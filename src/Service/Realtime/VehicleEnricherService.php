<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\ArrivalPredictionDto;
use App\Dto\EnrichedVehicleDTO;
use App\Dto\VehicleDto;
use App\Dto\VehicleStatusDto;
use App\Enum\VehiclePunctualityLabel;
use App\Enum\VehicleStatusColor;
use App\Repository\StopTimeRepository;
use App\Service\Prediction\ArrivalPredictor;
use Psr\Cache\CacheItemPoolInterface;

use function is_array;

/**
 * Lightweight vehicle enrichment for Mercure broadcasts.
 *
 * Adds arrival predictions to raw vehicle data without building full snapshots.
 * Optimized for high-frequency broadcasts (every 5 seconds).
 */
final readonly class VehicleEnricherService
{
    private const PREDICTION_CACHE_TTL = 60; // 60 second cache - predictions are stable

    public function __construct(
        private StopTimeRepository $stopTimeRepo,
        private ArrivalPredictor $arrivalPredictor,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Enrich raw vehicle data with arrival predictions (with caching).
     *
     * @param array $rawVehicles Raw vehicle data from Redis
     *
     * @return list<EnrichedVehicleDTO> Enriched vehicles ready for display
     */
    public function enrichVehicles(array $rawVehicles): array
    {
        $enrichedVehicles = [];

        foreach ($rawVehicles as $rawVehicle) {
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

            // Get cached prediction or compute new one
            $nextArrival = $this->getCachedPrediction($vehicleDto, $vehicleId);

            $enrichedVehicles[] = new EnrichedVehicleDTO(
                vehicleId: $vehicleId,
                vehicle: $vehicleDto,
                status: $statusDto ?? $this->getDefaultStatus(),
                nextArrival: $nextArrival
            );
        }

        return $enrichedVehicles;
    }

    /**
     * Get prediction from cache or compute it.
     */
    private function getCachedPrediction(VehicleDto $vehicle, string $vehicleId): ?ArrivalPredictionDto
    {
        if ($vehicle->tripId === null) {
            return null;
        }

        $cacheKey = "vehicle_prediction_{$vehicleId}";
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $prediction = $this->computePrediction($vehicle, $vehicleId);

        $item->set($prediction);
        $item->expiresAfter(self::PREDICTION_CACHE_TTL);
        $this->cache->save($item);

        return $prediction;
    }

    /**
     * Compute next arrival prediction for a vehicle.
     */
    private function computePrediction(VehicleDto $vehicle, string $vehicleId): ?ArrivalPredictionDto
    {
        if ($vehicle->tripId === null) {
            return null;
        }

        $stopTimes = $this->stopTimeRepo->getStopTimesForTrip($vehicle->tripId);
        if ($stopTimes === null) {
            return null;
        }

        $now = time();

        foreach ($stopTimes as $stopTime) {
            $stopId = $stopTime['stop_id'] ?? null;
            if ($stopId === null) {
                continue;
            }

            $prediction = $this->arrivalPredictor->predictArrival($stopId, $vehicle->tripId, $vehicleId);
            if ($prediction !== null && $prediction->arrivalAt >= $now - 60) {
                return $prediction;
            }
        }

        return null;
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
