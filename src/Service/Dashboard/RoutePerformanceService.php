<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\RouteDetailDto;
use App\Dto\RouteMetricDto;
use App\Entity\Route;
use App\Repository\RealtimeRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\Repository\RouteRepository;

use function array_map;
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
    ) {
    }

    /**
     * Get route list with current performance metrics.
     *
     * @return list<RouteMetricDto>
     */
    public function getRouteListWithMetrics(string $search = '', string $sort = 'name'): array
    {
        // Get all routes
        $routes = $this->routeRepo->findAll();

        // Get current scores from Redis
        $scores     = $this->realtimeRepo->readScores();
        $scoresById = [];
        foreach ($scores['items'] as $score) {
            $scoresById[$score['route_id'] ?? ''] = $score;
        }

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
            $endDate   = new \DateTimeImmutable('today');
            $startDate = $endDate->modify('-30 days');
            $perf      = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

            $avgOnTime = 0.0;
            $count     = 0;
            foreach ($perf as $p) {
                if ($p->getOnTimePercentage() !== null) {
                    $avgOnTime += (float) $p->getOnTimePercentage();
                    ++$count;
                }
            }
            $avgOnTime = $count > 0 ? $avgOnTime / $count : 0.0;

            // Fall back to realtime score if no historical data
            $grade = $this->onTimePercentageToGrade($avgOnTime);
            if ($avgOnTime === 0.0 && $score !== null && isset($score['grade']) && $score['grade'] !== 'N/A') {
                // Use realtime grade and estimate percentage from grade
                $grade     = $score['grade'];
                $avgOnTime = $this->gradeToOnTimePercentage($grade);
            }

            $routeMetrics[] = new RouteMetricDto(
                routeId: $gtfsId,
                shortName: $route->getShortName(),
                longName: $route->getLongName(),
                grade: $grade,
                onTimePercentage: $avgOnTime,
                colour: $route->getColour(),
                activeVehicles: $score !== null ? ($score['vehicles'] ?? 0) : 0,
                trend: null,
                issue: null,
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
            stats: $this->buildStats($route, $startDate, $endDate),
        );
    }

    /**
     * Build 30-day performance trend chart with weather overlay.
     *
     * @return array<string, mixed>
     */
    private function buildPerformanceTrendChart(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        // Query performance data
        $performances = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

        // Build data arrays
        $dates   = [];
        $values  = [];
        $weather = [];

        foreach ($performances as $perf) {
            // Format date as "Sep 14" instead of "2025-09-14"
            $date     = $perf->getDate()->format('M j');
            $dates[]  = $date;
            $onTime   = $perf->getOnTimePercentage();
            $values[] = $onTime !== null ? (float) $onTime : null;

            // Get weather for this date
            $weatherObs = $perf->getWeatherObservation();
            $weather[]  = $weatherObs?->getWeatherCondition() ?? 'unknown';
        }

        // ECharts configuration
        return [
            'title' => [
                'text'      => '30-Day Performance Trend',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 16, 'fontWeight' => 'bold'],
            ],
            'tooltip' => [
                'trigger'   => 'axis',
                'formatter' => '{b}<br/>On-Time: {c}%<br/>Weather: {a}',
            ],
            'xAxis' => [
                'type'      => 'category',
                'data'      => $dates,
                'axisLabel' => ['rotate' => 45],
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'On-Time %',
                'min'  => 0,
                'max'  => 100,
            ],
            'series' => [
                [
                    'name'      => 'On-Time Performance',
                    'type'      => 'line',
                    'data'      => $values,
                    'smooth'    => true,
                    'lineStyle' => ['width' => 3],
                    'itemStyle' => ['color' => '#0284c7'],
                    'areaStyle' => ['color' => 'rgba(2, 132, 199, 0.1)'],
                ],
            ],
            'grid' => [
                'left'         => '3%',
                'right'        => '4%',
                'bottom'       => '15%',
                'containLabel' => true,
            ],
        ];
    }

    /**
     * Build weather impact comparison chart.
     *
     * @return array<string, mixed>
     */
    private function buildWeatherImpactChart(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        // Query performance by weather condition
        $qb = $this->performanceRepo->createQueryBuilder('p');
        $qb->select('w.weatherCondition', 'AVG(p.onTimePercentage) as avgPerformance', 'COUNT(p.id) as days')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('p.route = :route')
            ->andWhere('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('w.weatherCondition IS NOT NULL')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('route', $route)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('w.weatherCondition')
            ->orderBy('avgPerformance', 'DESC');

        $results = $qb->getQuery()->getResult();

        $conditions = [];
        $values     = [];
        $colors     = [];

        $colorMap = [
            'clear'         => '#fef3c7',
            'partly_cloudy' => '#e5e7eb',
            'cloudy'        => '#cbd5e1',
            'rain'          => '#dbeafe',
            'snow'          => '#ede9fe',
            'storm'         => '#1e293b',
        ];

        foreach ($results as $row) {
            $condition    = $row['weatherCondition'] ?? 'unknown';
            $conditions[] = ucfirst($condition);
            $values[]     = round((float) $row['avgPerformance'], 1);
            $colors[]     = $colorMap[$condition] ?? '#e5e7eb';
        }

        return [
            'title' => [
                'text'      => 'Performance by Weather Condition',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 16, 'fontWeight' => 'bold'],
            ],
            'tooltip' => [
                'trigger'     => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $conditions,
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'On-Time %',
                'min'  => 0,
                'max'  => 100,
            ],
            'series' => [
                [
                    'name' => 'On-Time Performance',
                    'type' => 'bar',
                    'data' => array_map(function ($value, $color) {
                        return ['value' => $value, 'itemStyle' => ['color' => $color]];
                    }, $values, $colors),
                    'label' => [
                        'show'      => true,
                        'position'  => 'top',
                        'formatter' => '{c}%',
                    ],
                ],
            ],
            'grid' => [
                'left'         => '3%',
                'right'        => '4%',
                'bottom'       => '3%',
                'containLabel' => true,
            ],
        ];
    }

    /**
     * Build time-of-day heatmap showing performance by day of week and time.
     *
     * @return array<string, mixed>
     */
    private function buildTimeOfDayHeatmap(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $days  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $hours = ['0-6', '6-9', '9-12', '12-15', '15-18', '18-21', '21-24'];

        // Query arrival_log data grouped by day of week and hour bucket
        // Use native SQL for performance (Doctrine DQL doesn't have good date functions)
        $conn = $this->performanceRepo->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                EXTRACT(DOW FROM predicted_at) as day_of_week,  -- 0=Sun, 1=Mon, ... 6=Sat
                CASE
                    WHEN EXTRACT(HOUR FROM predicted_at) < 6  THEN 0
                    WHEN EXTRACT(HOUR FROM predicted_at) < 9  THEN 1
                    WHEN EXTRACT(HOUR FROM predicted_at) < 12 THEN 2
                    WHEN EXTRACT(HOUR FROM predicted_at) < 15 THEN 3
                    WHEN EXTRACT(HOUR FROM predicted_at) < 18 THEN 4
                    WHEN EXTRACT(HOUR FROM predicted_at) < 21 THEN 5
                    ELSE 6
                END as hour_bucket,
                COUNT(*) as total,
                SUM(CASE WHEN delay_sec IS NOT NULL AND delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time,
                AVG(CASE WHEN delay_sec IS NOT NULL THEN delay_sec ELSE NULL END) as avg_delay
            FROM arrival_log
            WHERE route_id = :route_id
                AND predicted_at >= :start_date
                AND predicted_at < :end_date
                AND delay_sec IS NOT NULL
            GROUP BY day_of_week, hour_bucket
            ORDER BY day_of_week, hour_bucket
        SQL;

        $results = $conn->executeQuery($sql, [
            'route_id'   => $route->getId(),
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date'   => $endDate->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        // Build heatmap data array
        $data      = [];
        $resultMap = [];

        // Index results by [day][hour] for O(1) lookup
        foreach ($results as $row) {
            $dow = (int) $row['day_of_week'];
            // Convert PostgreSQL DOW (0=Sun, 1=Mon, ..., 6=Sat) to our array index (0=Mon, ..., 6=Sun)
            $dayIndex         = $dow === 0 ? 6 : $dow - 1;
            $hourIndex        = (int) $row['hour_bucket'];
            $total            = (int) $row['total'];
            $onTime           = (int) $row['on_time'];
            $onTimePercentage = $total > 0 ? round(($onTime / $total) * 100, 1) : 0;

            $resultMap[$dayIndex][$hourIndex] = $onTimePercentage;
        }

        // Fill in heatmap data (7 days × 7 hour buckets)
        for ($d = 0; $d < count($days); ++$d) {
            for ($h = 0; $h < count($hours); ++$h) {
                // Use real data if available, otherwise use null (will show as gray in chart)
                $value  = $resultMap[$d][$h] ?? null;
                $data[] = [$d, $h, $value];
            }
        }

        return [
            'title' => [
                'text'      => 'Performance by Day & Time',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 16, 'fontWeight' => 'bold'],
            ],
            'tooltip' => [
                'position'  => 'top',
                'formatter' => 'Day: {b}<br/>Time: {c0}<br/>Performance: {c2}%',
            ],
            'xAxis' => [
                'type'      => 'category',
                'data'      => $days,
                'splitArea' => ['show' => true],
            ],
            'yAxis' => [
                'type'      => 'category',
                'data'      => $hours,
                'splitArea' => ['show' => true],
            ],
            'visualMap' => [
                'min'        => 40,
                'max'        => 100,
                'calculable' => true,
                'orient'     => 'horizontal',
                'left'       => 'center',
                'bottom'     => '0%',
                'inRange'    => [
                    'color' => ['#dc2626', '#f97316', '#fbbf24', '#84cc16', '#10b981'],
                ],
            ],
            'series' => [
                [
                    'name'  => 'Performance',
                    'type'  => 'heatmap',
                    'data'  => $data,
                    'label' => [
                        'show'     => true,
                        'fontSize' => 10,
                        // Formatter will be added by JS controller
                    ],
                ],
            ],
            'grid' => [
                'height' => '60%',
                'top'    => '10%',
            ],
        ];
    }

    /**
     * Build summary statistics.
     *
     * @return array<string, mixed>
     */
    private function buildStats(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $performances = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

        $totalDays   = 0;
        $totalOnTime = 0.0;
        $bestDay     = null;
        $worstDay    = null;
        $bestPerf    = 0.0;
        $worstPerf   = 100.0;

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
        }

        $avgPerformance = $totalDays > 0 ? $totalOnTime / $totalDays : 0.0;

        return [
            'totalDays'        => $totalDays,
            'avgPerformance'   => round($avgPerformance, 1),
            'bestDay'          => $bestDay?->format('M j'),
            'bestPerformance'  => round($bestPerf, 1),
            'worstDay'         => $worstDay?->format('M j'),
            'worstPerformance' => round($worstPerf, 1),
            'grade'            => $this->onTimePercentageToGrade($avgPerformance),
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
}
