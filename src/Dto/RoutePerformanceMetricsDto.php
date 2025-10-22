<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Aggregated performance metrics for a route on a specific date.
 *
 * Returned by repository SQL aggregations to avoid loading entity collections into PHP.
 */
final readonly class RoutePerformanceMetricsDto
{
    /**
     * @param list<int> $delays All delay values for median calculation
     */
    public function __construct(
        public int $totalPredictions,
        public int $highConfidenceCount,
        public int $mediumConfidenceCount,
        public int $lowConfidenceCount,
        public ?int $avgDelaySec,
        public array $delays,
        public int $onTimeCount,
        public int $lateCount,
        public int $earlyCount,
        public ?float $onTimePercentage,
        public ?float $latePercentage,
        public ?float $earlyPercentage,
    ) {
    }
}
