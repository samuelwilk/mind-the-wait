<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\ArrivalPredictionDto;
use App\Dto\CountsDTO;
use App\Dto\EnrichedVehicleDTO;
use App\Dto\RouteSnapshotDTO;
use App\Dto\VehicleDto;
use App\Dto\VehicleStatusDto;
use App\Entity\Route;
use App\Enum\VehiclePunctualityLabel;
use App\Enum\VehicleStatusColor;
use App\Repository\RouteRepository;
use App\Repository\StopTimeRepository;
use App\Service\Prediction\ArrivalPredictor;
use Psr\Cache\CacheItemPoolInterface;

use function array_filter;
use function array_values;
use function count;
use function is_array;

/**
 * Orchestrates real-time route tracking data.
 *
 * Combines multiple services to build a complete route snapshot for live UI.
 */
final readonly class RouteTrackingService
{
    private const CACHE_TTL = 0; // No cache - fresh data on every SSE update

    public function __construct(
        private RouteRepository $routeRepo,
        private RealtimeSnapshotService $snapshotService,
        private VehicleStatusService $vehicleStatusService,
        private ArrivalPredictor $arrivalPredictor,
        private HeadwayService $headwayService,
        private StopSequenceService $stopSequenceService,
        private StopTimeRepository $stopTimeRepo,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get complete route snapshot for live tracking.
     *
     * @param string $routeGtfsId Route GTFS ID
     *
     * @return RouteSnapshotDTO Complete snapshot with all live data
     *
     * @throws \RuntimeException If route not found
     */
    public function snapshot(string $routeGtfsId): RouteSnapshotDTO
    {
        // For live SSE updates, always fetch fresh data (no cache)
        if (self::CACHE_TTL === 0) {
            return $this->buildSnapshot($routeGtfsId);
        }

        // Check cache first
        $cacheKey = "route_snapshot_{$routeGtfsId}";
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        // Build snapshot
        $snapshot = $this->buildSnapshot($routeGtfsId);

        // Cache for short duration
        $item->set($snapshot);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $snapshot;
    }

    /**
     * Build fresh snapshot without cache.
     */
    private function buildSnapshot(string $routeGtfsId): RouteSnapshotDTO
    {
        // Get route entity
        $route = $this->routeRepo->findOneBy(['gtfsId' => $routeGtfsId]);
        if ($route === null) {
            throw new \RuntimeException("Route not found: {$routeGtfsId}");
        }

        // Get enriched realtime snapshot (vehicles with status)
        $realtimeSnapshot = $this->snapshotService->snapshot();
        $allVehicles      = $realtimeSnapshot['vehicles'] ?? [];
        $snapshotTs       = $realtimeSnapshot['ts']       ?? time();

        // Filter vehicles for this route
        $routeVehicles = array_values(array_filter(
            $allVehicles,
            fn ($v) => ($v['route'] ?? null) === $routeGtfsId
        ));

        // Build enriched vehicle DTOs with arrival predictions
        $enrichedVehicles = [];
        $timelineArrivals = []; // For stop timeline (all upcoming stops)

        foreach ($routeVehicles as $rawVehicle) {
            // Parse vehicle data
            $vehicleDto = VehicleDto::fromArray($rawVehicle);
            if ($vehicleDto === null) {
                continue;
            }

            $vehicleId = $rawVehicle['id'] ?? $vehicleDto->tripId ?? null;
            if ($vehicleId === null) {
                continue;
            }

            // Parse status from enriched snapshot
            $statusDto = null;
            if (isset($rawVehicle['status']) && is_array($rawVehicle['status'])) {
                $statusDto = $this->parseStatusDto($rawVehicle['status']);
            }

            // Predict next arrival for this vehicle (for vehicle card)
            $nextArrival = $this->predictNextArrivalForVehicle($vehicleDto, $vehicleId);

            // For stop timeline, just use the next arrival (don't predict ALL stops - too expensive!)
            if ($nextArrival !== null) {
                $timelineArrivals[] = $nextArrival;
            }

            $enrichedVehicles[] = new EnrichedVehicleDTO(
                vehicleId: $vehicleId,
                vehicle: $vehicleDto,
                status: $statusDto ?? $this->getDefaultStatus(),
                nextArrival: $nextArrival
            );
        }

        // Calculate headway
        $vehicleDtos = array_map(
            fn ($ev) => $ev->vehicle,
            $enrichedVehicles
        );
        $headway = $this->headwayService->calculateHeadway($vehicleDtos);

        // Build stop timeline
        $stops = $this->stopSequenceService->buildStopTimeline($route, $timelineArrivals);

        // Count statistics
        $counts = new CountsDTO(
            vehiclesOnline: count($enrichedVehicles),
            timepoints: count(array_filter($stops, fn ($s) => $s->isTimepoint)),
            totalStops: count($stops)
        );

        return new RouteSnapshotDTO(
            routeId: $routeGtfsId,
            updatedAt: \DateTimeImmutable::createFromFormat('U', (string) $snapshotTs) ?: new \DateTimeImmutable(),
            stops: $stops,
            vehicles: $enrichedVehicles,
            headway: $headway,
            counts: $counts
        );
    }

    /**
     * Predict next arrival for a vehicle by finding its next stop.
     *
     * Returns ONLY the next stop (for vehicle card display).
     * For the stop timeline, we need predictions for ALL upcoming stops.
     */
    private function predictNextArrivalForVehicle(VehicleDto $vehicle, string $vehicleId): ?ArrivalPredictionDto
    {
        $predictions = $this->predictAllArrivalsForVehicle($vehicle, $vehicleId);

        return $predictions[0] ?? null;
    }

    /**
     * Predict arrivals for ALL upcoming stops on this vehicle's trip.
     *
     * @return list<ArrivalPredictionDto>
     */
    private function predictAllArrivalsForVehicle(VehicleDto $vehicle, string $vehicleId): array
    {
        if ($vehicle->tripId === null) {
            return [];
        }

        // Get stop times for this trip from repository
        $stopTimes = $this->stopTimeRepo->getStopTimesForTrip($vehicle->tripId);
        if ($stopTimes === null) {
            return [];
        }

        // Generate predictions for all upcoming stops
        $predictions = [];
        $now         = time();

        foreach ($stopTimes as $stopTime) {
            $stopId = $stopTime['stop_id'] ?? null;
            if ($stopId === null) {
                continue;
            }

            // Try to predict arrival at this stop
            $prediction = $this->arrivalPredictor->predictArrival($stopId, $vehicle->tripId, $vehicleId);
            if ($prediction !== null && $prediction->arrivalAt >= $now - 60) {
                // Valid future arrival (or very recent past)
                $predictions[] = $prediction;
            }
        }

        return $predictions;
    }

    /**
     * Parse VehicleStatusDto from enriched snapshot array.
     */
    private function parseStatusDto(array $status): ?VehicleStatusDto
    {
        try {
            return new VehicleStatusDto(
                color: VehicleStatusColor::from($status['color'] ?? ''),
                label: VehiclePunctualityLabel::from($status['label'] ?? ''),
                severity: $status['severity']          ?? 'unknown',
                deviationSec: $status['deviation_sec'] ?? 0,
                reason: $status['reason']              ?? null,
                feedback: $status['feedback']          ?? []
            );
        } catch (\ValueError) {
            return null;
        }
    }

    /**
     * Get default status for vehicles without status data.
     */
    private function getDefaultStatus(): VehicleStatusDto
    {
        return new VehicleStatusDto(
            color: VehicleStatusColor::YELLOW,
            label: VehiclePunctualityLabel::ON_TIME,
            severity: '✓ vibing',
            deviationSec: 0
        );
    }
}
