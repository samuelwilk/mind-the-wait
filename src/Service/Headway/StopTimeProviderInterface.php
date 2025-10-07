<?php

declare(strict_types=1);

namespace App\Service\Headway;

/**
 * Abstraction over GTFS stop time lookups so headway logic can be unit tested.
 *
 * @internal exposed for testability; production implementation is RealtimeStopTimeProvider
 */
interface StopTimeProviderInterface
{
    /**
     * Retrieve ordered stop_time data for the given trip from any available source.
     *
     * @return list<array{stop_id: string, seq: int, arr: int|null, dep: int|null, delay: int|null}>|null
     */
    public function getStopTimesForTrip(string $tripId): ?array;

    /**
     * Return trip start and end times in absolute epoch seconds when available.
     *
     * @return array{start: int, end: int}|null
     */
    public function getTripDuration(string $tripId): ?array;
}
