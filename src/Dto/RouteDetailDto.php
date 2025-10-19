<?php

declare(strict_types=1);

namespace App\Dto;

use App\ValueObject\Chart\Chart;

/**
 * Comprehensive route detail data for route detail page.
 */
final readonly class RouteDetailDto
{
    /**
     * @param Chart                $performanceTrendChart 30-day performance trend chart
     * @param Chart                $weatherImpactChart    Weather impact comparison chart
     * @param Chart                $timeOfDayHeatmap      Time-of-day heatmap chart
     * @param array<string, mixed> $stats                 Summary statistics
     */
    public function __construct(
        public Chart $performanceTrendChart,
        public Chart $weatherImpactChart,
        public Chart $timeOfDayHeatmap,
        public array $stats,
    ) {
    }
}
