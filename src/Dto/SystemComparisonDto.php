<?php

declare(strict_types=1);

namespace App\Dto;

use function round;

/**
 * System-wide comparison data for a specific route.
 *
 * Shows how a route ranks compared to all other routes in the system.
 */
final readonly class SystemComparisonDto
{
    public function __construct(
        public int $routeRank,
        public int $totalRoutes,
        public float $routePerformance,
        public float $systemMedianPerformance,
    ) {
    }

    /**
     * Get percentile rank (0-100).
     *
     * 100 = best in system, 0 = worst in system
     */
    public function getPercentile(): int
    {
        if ($this->totalRoutes <= 1) {
            return 100;
        }

        // Calculate percentile: (routes worse than this route / total routes) × 100
        $routesWorse = $this->totalRoutes - $this->routeRank;
        $percentile  = ($routesWorse / ($this->totalRoutes - 1)) * 100;

        return (int) round($percentile);
    }

    /**
     * Get performance relative to system median (percentage points difference).
     */
    public function getPerformanceDelta(): float
    {
        return round($this->routePerformance - $this->systemMedianPerformance, 1);
    }

    /**
     * Get human-readable ranking description.
     */
    public function getRankingDescription(): string
    {
        $percentile = $this->getPercentile();

        return match (true) {
            $percentile >= 90 => 'Excellent - Top 10%',
            $percentile >= 75 => 'Above Average - Top 25%',
            $percentile >= 50 => 'Average - Middle 50%',
            $percentile >= 25 => 'Below Average - Bottom 50%',
            default           => 'Poor - Bottom 25%',
        };
    }

    /**
     * Get Tailwind CSS color class for ranking badge.
     */
    public function getColorClass(): string
    {
        $percentile = $this->getPercentile();

        return match (true) {
            $percentile >= 75 => 'bg-success-100 text-success-800 border-success-200',
            $percentile >= 50 => 'bg-blue-100 text-blue-800 border-blue-200',
            $percentile >= 25 => 'bg-warning-100 text-warning-800 border-warning-200',
            default           => 'bg-danger-100 text-danger-800 border-danger-200',
        };
    }

    /**
     * Check if route is underperforming compared to system.
     */
    public function isUnderperforming(): bool
    {
        return $this->routePerformance < $this->systemMedianPerformance - 10.0;
    }
}
