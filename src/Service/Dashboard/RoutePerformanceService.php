<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\RouteDetailDto;
use App\Dto\RouteMetricDto;
use App\Entity\City;
use App\Entity\Route;
use App\Repository\ArrivalLogRepository;
use App\Repository\RealtimeRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\Repository\RouteRepository;
use App\ValueObject\Chart\Chart;
use App\ValueObject\Chart\RoutePerformanceChartPreset;

use function array_slice;
use function count;
use function usort;

/**
 * Service for route performance analysis and chart generation.
 */
final readonly class RoutePerformanceService
{
    public function __construct(
        private RouteRepository $routeRepo,
        private RoutePerformanceDailyRepository $performanceRepo,
        private RealtimeRepository $realtimeRepo,
        private ArrivalLogRepository $arrivalLogRepo,
    ) {
    }

    /**
     * Get route list with current performance metrics.
     *
     * @param City|null $city   Optional city filter
     * @param string    $search Search query
     * @param string    $sort   Sort field
     *
     * @return list<RouteMetricDto>
     */
    public function getRouteListWithMetrics(?City $city = null, string $search = '', string $sort = 'name'): array
    {
        // Get routes, optionally filtered by city
        if ($city !== null) {
            $routes = $this->routeRepo->findBy(['city' => $city]);
        } else {
            $routes = $this->routeRepo->findAll();
        }

        // Get current scores from Redis
        $scores = $this->realtimeRepo->readScores();

        // Aggregate vehicle counts by route (sum across all directions)
        $vehicleCountByRoute = [];
        foreach ($scores['items'] as $score) {
            $routeId  = $score['route_id'] ?? '';
            $vehicles = $score['vehicles'] ?? 0;

            if (!isset($vehicleCountByRoute[$routeId])) {
                $vehicleCountByRoute[$routeId] = 0;
            }
            $vehicleCountByRoute[$routeId] += $vehicles;
        }

        // Keep the last score per route for grade information
        $scoresById = [];
        foreach ($scores['items'] as $score) {
            $scoresById[$score['route_id'] ?? ''] = $score;
        }

        // Get system baseline for Bayesian adjustment (cached for performance)
        $endDate      = new \DateTimeImmutable('today');
        $startDate    = $endDate->modify('-30 days');
        $systemMedian = $this->performanceRepo->getSystemMedianPerformance($startDate, $endDate);

        // Build route metrics
        $routeMetrics = [];
        foreach ($routes as $route) {
            $gtfsId = $route->getGtfsId();
            $score  = $scoresById[$gtfsId] ?? null;

            // Skip if search doesn't match
            if ($search !== '' && !$this->matchesSearch($route, $search)) {
                continue;
            }

            // Get 30-day average for more stable metric
            $perf = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

            $avgOnTime  = 0.0;
            $daysOfData = 0;
            foreach ($perf as $p) {
                if ($p->getOnTimePercentage() !== null) {
                    $avgOnTime += (float) $p->getOnTimePercentage();
                    ++$daysOfData;
                }
            }
            $rawAverage = $daysOfData > 0 ? $avgOnTime / $daysOfData : 0.0;

            // Apply Bayesian adjustment for routes with limited data
            $adjusted        = $this->calculateAdjustedPerformance($rawAverage, $daysOfData, $systemMedian);
            $adjustedOnTime  = $adjusted['performance'];
            $confidenceLevel = $adjusted['confidence'];

            // Fall back to realtime score if no historical data
            $grade = $this->onTimePercentageToGrade($adjustedOnTime);
            if ($adjustedOnTime === 0.0 && $score !== null && isset($score['grade']) && $score['grade'] !== 'N/A') {
                // Use realtime grade and estimate percentage from grade
                $grade           = $score['grade'];
                $adjustedOnTime  = $this->gradeToOnTimePercentage($grade);
                $confidenceLevel = 'realtime';
            }

            $routeMetrics[] = new RouteMetricDto(
                routeId: $gtfsId,
                shortName: $route->getShortName(),
                longName: $route->getLongName(),
                grade: $grade,
                onTimePercentage: $adjustedOnTime,
                colour: $route->getColour(),
                activeVehicles: $vehicleCountByRoute[$gtfsId] ?? 0,
                trend: null,
                issue: null,
                daysOfData: $daysOfData,
                confidenceLevel: $confidenceLevel,
            );
        }

        // Sort routes
        usort($routeMetrics, function ($a, $b) use ($sort) {
            return match ($sort) {
                'grade'       => $this->compareGrades($a->grade, $b->grade),
                'performance' => $b->onTimePercentage <=> $a->onTimePercentage,
                default       => $a->shortName        <=> $b->shortName, // name (natural sort by number)
            };
        });

        return $routeMetrics;
    }

    /**
     * Get comprehensive route detail data for charts.
     */
    public function getRouteDetail(Route $route): RouteDetailDto
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        // Generate chart configurations
        return new RouteDetailDto(
            performanceTrendChart: $this->buildPerformanceTrendChart($route, $startDate, $endDate),
            weatherImpactChart: $this->buildWeatherImpactChart($route, $startDate, $endDate),
            timeOfDayHeatmap: $this->buildTimeOfDayHeatmap($route, $startDate, $endDate),
            stopReliabilityChart: $this->buildStopReliabilityChart($route, $startDate, $endDate),
            stats: $this->buildStats($route, $startDate, $endDate),
        );
    }

    /**
     * Build 30-day performance trend chart with weather overlay.
     */
    private function buildPerformanceTrendChart(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): Chart
    {
        // Query performance data
        $performances = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

        // Build data arrays
        $dates  = [];
        $values = [];

        foreach ($performances as $perf) {
            $dates[]  = $perf->getDate()->format('M j');
            $onTime   = $perf->getOnTimePercentage();
            $values[] = $onTime !== null ? (float) $onTime : null;
        }

        return RoutePerformanceChartPreset::performanceTrend($dates, $values);
    }

    /**
     * Build weather impact comparison chart.
     */
    private function buildWeatherImpactChart(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): Chart
    {
        // Query performance by weather condition from repository
        $results = $this->performanceRepo->findWeatherImpactByRoute($route->getId(), $startDate, $endDate);

        $conditions = [];
        $values     = [];
        $colors     = [];

        foreach ($results as $dto) {
            $conditions[] = $dto->weatherCondition->label();
            $values[]     = $dto->avgPerformance;
            $colors[]     = $dto->weatherCondition->chartColor();
        }

        return RoutePerformanceChartPreset::weatherImpact($conditions, $values, $colors);
    }

    /**
     * Build time-of-day heatmap showing performance by day of week and time.
     */
    private function buildTimeOfDayHeatmap(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): Chart
    {
        $days  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $hours = ['0-6', '6-9', '9-12', '12-15', '15-18', '18-21', '21-24'];

        $data = [];
        /** @var array<int, array<int, float|null>> $resultMap */
        $resultMap = [];

        $buckets = $this->arrivalLogRepo->findHeatmapBuckets(
            routeId: $route->getId(),
            start: $startDate,
            end: $endDate,
        );

        foreach ($buckets as $bucket) {
            $resultMap[$bucket->dayIndex][$bucket->hourIndex] = $bucket->onTimePercentage;
        }

        // Fill in heatmap data (7 days × 7 hour buckets)
        for ($d = 0; $d < count($days); ++$d) {
            for ($h = 0; $h < count($hours); ++$h) {
                // Use real data if available, otherwise use null (will show as gray in chart)
                $value  = $resultMap[$d][$h] ?? null;
                $data[] = [$d, $h, $value];
            }
        }

        return RoutePerformanceChartPreset::timeOfDayHeatmap($days, $hours, $data);
    }

    /**
     * Build stop-level reliability chart showing which stops cause delays.
     *
     * Returns null if no stop data available (< 10 arrivals per stop).
     */
    private function buildStopReliabilityChart(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): ?Chart
    {
        $stopData = $this->arrivalLogRepo->findStopReliabilityData($route->getId(), $startDate, $endDate);

        if (count($stopData) === 0) {
            return null;
        }

        // Limit to top 15 worst-performing stops for readability
        $topStops = array_slice($stopData, 0, 15);

        $stopNames = [];
        $delays    = [];
        $colors    = [];

        foreach ($topStops as $stop) {
            $stopNames[] = $stop->stopName;
            $delays[]    = $stop->avgDelaySec;

            // Color based on delay severity
            $colors[] = match (true) {
                $stop->avgDelaySec <= -180 => '#3b82f6',  // Blue (very early)
                $stop->avgDelaySec < -60   => '#60a5fa',  // Light blue (early)
                $stop->avgDelaySec <= 60   => '#10b981',  // Green (on-time)
                $stop->avgDelaySec <= 180  => '#f59e0b',  // Yellow (slightly late)
                $stop->avgDelaySec <= 300  => '#ef4444',  // Red (late)
                default                    => '#dc2626',  // Dark red (very late)
            };
        }

        return RoutePerformanceChartPreset::stopReliability($stopNames, $delays, $colors);
    }

    /**
     * Build summary statistics.
     *
     * @return array<string, mixed>
     */
    private function buildStats(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $performances = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

        $totalDays            = 0;
        $totalOnTime          = 0.0;
        $bestDay              = null;
        $worstDay             = null;
        $bestPerf             = 0.0;
        $worstPerf            = 100.0;
        $scheduleRealismCount = 0;
        $totalScheduleRealism = 0.0;

        foreach ($performances as $perf) {
            $onTime = $perf->getOnTimePercentage();
            if ($onTime === null) {
                continue;
            }

            // Cast to float (Doctrine returns DECIMAL as string)
            $onTime = (float) $onTime;

            ++$totalDays;
            $totalOnTime += $onTime;

            if ($onTime > $bestPerf) {
                $bestPerf = $onTime;
                $bestDay  = $perf->getDate();
            }
            if ($onTime < $worstPerf) {
                $worstPerf = $onTime;
                $worstDay  = $perf->getDate();
            }

            // Aggregate schedule realism ratio
            $ratio = $perf->getScheduleRealismRatio();
            if ($ratio !== null) {
                $totalScheduleRealism += (float) $ratio;
                ++$scheduleRealismCount;
            }
        }

        $avgPerformance       = $totalDays            > 0 ? $totalOnTime          / $totalDays : 0.0;
        $avgScheduleRealism   = $scheduleRealismCount > 0 ? $totalScheduleRealism / $scheduleRealismCount : null;
        $scheduleRealismGrade = $avgScheduleRealism !== null
            ? \App\Enum\ScheduleRealismGrade::fromRatio($avgScheduleRealism)
            : \App\Enum\ScheduleRealismGrade::INSUFFICIENT_DATA;

        return [
            'totalDays'            => $totalDays,
            'avgPerformance'       => round($avgPerformance, 1),
            'bestDay'              => $bestDay?->format('M j'),
            'bestPerformance'      => round($bestPerf, 1),
            'worstDay'             => $worstDay?->format('M j'),
            'worstPerformance'     => round($worstPerf, 1),
            'grade'                => $this->onTimePercentageToGrade($avgPerformance),
            'scheduleRealismRatio' => $avgScheduleRealism !== null ? round($avgScheduleRealism, 2) : null,
            'scheduleRealismGrade' => $scheduleRealismGrade,
            'systemComparison'     => $this->performanceRepo->getRoutePerformanceRanking($route->getId(), $startDate, $endDate),
        ];
    }

    /**
     * Check if route matches search query.
     */
    private function matchesSearch(Route $route, string $search): bool
    {
        $search = strtolower($search);

        return str_contains(strtolower($route->getShortName()), $search)
            || str_contains(strtolower($route->getLongName()), $search);
    }

    /**
     * Compare two grades for sorting (A > B > C > D > F).
     */
    private function compareGrades(string $gradeA, string $gradeB): int
    {
        $order = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1, 'N/A' => 0];

        return ($order[$gradeB] ?? 0) <=> ($order[$gradeA] ?? 0);
    }

    /**
     * Convert on-time percentage to letter grade.
     */
    private function onTimePercentageToGrade(float $onTimePercentage): string
    {
        // Return N/A when there's no data
        if ($onTimePercentage === 0.0) {
            return 'N/A';
        }

        return match (true) {
            $onTimePercentage >= 90 => 'A',
            $onTimePercentage >= 80 => 'B',
            $onTimePercentage >= 70 => 'C',
            $onTimePercentage >= 60 => 'D',
            default                 => 'F',
        };
    }

    /**
     * Convert letter grade to estimated on-time percentage (for display).
     */
    private function gradeToOnTimePercentage(string $grade): float
    {
        return match ($grade) {
            'A'     => 95.0,
            'B'     => 85.0,
            'C'     => 75.0,
            'D'     => 65.0,
            'F'     => 50.0,
            default => 0.0,
        };
    }

    /**
     * Calculate adjusted performance using Bayesian shrinkage.
     *
     * Routes with limited data are adjusted toward the system median
     * to prevent small sample size bias.
     *
     * @param float $rawAverage   Raw average on-time percentage
     * @param int   $daysOfData   Number of days of data
     * @param float $systemMedian System-wide median performance
     *
     * @return array{performance: float, confidence: string}
     */
    private function calculateAdjustedPerformance(
        float $rawAverage,
        int $daysOfData,
        float $systemMedian,
    ): array {
        // Determine confidence level
        $confidenceLevel = match (true) {
            $daysOfData >= 10 => 'high',
            $daysOfData >= 5  => 'medium',
            $daysOfData > 0   => 'low',
            default           => 'none',
        };

        // No adjustment needed for high-confidence routes
        if ($daysOfData >= 10) {
            return [
                'performance' => $rawAverage,
                'confidence'  => $confidenceLevel,
            ];
        }

        // No data - return system median
        if ($daysOfData === 0) {
            return [
                'performance' => 0.0,
                'confidence'  => 'none',
            ];
        }

        // Apply Bayesian shrinkage for low-sample routes
        // Confidence increases linearly from 0 (no data) to 1 (10+ days)
        $confidence = min(1.0, $daysOfData / 10.0);

        // Weighted average: (raw × confidence) + (baseline × (1 - confidence))
        $adjusted = ($rawAverage * $confidence) + ($systemMedian * (1 - $confidence));

        return [
            'performance' => round($adjusted, 1),
            'confidence'  => $confidenceLevel,
        ];
    }
}
