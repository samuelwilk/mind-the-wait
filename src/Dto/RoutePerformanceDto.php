<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Contains aggregated daily performance metrics for a route.
 */
final readonly class RoutePerformanceDto
{
    public function __construct(
        public int $totalPredictions,
        public int $highConfidenceCount,
        public int $mediumConfidenceCount,
        public int $lowConfidenceCount,
        public ?int $avgDelaySec,
        public ?int $medianDelaySec,
        public ?float $onTimePercentage,
        public ?float $latePercentage,
        public ?float $earlyPercentage,
    ) {
    }
}
