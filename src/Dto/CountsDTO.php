<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Count statistics for a route snapshot.
 */
final readonly class CountsDTO
{
    public function __construct(
        public int $vehiclesOnline,
        public int $timepoints,
        public int $totalStops,
    ) {
    }
}
