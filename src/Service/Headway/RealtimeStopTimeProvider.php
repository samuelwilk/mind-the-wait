<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Repository\RealtimeRepository;

use function count;

/**
 * Provides stop_time data from GTFS-RT TripUpdate feed when static data is unavailable.
 * This allows position-based headway calculation even when trip IDs don't match static GTFS.
 */
final readonly class RealtimeStopTimeProvider
{
    public function __construct(
        private RealtimeRepository $realtimeRepo,
    ) {
    }

    /**
     * Get stop sequence data for a trip from the realtime TripUpdate feed.
     *
     * @param string $tripId GTFS trip_id
     *
     * @return list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}>|null
     */
    public function getStopTimesForTrip(string $tripId): ?array
    {
        $snapshot = $this->realtimeRepo->snapshot();
        $trips    = $snapshot['trips'] ?? [];

        foreach ($trips as $trip) {
            if (($trip['trip'] ?? null) === $tripId) {
                return $trip['stops'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get first and last stop times for calculating trip duration.
     *
     * @return array{start: int, end: int}|null [start_time, end_time] in seconds
     */
    public function getTripDuration(string $tripId): ?array
    {
        $stopTimes = $this->getStopTimesForTrip($tripId);
        if ($stopTimes === null || count($stopTimes) < 2) {
            return null;
        }

        $first = $stopTimes[0];
        $last  = $stopTimes[count($stopTimes) - 1];

        $startTime = $first['dep'] ?? $first['arr'] ?? null;
        $endTime   = $last['arr']  ?? $last['dep'] ?? null;

        if ($startTime === null || $endTime === null) {
            return null;
        }

        return ['start' => $startTime, 'end' => $endTime];
    }
}
