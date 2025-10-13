<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Comprehensive route detail data for route detail page.
 */
final readonly class RouteDetailDto
{
    /**
     * @param array<string, mixed> $performanceTrendChart ECharts config for 30-day performance trend
     * @param array<string, mixed> $weatherImpactChart    ECharts config for weather impact comparison
     * @param array<string, mixed> $timeOfDayHeatmap      ECharts config for time-of-day heatmap
     * @param array<string, mixed> $stats                 Summary statistics
     */
    public function __construct(
        public array $performanceTrendChart,
        public array $weatherImpactChart,
        public array $timeOfDayHeatmap,
        public array $stats,
    ) {
    }
}
