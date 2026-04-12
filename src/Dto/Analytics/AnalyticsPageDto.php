<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use App\ValueObject\Chart\Chart;

/**
 * Data transfer object for the complete analytics page.
 *
 * Aggregates all analytics data needed for rendering the analytics dashboard.
 */
final readonly class AnalyticsPageDto
{
    /**
     * @param DateRangeDto                $dateRange            Selected date range
     * @param AnalyticsSummaryDto         $summary              Summary metrics for cards
     * @param list<RouteComparisonDto>    $routeComparisons     Route comparison data
     * @param list<VehiclePerformanceDto> $topVehicles          Top performing vehicles
     * @param list<VehiclePerformanceDto> $worstVehicles        Worst performing vehicles
     * @param list<DayOfWeekTrendDto>     $dayOfWeekTrends      Day-of-week performance
     * @param list<HourlyTrendDto>        $hourlyTrends         Hourly performance trends
     * @param list<MonthlyTrendDto>       $monthlyTrends        Monthly performance trends
     * @param Chart|null                  $dayOfWeekChart       Day-of-week bar chart
     * @param Chart|null                  $hourlyChart          Hourly line chart
     * @param Chart|null                  $monthlyChart         Monthly trend chart
     * @param Chart|null                  $routeComparisonChart Route comparison bar chart
     * @param bool                        $hasHistoricalData    Whether historical data exists
     * @param string|null                 $dataGapNote          Note about data gaps if any
     * @param PredictionAccuracyDto|null  $predictionAccuracy   Prediction accuracy metrics
     * @param Chart|null                  $accuracyByHourChart  Accuracy by hour bar chart
     */
    public function __construct(
        public DateRangeDto $dateRange,
        public AnalyticsSummaryDto $summary,
        public array $routeComparisons = [],
        public array $topVehicles = [],
        public array $worstVehicles = [],
        public array $dayOfWeekTrends = [],
        public array $hourlyTrends = [],
        public array $monthlyTrends = [],
        public ?Chart $dayOfWeekChart = null,
        public ?Chart $hourlyChart = null,
        public ?Chart $monthlyChart = null,
        public ?Chart $routeComparisonChart = null,
        public bool $hasHistoricalData = false,
        public ?string $dataGapNote = null,
        public ?PredictionAccuracyDto $predictionAccuracy = null,
        public ?Chart $accuracyByHourChart = null,
    ) {
    }
}
