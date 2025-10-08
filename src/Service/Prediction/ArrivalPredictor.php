<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Dto\ArrivalPredictionDto;
use App\Dto\VehicleDto;
use App\Enum\PredictionConfidence;
use App\Repository\RealtimeRepository;
use App\Repository\StopRepository;
use App\Repository\StopTimeRepository;
use App\Repository\TripRepository;
use App\Repository\VehicleFeedbackRepositoryInterface;
use App\Service\Headway\PositionInterpolator;
use App\Service\Headway\StopTimeProviderInterface;
use App\Service\Realtime\VehicleStatusService;

use function array_slice;
use function count;
use function time;
use function usort;

final readonly class ArrivalPredictor implements ArrivalPredictorInterface
{
    private const MAX_PAST_ARRIVAL_SEC = 60; // Ignore arrivals >1 min in the past

    public function __construct(
        private RealtimeRepository $realtimeRepo,
        private StopTimeProviderInterface $realtimeStopTimeProvider,
        private StopTimeRepository $staticStopTimeRepo,
        private StopRepository $stopRepo,
        private TripRepository $tripRepo,
        private PositionInterpolator $positionInterpolator,
        private VehicleStatusService $statusService,
        private VehicleFeedbackRepositoryInterface $feedbackRepo,
    ) {
    }

    public function predictArrival(string $stopId, string $tripId, string $vehicleId): ?ArrivalPredictionDto
    {
        // Get vehicle data
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicle  = $this->findVehicleById($snapshot['vehicles'] ?? [], $vehicleId);
        if ($vehicle === null) {
            return null;
        }

        // Try TripUpdate predictions first (high confidence)
        $prediction = $this->fromTripUpdate($stopId, $tripId, $vehicle);
        if ($prediction !== null) {
            return $prediction;
        }

        // Try GPS interpolation (medium confidence)
        $prediction = $this->fromGpsInterpolation($stopId, $vehicle);
        if ($prediction !== null) {
            return $prediction;
        }

        // Fallback to static schedule (low confidence)
        return $this->fromStaticSchedule($stopId, $tripId, $vehicle);
    }

    public function predictArrivalsForStop(string $stopId, ?int $limit = null, ?string $routeId = null): array
    {
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicles = $snapshot['vehicles'] ?? [];
        $now      = time();

        $predictions = [];
        foreach ($vehicles as $rawVehicle) {
            if (!isset($rawVehicle['trip'])) {
                continue;
            }

            // Filter by route if specified
            if ($routeId !== null && ($rawVehicle['route'] ?? null) !== $routeId) {
                continue;
            }

            $vehicle    = VehicleDto::fromArray($rawVehicle);
            $prediction = $this->predictArrivalForVehicle($stopId, $vehicle, $rawVehicle);

            if ($prediction !== null && $prediction->arrivalAt >= $now - self::MAX_PAST_ARRIVAL_SEC) {
                $predictions[] = $prediction;
            }
        }

        // Sort by arrival time (soonest first)
        usort($predictions, fn ($a, $b) => $a->arrivalAt <=> $b->arrivalAt);

        return $limit !== null ? array_slice($predictions, 0, $limit) : $predictions;
    }

    private function predictArrivalForVehicle(string $stopId, VehicleDto $vehicle, array $rawVehicle): ?ArrivalPredictionDto
    {
        if ($vehicle->tripId === null) {
            return null;
        }

        $vehicleId = $rawVehicle['id'] ?? $vehicle->tripId; // Fallback to trip if no vehicle ID

        return $this->predictArrival($stopId, $vehicle->tripId, $vehicleId);
    }

    /**
     * Tier 1: Use GTFS-RT TripUpdate predictions (highest confidence).
     */
    private function fromTripUpdate(string $stopId, string $tripId, VehicleDto $vehicle): ?ArrivalPredictionDto
    {
        $stopTimes = $this->realtimeStopTimeProvider->getStopTimesForTrip($tripId);
        if ($stopTimes === null) {
            return null;
        }

        foreach ($stopTimes as $stop) {
            if ($stop['stop_id'] !== $stopId) {
                continue;
            }

            $arrivalTime = $stop['arr'] ?? $stop['dep'] ?? null;
            if ($arrivalTime === null) {
                continue;
            }

            return $this->buildPrediction(
                vehicle: $vehicle,
                stopId: $stopId,
                arrivalAt: $arrivalTime,
                confidence: PredictionConfidence::HIGH
            );
        }

        return null;
    }

    /**
     * Tier 2: Use GPS position + schedule to interpolate arrival time (medium confidence).
     */
    private function fromGpsInterpolation(string $stopId, VehicleDto $vehicle): ?ArrivalPredictionDto
    {
        if ($vehicle->lat === null || $vehicle->lon === null || $vehicle->tripId === null) {
            return null;
        }

        // Use position interpolator to estimate arrival time
        $stop = $this->stopRepo->findOneByGtfsId($stopId);
        if ($stop === null) {
            return null;
        }

        // Find the stop in the trip's sequence
        $stopTimes = $this->staticStopTimeRepo->getStopTimesForTrip($vehicle->tripId);
        if ($stopTimes === null) {
            return null;
        }

        $targetStop = null;
        foreach ($stopTimes as $st) {
            if ($st['stop_id'] === $stopId) {
                $targetStop = $st;
                break;
            }
        }

        if ($targetStop === null) {
            return null;
        }

        // Calculate progress through route
        $tripProgress = $this->positionInterpolator->estimateProgress($vehicle, $stopTimes);
        if ($tripProgress === null) {
            return null;
        }

        // Estimate time to target stop
        $targetProgress = $targetStop['seq'] / count($stopTimes);
        $tripDuration   = $this->realtimeStopTimeProvider->getTripDuration($vehicle->tripId);
        if ($tripDuration === null) {
            return null;
        }

        $totalTripSec     = $tripDuration['end'] - $tripDuration['start'];
        $remainingRatio   = $targetProgress      - $tripProgress;
        $estimatedDelay   = $remainingRatio * $totalTripSec;
        $estimatedArrival = time() + (int) $estimatedDelay;

        return $this->buildPrediction(
            vehicle: $vehicle,
            stopId: $stopId,
            arrivalAt: $estimatedArrival,
            confidence: PredictionConfidence::MEDIUM
        );
    }

    /**
     * Tier 3: Fallback to static schedule (lowest confidence).
     */
    private function fromStaticSchedule(string $stopId, string $tripId, VehicleDto $vehicle): ?ArrivalPredictionDto
    {
        $stopTimes = $this->staticStopTimeRepo->getStopTimesForTrip($tripId);
        if ($stopTimes === null) {
            return null;
        }

        foreach ($stopTimes as $stop) {
            if ($stop['stop_id'] !== $stopId) {
                continue;
            }

            $arrivalTime = $stop['arr'] ?? $stop['dep'] ?? null;
            if ($arrivalTime === null) {
                continue;
            }

            return $this->buildPrediction(
                vehicle: $vehicle,
                stopId: $stopId,
                arrivalAt: $arrivalTime,
                confidence: PredictionConfidence::LOW
            );
        }

        return null;
    }

    private function buildPrediction(
        VehicleDto $vehicle,
        string $stopId,
        int $arrivalAt,
        PredictionConfidence $confidence,
    ): ArrivalPredictionDto {
        // Get trip headsign
        $trip     = $this->tripRepo->findOneByGtfsId($vehicle->tripId ?? '');
        $headsign = $trip?->getHeadsign();

        // Get vehicle status
        $snapshot = $this->realtimeRepo->snapshot();
        $enriched = $this->statusService->enrichSnapshot($snapshot);
        $status   = $this->findVehicleStatus($enriched['vehicles'] ?? [], $vehicle->tripId ?? '');

        // Get current location (stops away)
        $stopsAway       = null;
        $currentLocation = null;
        if ($vehicle->lat !== null && $vehicle->lon !== null) {
            $stopsAway       = $this->calculateStopsAway($stopId, $vehicle);
            $currentLocation = [
                'lat'        => $vehicle->lat,
                'lon'        => $vehicle->lon,
                'stops_away' => $stopsAway,
            ];
        }

        // Get feedback summary
        $vehicleId       = $vehicle->tripId ?? ''; // TODO: better vehicle ID resolution
        $feedbackSummary = $this->feedbackRepo->getSummary($vehicleId);

        return new ArrivalPredictionDto(
            vehicleId: $vehicleId,
            routeId: $vehicle->routeId,
            tripId: $vehicle->tripId ?? '',
            stopId: $stopId,
            headsign: $headsign,
            arrivalAt: $arrivalAt,
            confidence: $confidence,
            status: $status,
            currentLocation: $currentLocation,
            feedbackSummary: $feedbackSummary
        );
    }

    private function findVehicleById(array $vehicles, string $vehicleId): ?VehicleDto
    {
        foreach ($vehicles as $raw) {
            if (($raw['id'] ?? null) === $vehicleId || ($raw['trip'] ?? null) === $vehicleId) {
                return VehicleDto::fromArray($raw);
            }
        }

        return null;
    }

    private function findVehicleStatus(array $vehicles, string $tripId): ?\App\Dto\VehicleStatusDto
    {
        foreach ($vehicles as $v) {
            if (($v['trip'] ?? null) === $tripId && isset($v['status'])) {
                return new \App\Dto\VehicleStatusDto(
                    color: \App\Enum\VehicleStatusColor::from($v['status']['color']),
                    label: \App\Enum\VehiclePunctualityLabel::from($v['status']['label']),
                    severity: $v['status']['severity'],
                    deviationSec: $v['status']['deviation_sec'],
                    reason: $v['status']['reason']     ?? null,
                    feedback: $v['status']['feedback'] ?? []
                );
            }
        }

        return null;
    }

    private function calculateStopsAway(string $targetStopId, VehicleDto $vehicle): ?int
    {
        if ($vehicle->tripId === null) {
            return null;
        }

        $stopTimes = $this->staticStopTimeRepo->getStopTimesForTrip($vehicle->tripId);
        if ($stopTimes === null) {
            return null;
        }

        // Find nearest stop to vehicle
        $nearestStop = $this->positionInterpolator->findNearestStop($vehicle);
        if ($nearestStop === null) {
            return null;
        }

        // Find both stops in sequence
        $nearestSeq = null;
        $targetSeq  = null;
        foreach ($stopTimes as $st) {
            if ($st['stop_id'] === $nearestStop->getGtfsId()) {
                $nearestSeq = $st['seq'];
            }
            if ($st['stop_id'] === $targetStopId) {
                $targetSeq = $st['seq'];
            }
        }

        if ($nearestSeq === null || $targetSeq === null) {
            return null;
        }

        return max(0, $targetSeq - $nearestSeq);
    }
}
