<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Headway time window for a route direction.
 *
 * Represents the min/max time gaps between consecutive vehicles.
 */
final readonly class HeadwayDTO
{
    public function __construct(
        public int $minSec,
        public int $maxSec,
    ) {
    }

    /**
     * Get human-readable headway range display.
     *
     * Examples: "7-10m", "2-3m", "15-20m"
     */
    public function getDisplayRange(): string
    {
        $minMin = (int) round($this->minSec / 60);
        $maxMin = (int) round($this->maxSec / 60);

        if ($minMin === $maxMin) {
            return "{$minMin}m";
        }

        return "{$minMin}-{$maxMin}m";
    }
}
