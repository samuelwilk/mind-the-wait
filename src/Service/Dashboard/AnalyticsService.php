<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\Analytics\AnalyticsPageDto;
use App\Dto\Analytics\AnalyticsSummaryDto;
use App\Dto\Analytics\DateRangeDto;
use App\Dto\Analytics\PredictionAccuracyDto;
use App\Repository\ArrivalLogRepository;
use App\Repository\BunchingIncidentRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\ValueObject\Chart\Chart;
use App\ValueObject\Chart\ChartBuilder;
use Psr\Cache\CacheItemPoolInterface;

use function count;

/**
 * Service for analytics dashboard data aggregation.
 *
 * Provides comprehensive analytics data including:
 * - Route comparison metrics
 * - Vehicle performance leaderboards
 * - Time-based trends (hourly, daily, monthly)
 * - Summary statistics
 */
final readonly class AnalyticsService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private RoutePerformanceDailyRepository $performanceRepo,
        private ArrivalLogRepository $arrivalRepo,
        private BunchingIncidentRepository $bunchingRepo,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Get complete analytics page data for a date range.
     *
     * @param DateRangeDto   $dateRange Date range for analysis
     * @param list<int>|null $routeIds  Optional route IDs for comparison (null = all routes)
     */
    public function getAnalyticsPageData(
        DateRangeDto $dateRange,
        ?array $routeIds = null,
    ): AnalyticsPageDto {
        $cacheKey = 'analytics_page_'.md5($dateRange->startDate->format('Y-m-d').'_'.$dateRange->endDate->format('Y-m-d').'_'.json_encode($routeIds));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        // Build summary
        $summary = $this->buildSummary($dateRange);

        // Get route comparisons (use top 10 by default if no specific routes selected)
        $routeComparisons = [];
        if ($routeIds !== null && count($routeIds) > 0) {
            $routeComparisons = $this->performanceRepo->findRouteComparisonData(
                $routeIds,
                $dateRange->startDate,
                $dateRange->endDate,
            );
        }

        // Get vehicle performance leaderboards
        $topVehicles = $this->arrivalRepo->findTopPerformingVehicles(
            $dateRange->startDate,
            $dateRange->endDate,
            10,
        );

        $worstVehicles = $this->arrivalRepo->findWorstPerformingVehicles(
            $dateRange->startDate,
            $dateRange->endDate,
            10,
        );

        // Get time-based trends
        $dayOfWeekTrends = $this->performanceRepo->findDayOfWeekTrends(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $hourlyTrends = $this->arrivalRepo->findHourlyTrends(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $monthlyTrends = $this->performanceRepo->findMonthlyTrends(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        // Build charts
        $dayOfWeekChart       = $this->buildDayOfWeekChart($dayOfWeekTrends);
        $hourlyChart          = $this->buildHourlyChart($hourlyTrends);
        $monthlyChart         = $this->buildMonthlyChart($monthlyTrends);
        $routeComparisonChart = count($routeComparisons) > 0
            ? $this->buildRouteComparisonChart($routeComparisons)
            : null;

        // Prediction accuracy
        $predictionAccuracy      = $this->buildPredictionAccuracy($dateRange);
        $accuracyByHourChart     = $this->buildAccuracyByHourChart($predictionAccuracy?->byHour ?? []);
        $accuracyByDistanceChart = $this->buildAccuracyByDistanceChart($predictionAccuracy?->byStopsAway ?? []);

        // Data gap note — show when range overlaps the collection interruption
        $gapStart    = new \DateTimeImmutable('2026-01-05');
        $gapEnd      = new \DateTimeImmutable('2026-02-07');
        $dataGapNote = ($dateRange->startDate < $gapEnd && $dateRange->endDate > $gapStart)
            ? 'Note: Data collection was interrupted from Jan 5 - Feb 7, 2026.'
            : null;

        $result = new AnalyticsPageDto(
            dateRange: $dateRange,
            summary: $summary,
            routeComparisons: $routeComparisons,
            topVehicles: $topVehicles,
            worstVehicles: $worstVehicles,
            dayOfWeekTrends: $dayOfWeekTrends,
            hourlyTrends: $hourlyTrends,
            monthlyTrends: $monthlyTrends,
            dayOfWeekChart: $dayOfWeekChart,
            hourlyChart: $hourlyChart,
            monthlyChart: $monthlyChart,
            routeComparisonChart: $routeComparisonChart,
            hasHistoricalData: false,
            dataGapNote: $dataGapNote,
            predictionAccuracy: $predictionAccuracy,
            accuracyByHourChart: $accuracyByHourChart,
            accuracyByDistanceChart: $accuracyByDistanceChart,
        );

        $item->set($result);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $result;
    }

    /**
     * Get all routes that have performance data in the date range.
     *
     * @return list<array{id: int, short_name: string, long_name: string}>
     */
    public function getAvailableRoutes(DateRangeDto $dateRange): array
    {
        $cacheKey = 'analytics_routes_'.md5($dateRange->startDate->format('Y-m-d').'_'.$dateRange->endDate->format('Y-m-d'));
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $routes = $this->performanceRepo->findRoutesWithData(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $item->set($routes);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $routes;
    }

    /**
     * Build summary statistics for the date range.
     */
    private function buildSummary(DateRangeDto $dateRange): AnalyticsSummaryDto
    {
        $stats = $this->performanceRepo->getAnalyticsSummary(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $vehiclesTracked = $this->arrivalRepo->countDistinctVehicles(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $bunchingIncidents = $this->bunchingRepo->countByDateRange(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        return new AnalyticsSummaryDto(
            totalPredictions: $stats['total_predictions'],
            avgOnTimePercentage: $stats['avg_on_time'],
            avgDelaySec: $stats['avg_delay'],
            daysWithData: $stats['days_with_data'],
            routesTracked: $stats['routes_tracked'],
            vehiclesTracked: $vehiclesTracked,
            bunchingIncidents: $bunchingIncidents,
        );
    }

    /**
     * Build day-of-week performance bar chart.
     *
     * @param list<\App\Dto\Analytics\DayOfWeekTrendDto> $trends
     */
    private function buildDayOfWeekChart(array $trends): ?Chart
    {
        if (count($trends) === 0) {
            return null;
        }

        $days = array_map(fn ($t) => $t->getShortDayName(), $trends);
        $data = array_map(fn ($t) => $t->avgOnTimePercentage, $trends);

        // Color weekends differently
        $colors = array_map(
            fn ($t) => $t->isWeekend() ? '#9CA3AF' : '#3B82F6',
            $trends
        );

        return ChartBuilder::bar()
            ->categoryXAxis($days)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->addSeries('On-Time %', $data, ['itemStyle' => ['color' => '#3B82F6']])
            ->build();
    }

    /**
     * Build hourly performance line chart.
     *
     * @param list<\App\Dto\Analytics\HourlyTrendDto> $trends
     */
    private function buildHourlyChart(array $trends): ?Chart
    {
        if (count($trends) === 0) {
            return null;
        }

        $hours = array_map(fn ($t) => $t->getHourLabel(), $trends);
        $data  = array_map(fn ($t) => $t->avgOnTimePercentage, $trends);

        return ChartBuilder::line()
            ->categoryXAxis($hours)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->addSeries('On-Time %', $data, ['itemStyle' => ['color' => '#10B981'], 'smooth' => true, 'areaStyle' => []])
            ->build();
    }

    /**
     * Build monthly trend line chart.
     *
     * @param list<\App\Dto\Analytics\MonthlyTrendDto> $trends
     */
    private function buildMonthlyChart(array $trends): ?Chart
    {
        if (count($trends) === 0) {
            return null;
        }

        $months = array_map(fn ($t) => $t->getMonthLabel(), $trends);
        $data   = array_map(fn ($t) => $t->avgOnTimePercentage, $trends);

        return ChartBuilder::line()
            ->categoryXAxis($months)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->addSeries('System Average', $data, ['itemStyle' => ['color' => '#6366F1'], 'smooth' => true, 'areaStyle' => []])
            ->build();
    }

    /**
     * Build route comparison bar chart.
     *
     * @param list<\App\Dto\Analytics\RouteComparisonDto> $comparisons
     */
    private function buildRouteComparisonChart(array $comparisons): Chart
    {
        $routes = array_map(fn ($c) => 'Route '.$c->shortName, $comparisons);
        $data   = array_map(fn ($c) => $c->avgOnTimePercentage, $comparisons);
        $colors = array_map(fn ($c) => $c->colour ?? '#3B82F6', $comparisons);

        return ChartBuilder::bar()
            ->categoryXAxis($routes)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->addSeries('On-Time %', $data, ['itemStyle' => ['color' => '#3B82F6']])
            ->build();
    }

    private function buildPredictionAccuracy(DateRangeDto $dateRange): ?PredictionAccuracyDto
    {
        $summary = $this->arrivalRepo->findPredictionAccuracySummary(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        if ($summary === null) {
            return null;
        }

        $byConfidence = $this->arrivalRepo->findPredictionAccuracyByConfidence(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $byHour = $this->arrivalRepo->findPredictionAccuracyByHour(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        $byStopsAway = $this->arrivalRepo->findAccuracyByStopsAway(
            $dateRange->startDate,
            $dateRange->endDate,
        );

        return new PredictionAccuracyDto(
            maeSeconds: $summary['mae'],
            biasSeconds: $summary['bias'],
            sampleSize: $summary['sample_size'],
            within1Min: $summary['within_1_min'],
            within2Min: $summary['within_2_min'],
            within3Min: $summary['within_3_min'],
            within5Min: $summary['within_5_min'],
            byConfidence: $byConfidence,
            byHour: $byHour,
            byStopsAway: $byStopsAway,
        );
    }

    /**
     * @param list<\App\Dto\Analytics\HourlyAccuracyDto> $hourlyAccuracy
     */
    private function buildAccuracyByHourChart(array $hourlyAccuracy): ?Chart
    {
        if (count($hourlyAccuracy) === 0) {
            return null;
        }

        $hours   = array_map(fn ($h) => $h->getHourLabel(), $hourlyAccuracy);
        $maeData = array_map(fn ($h) => $h->getMaeMinutes(), $hourlyAccuracy);

        return ChartBuilder::bar()
            ->categoryXAxis($hours)
            ->valueYAxis('MAE (minutes)', min: 0)
            ->addSeries('Avg Error', $maeData, ['itemStyle' => ['color' => '#F59E0B']])
            ->build();
    }

    /**
     * @param list<array{stops_away: int, mae: float, within_3_min: float, sample_size: int}> $byStopsAway
     */
    private function buildAccuracyByDistanceChart(array $byStopsAway): ?Chart
    {
        if (count($byStopsAway) === 0) {
            return null;
        }

        $labels  = array_map(fn ($s) => $s['stops_away'].' stop'.($s['stops_away'] !== 1 ? 's' : ''), $byStopsAway);
        $maeData = array_map(fn ($s) => round($s['mae'] / 60, 1), $byStopsAway);

        return ChartBuilder::line()
            ->categoryXAxis($labels)
            ->valueYAxis('MAE (minutes)', min: 0)
            ->addSeries('Prediction Error', $maeData, ['itemStyle' => ['color' => '#EC4899'], 'smooth' => true, 'areaStyle' => []])
            ->build();
    }
}
