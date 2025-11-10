<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Complete snapshot of route state for real-time tracking.
 *
 * Contains all data needed to render the live tracking UI.
 */
final readonly class RouteSnapshotDTO
{
    /**
     * @param string                   $routeId   Route GTFS ID
     * @param \DateTimeImmutable       $updatedAt Timestamp of snapshot
     * @param list<StopDTO>            $stops     All stops on route with approaching vehicles
     * @param list<EnrichedVehicleDTO> $vehicles  All vehicles serving this route
     * @param HeadwayDTO               $headway   Current headway window
     * @param CountsDTO                $counts    Count statistics
     */
    public function __construct(
        public string $routeId,
        public \DateTimeImmutable $updatedAt,
        public array $stops,
        public array $vehicles,
        public HeadwayDTO $headway,
        public CountsDTO $counts,
    ) {
    }

    /**
     * Check if realtime feed data is stale (> 2 minutes old).
     */
    public function isFeedStale(): bool
    {
        $now = new \DateTimeImmutable();
        $age = $now->getTimestamp() - $this->updatedAt->getTimestamp();

        return $age > 120; // 2 minutes
    }

    /**
     * Get age of snapshot in seconds.
     */
    public function getAgeSeconds(): int
    {
        $now = new \DateTimeImmutable();

        return $now->getTimestamp() - $this->updatedAt->getTimestamp();
    }
}
