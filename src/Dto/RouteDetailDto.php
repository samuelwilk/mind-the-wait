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
     * @param Chart                $performanceTrendChart          30-day performance trend chart
     * @param Chart                $weatherImpactChart             Weather impact comparison chart
     * @param Chart                $timeOfDayHeatmap               Time-of-day heatmap chart
     * @param Chart|null           $stopReliabilityChartDirection0 Stop reliability for direction 0 (null if no data)
     * @param Chart|null           $stopReliabilityChartDirection1 Stop reliability for direction 1 (null if no data)
     * @param array<string, mixed> $stats                          Summary statistics
     */
    public function __construct(
        public Chart $performanceTrendChart,
        public Chart $weatherImpactChart,
        public Chart $timeOfDayHeatmap,
        public ?Chart $stopReliabilityChartDirection0,
        public ?Chart $stopReliabilityChartDirection1,
        public array $stats,
    ) {
    }
}
