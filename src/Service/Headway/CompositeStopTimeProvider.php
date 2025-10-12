<?php

declare(strict_types=1);

namespace App\Service\Headway;

/**
 * Composite provider that tries multiple sources with fallback logic.
 *
 * Priority:
 * 1. GTFS-RT TripUpdate feed (realtime predictions) - HIGH confidence
 * 2. Database static schedule - MEDIUM confidence
 */
final readonly class CompositeStopTimeProvider implements StopTimeProviderInterface
{
    public function __construct(
        private RealtimeStopTimeProvider $realtimeProvider,
        private DatabaseStopTimeProvider $databaseProvider,
    ) {
    }

    /**
     * Get stop sequence data for a trip, trying realtime first, then database.
     *
     * @param string $tripId GTFS trip_id
     *
     * @return list<array{stop_id: string, seq: int, arr: int|null, dep: int|null, delay: int|null}>|null
     */
    public function getStopTimesForTrip(string $tripId): ?array
    {
        // Try realtime TripUpdate feed first (includes delay predictions)
        $stopTimes = $this->realtimeProvider->getStopTimesForTrip($tripId);
        if ($stopTimes !== null) {
            return $stopTimes;
        }

        // Fallback to database static schedule
        return $this->databaseProvider->getStopTimesForTrip($tripId);
    }

    /**
     * Get first and last stop times for calculating trip duration.
     *
     * @return array{start: int, end: int}|null [start_time, end_time] in seconds
     */
    public function getTripDuration(string $tripId): ?array
    {
        // Try realtime first
        $duration = $this->realtimeProvider->getTripDuration($tripId);
        if ($duration !== null) {
            return $duration;
        }

        // Fallback to database
        return $this->databaseProvider->getTripDuration($tripId);
    }
}
