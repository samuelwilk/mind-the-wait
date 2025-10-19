<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents a single bucket in the time-of-day performance heatmap.
 */
final readonly class RoutePerformanceHeatmapBucketDto
{
    public function __construct(
        public int $dayIndex,
        public int $hourIndex,
        public ?float $onTimePercentage,
    ) {
    }
}
