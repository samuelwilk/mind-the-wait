<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Stop information with approaching vehicles for timeline display.
 */
final readonly class StopDTO
{
    /**
     * @param string                     $id                  Stop GTFS ID
     * @param string                     $name                Stop name
     * @param bool                       $isTimepoint         Whether this is a schedule timepoint
     * @param int                        $sequence            Stop sequence number (route order)
     * @param list<ArrivalPredictionDto> $approachingVehicles Vehicles approaching this stop, sorted by ETA
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $isTimepoint,
        public int $sequence,
        public array $approachingVehicles = [],
    ) {
    }
}
