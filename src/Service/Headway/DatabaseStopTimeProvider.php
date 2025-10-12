<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Repository\StopTimeRepository;

use function count;

/**
 * Provides stop_time data from the PostgreSQL database.
 *
 * Converts GTFS "seconds since midnight" format to absolute unix timestamps
 * based on the current service day.
 */
final readonly class DatabaseStopTimeProvider implements StopTimeProviderInterface
{
    public function __construct(
        private StopTimeRepository $stopTimeRepo,
    ) {
    }

    /**
     * Get stop sequence data for a trip from the database.
     *
     * @param string $tripId GTFS trip_id
     *
     * @return list<array{stop_id: string, seq: int, arr: int|null, dep: int|null, delay: int|null}>|null
     */
    public function getStopTimesForTrip(string $tripId): ?array
    {
        $stopTimes = $this->stopTimeRepo->getStopTimesForTrip($tripId);
        if ($stopTimes === null) {
            return null;
        }

        // Get current service day start (today at midnight)
        $now             = new \DateTimeImmutable();
        $serviceDayStart = $now->setTime(0, 0, 0)->getTimestamp();

        $result = [];
        foreach ($stopTimes as $st) {
            $arrTime = $st['arr'] !== null ? $serviceDayStart + $st['arr'] : null;
            $depTime = $st['dep'] !== null ? $serviceDayStart + $st['dep'] : null;

            $result[] = [
                'stop_id' => $st['stop_id'],
                'seq'     => $st['seq'],
                'arr'     => $arrTime,
                'dep'     => $depTime,
                'delay'   => null,  // No delay info from static schedule
            ];
        }

        return $result;
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
