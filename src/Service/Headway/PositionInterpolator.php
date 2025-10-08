<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\VehicleDto;
use App\Entity\Stop;
use App\Entity\StopTime;
use App\Repository\StopRepository;
use App\Repository\StopTimeRepository;

use function count;

use const PHP_FLOAT_MAX;

/**
 * Interpolates vehicle position along route using scheduled stop sequences.
 * This allows proper headway calculation based on route progress, not GPS timestamp deltas.
 */
final readonly class PositionInterpolator implements CrossingTimeEstimatorInterface
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private StopTimeRepository $stopTimeRepo,
        private StopRepository $stopRepo,
        private RealtimeStopTimeProvider $realtimeStopTimes,
    ) {
    }

    /**
     * Calculate progress along route for a vehicle (0.0 = start, 1.0 = end).
     * Uses nearest stop to vehicle's GPS position and its sequence in the trip.
     * Tries static GTFS first, falls back to realtime TripUpdate data.
     *
     * @return float|null Progress ratio (0.0-1.0), or null if cannot determine
     */
    public function calculateRouteProgress(VehicleDto $vehicle): ?float
    {
        if ($vehicle->lat === null || $vehicle->lon === null || $vehicle->tripId === null) {
            return null;
        }

        // Try static GTFS stop_times first
        $stopTimes = $this->stopTimeRepo->findByTripGtfsId($vehicle->tripId);
        if (!empty($stopTimes)) {
            return $this->calculateProgressFromStatic($vehicle, $stopTimes);
        }

        // Fallback: use realtime TripUpdate data
        $realtimeStops = $this->realtimeStopTimes->getStopTimesForTrip($vehicle->tripId);
        if ($realtimeStops !== null) {
            return $this->calculateProgressFromRealtime($vehicle, $realtimeStops);
        }

        return null;
    }

    /**
     * Calculate progress using static GTFS stop_times.
     *
     * @param list<StopTime> $stopTimes
     */
    private function calculateProgressFromStatic(VehicleDto $vehicle, array $stopTimes): ?float
    {
        $nearestSeq  = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stopTimes as $st) {
            $stop = $st->getStop();
            if ($stop === null) {
                continue;
            }

            $distance = $this->haversineDistance(
                $vehicle->lat,
                $vehicle->lon,
                $stop->getLat(),
                $stop->getLong()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestSeq  = $st->getStopSequence();
            }
        }

        if ($nearestSeq === null) {
            return null;
        }

        $maxSeq = max(array_map(static fn (StopTime $st) => $st->getStopSequence(), $stopTimes));
        if ($maxSeq <= 0) {
            return null;
        }

        return $nearestSeq / $maxSeq;
    }

    /**
     * Calculate progress using realtime TripUpdate stop data.
     *
     * @param list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}> $realtimeStops
     */
    private function calculateProgressFromRealtime(VehicleDto $vehicle, array $realtimeStops): ?float
    {
        $nearestSeq  = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($realtimeStops as $rt) {
            $stop = $this->stopRepo->findOneByGtfsId((string) $rt['stop_id']);
            if ($stop === null) {
                continue;
            }

            $distance = $this->haversineDistance(
                $vehicle->lat,
                $vehicle->lon,
                $stop->getLat(),
                $stop->getLong()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestSeq  = $rt['seq'];
            }
        }

        if ($nearestSeq === null) {
            return null;
        }

        $maxSeq = max(array_map(static fn (array $rt) => $rt['seq'], $realtimeStops));
        if ($maxSeq <= 0) {
            return null;
        }

        return $nearestSeq / $maxSeq;
    }

    /**
     * Calculate estimated time for vehicle to reach a reference point (progress = 0.5 by default).
     * This gives us a common "crossing point" to compare all vehicles on the route.
     *
     * @param float $referenceProgress Point along route to measure (0.0 = start, 1.0 = end)
     *
     * @return int|null Unix timestamp when vehicle will reach reference point
     */
    public function estimateTimeAtProgress(VehicleDto $vehicle, float $referenceProgress = 0.5): ?int
    {
        if ($vehicle->timestamp === null) {
            return null;
        }

        $currentProgress = $this->calculateRouteProgress($vehicle);
        if ($currentProgress === null) {
            return null;
        }

        // If already past reference point, can't estimate
        if ($currentProgress >= $referenceProgress) {
            // Return current timestamp as they've already passed the reference
            return $vehicle->timestamp;
        }

        // Get average speed from scheduled stop_times
        $avgSpeedSecsPerProgress = $this->estimateAverageSpeedForTrip($vehicle->tripId);
        if ($avgSpeedSecsPerProgress === null) {
            return null;
        }

        // Calculate time to reach reference point
        $progressRemaining  = $referenceProgress - $currentProgress;
        $secondsToReference = (int) ($progressRemaining * $avgSpeedSecsPerProgress);

        return $vehicle->timestamp + $secondsToReference;
    }

    /**
     * Estimate average speed (seconds per unit progress) for a trip based on schedule.
     * Tries static GTFS first, falls back to realtime TripUpdate data.
     *
     * @return float|null Average seconds to traverse full route
     */
    private function estimateAverageSpeedForTrip(?string $tripId): ?float
    {
        if ($tripId === null) {
            return null;
        }

        // Try static GTFS stop_times first
        $stopTimes = $this->stopTimeRepo->findByTripGtfsId($tripId);
        if (count($stopTimes) >= 2) {
            $first = $stopTimes[0];
            $last  = $stopTimes[count($stopTimes) - 1];

            $startTime = $first->getDepartureTime() ?? $first->getArrivalTime();
            $endTime   = $last->getArrivalTime()    ?? $last->getDepartureTime();

            if ($startTime !== null && $endTime !== null && $endTime > $startTime) {
                return (float) ($endTime - $startTime);
            }
        }

        // Fallback: use realtime TripUpdate data
        $duration = $this->realtimeStopTimes->getTripDuration($tripId);
        if ($duration !== null) {
            $totalSeconds = $duration['end'] - $duration['start'];
            if ($totalSeconds > 0) {
                return (float) $totalSeconds;
            }
        }

        return null;
    }

    /**
     * Calculate great-circle distance between two lat/lon points (Haversine formula).
     *
     * @return float Distance in kilometers
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad  = deg2rad($lat1);
        $lat2Rad  = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
        $c = 2                                       * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Find the nearest stop to a vehicle's current GPS position.
     *
     * @return Stop|null The nearest stop, or null if vehicle has no position or trip
     */
    public function findNearestStop(VehicleDto $vehicle): ?Stop
    {
        if ($vehicle->lat === null || $vehicle->lon === null || $vehicle->tripId === null) {
            return null;
        }

        $stopTimes = $this->stopTimeRepo->findByTripGtfsId($vehicle->tripId);
        if (empty($stopTimes)) {
            return null;
        }

        $nearestStop = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stopTimes as $st) {
            $stop = $st->getStop();
            if ($stop === null) {
                continue;
            }

            $distance = $this->haversineDistance(
                $vehicle->lat,
                $vehicle->lon,
                $stop->getLat(),
                $stop->getLong()
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestStop = $stop;
            }
        }

        return $nearestStop;
    }

    /**
     * Estimate progress along route for arrival prediction.
     * Alias for calculateRouteProgress() with clearer naming for external use.
     *
     * @param list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}>|null $stopTimes
     *
     * @return float|null Progress ratio (0.0-1.0)
     */
    public function estimateProgress(VehicleDto $vehicle, ?array $stopTimes = null): ?float
    {
        return $this->calculateRouteProgress($vehicle);
    }
}
