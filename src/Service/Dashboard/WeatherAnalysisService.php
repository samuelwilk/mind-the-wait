<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\WeatherImpactDto;
use App\Repository\BunchingIncidentRepository;
use App\Repository\RoutePerformanceDailyRepository;

use function array_flip;
use function array_map;
use function array_slice;
use function count;
use function usort;

/**
 * Service for weather impact analysis and insight generation.
 *
 * Generates comprehensive weather impact insights including:
 * - Winter operations (clear vs snow comparison)
 * - Temperature threshold analysis
 * - Weather impact matrix (all routes × all conditions)
 * - Bunching incidents by weather
 */
final readonly class WeatherAnalysisService
{
    public function __construct(
        private RoutePerformanceDailyRepository $performanceRepo,
        private BunchingIncidentRepository $bunchingRepo,
        private InsightGeneratorService $insightGenerator,
    ) {
    }

    /**
     * Get complete weather impact insights for the weather impact page.
     */
    public function getWeatherImpactInsights(): WeatherImpactDto
    {
        // Build statistics first (used by AI generation)
        $winterStats   = $this->buildWinterOperationsStats();
        $tempStats     = $this->buildTemperatureThresholdStats();
        $impactStats   = $this->buildWeatherImpactStats();
        $bunchingStats = $this->buildBunchingByWeatherStats();

        return new WeatherImpactDto(
            winterOperationsChart: $this->buildWinterOperationsChart(),
            winterOperationsStats: $winterStats,
            winterOperationsNarrative: $this->insightGenerator->generateWinterOperationsInsight($winterStats),
            temperatureThresholdChart: $this->buildTemperatureThresholdChart(),
            temperatureThresholdStats: $tempStats,
            temperatureThresholdNarrative: $this->insightGenerator->generateTemperatureThresholdInsight($tempStats),
            weatherImpactMatrix: $this->buildWeatherImpactMatrix(),
            weatherImpactStats: $impactStats,
            weatherImpactNarrative: $this->insightGenerator->generateWeatherImpactMatrixInsight($impactStats),
            bunchingByWeatherChart: $this->buildBunchingByWeatherChart(),
            bunchingByWeatherStats: $bunchingStats,
            bunchingByWeatherNarrative: $this->insightGenerator->generateBunchingByWeatherInsight($bunchingStats),
            keyTakeaway: $this->insightGenerator->generateWeatherImpactKeyTakeaway(),
        );
    }

    /**
     * Build winter operations chart comparing clear vs snow performance.
     *
     * Shows top 10 routes most affected by snow.
     *
     * @return array<string, mixed>
     */
    private function buildWinterOperationsChart(): array
    {
        // Query 1: Get clear weather performance
        $qb           = $this->performanceRepo->createQueryBuilder('p');
        $clearResults = $qb->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition = :clear')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('clear', 'clear')
            ->groupBy('r.id', 'r.shortName', 'r.longName')
            ->having('COUNT(p.id) >= 3')
            ->getQuery()
            ->getResult();

        // Query 2: Get snow weather performance
        $qb2         = $this->performanceRepo->createQueryBuilder('p');
        $snowResults = $qb2->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition = :snow')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('snow', 'snow')
            ->groupBy('r.id', 'r.shortName', 'r.longName')
            ->having('COUNT(p.id) >= 3')
            ->getQuery()
            ->getResult();

        // Combine results
        $clearByRoute = [];
        foreach ($clearResults as $row) {
            $clearByRoute[(int) $row['id']] = [
                'shortName' => $row['shortName'],
                'longName'  => $row['longName'],
                'perf'      => (float) $row['avgPerf'],
            ];
        }

        $snowByRoute = [];
        foreach ($snowResults as $row) {
            $snowByRoute[(int) $row['id']] = [
                'shortName' => $row['shortName'],
                'longName'  => $row['longName'],
                'perf'      => (float) $row['avgPerf'],
            ];
        }

        // Find routes that exist in both
        $combined = [];
        foreach ($clearByRoute as $routeId => $clearData) {
            if (isset($snowByRoute[$routeId])) {
                $combined[] = [
                    'shortName' => $clearData['shortName'],
                    'longName'  => $clearData['longName'],
                    'clearPerf' => $clearData['perf'],
                    'snowPerf'  => $snowByRoute[$routeId]['perf'],
                    'delta'     => $clearData['perf'] - $snowByRoute[$routeId]['perf'],
                ];
            }
        }

        // Sort by delta (biggest impact first)
        usort($combined, fn ($a, $b) => $b['delta'] <=> $a['delta']);

        // Take top 10
        $results = array_slice($combined, 0, 10);

        // Build chart data
        $routes      = [];
        $clearData   = [];
        $snowData    = [];
        $deltaLabels = [];

        foreach ($results as $row) {
            $routes[]      = 'Route '.$row['shortName'];
            $clearData[]   = round($row['clearPerf'], 1);
            $snowData[]    = round($row['snowPerf'], 1);
            $deltaLabels[] = round($row['delta'], 1);
        }

        return [
            'title' => [
                'text'      => 'Winter Operations: Clear vs Snow Performance',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
            ],
            'tooltip' => [
                'trigger'     => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'legend' => [
                'data'   => ['Clear Weather', 'Snow'],
                'bottom' => 0,
            ],
            'xAxis' => [
                'type'      => 'category',
                'data'      => $routes,
                'axisLabel' => ['rotate' => 45, 'interval' => 0],
            ],
            'yAxis' => [
                'type'          => 'value',
                'name'          => 'On-Time %',
                'nameLocation'  => 'middle',
                'nameGap'       => 40,
                'nameTextStyle' => ['fontSize' => 11],
                'min'           => 0,
                'max'           => 100,
            ],
            'series' => [
                [
                    'name'      => 'Clear Weather',
                    'type'      => 'bar',
                    'data'      => $clearData,
                    'itemStyle' => ['color' => '#fef3c7', 'borderColor' => '#f59e0b', 'borderWidth' => 1],
                    'label'     => [
                        'show'      => true,
                        'position'  => 'top',
                        'formatter' => '{c}%',
                        'fontSize'  => 11,
                    ],
                ],
                [
                    'name'      => 'Snow',
                    'type'      => 'bar',
                    'data'      => $snowData,
                    'itemStyle' => ['color' => '#ede9fe', 'borderColor' => '#8b5cf6', 'borderWidth' => 1],
                    'label'     => [
                        'show'      => true,
                        'position'  => 'top',
                        'formatter' => '{c}%',
                        'fontSize'  => 11,
                    ],
                ],
            ],
            'grid' => [
                'left'         => '30',
                'right'        => '4%',
                'bottom'       => '15%',
                'containLabel' => true,
            ],
        ];
    }

    /**
     * Build statistics for winter operations story card.
     *
     * @return array<string, mixed>
     */
    private function buildWinterOperationsStats(): array
    {
        // Reuse the chart data to get the worst route
        $chartData = $this->buildWinterOperationsChart();

        // The chart is already sorted by delta, so first route is worst
        if (empty($chartData['xAxis']['data'])) {
            return [
                'worstRoute'      => 'N/A',
                'clearPerf'       => 0.0,
                'snowPerf'        => 0.0,
                'performanceDrop' => 0.0,
            ];
        }

        $worstRoute = $chartData['xAxis']['data'][0]     ?? 'N/A';
        $clearPerf  = $chartData['series'][0]['data'][0] ?? 0.0;
        $snowPerf   = $chartData['series'][1]['data'][0] ?? 0.0;

        return [
            'worstRoute'      => $worstRoute,
            'clearPerf'       => $clearPerf,
            'snowPerf'        => $snowPerf,
            'performanceDrop' => round($clearPerf - $snowPerf, 1),
        ];
    }

    /**
     * Build temperature threshold analysis chart.
     *
     * Shows performance by temperature bucket with trend line.
     *
     * @return array<string, mixed>
     */
    private function buildTemperatureThresholdChart(): array
    {
        // Use native SQL since DQL doesn't support FLOOR
        $conn = $this->performanceRepo->getEntityManager()->getConnection();

        $sql = '
            SELECT
                FLOOR(w.temperature_celsius / 5) * 5 as temp_bucket,
                AVG(p.on_time_percentage) as avg_performance,
                COUNT(p.id) as observation_count
            FROM route_performance_daily p
            LEFT JOIN weather_observation w ON p.weather_observation_id = w.id
            WHERE w.temperature_celsius IS NOT NULL
                AND p.on_time_percentage IS NOT NULL
            GROUP BY temp_bucket
            ORDER BY temp_bucket ASC
        ';

        $results = $conn->executeQuery($sql)->fetchAllAssociative();

        // Build scatter data
        $scatterData = [];
        $lineData    = [];
        $xAxisData   = [];

        foreach ($results as $row) {
            $temp  = (int) $row['temp_bucket'];
            $perf  = round((float) $row['avg_performance'], 1);
            $count = (int) $row['observation_count'];

            // Scatter point: [temperature, performance]
            // Symbol size based on observation count
            $scatterData[] = [
                'value'      => [$temp, $perf],
                'symbolSize' => min(5 + ($count / 2), 30), // Scale with observations, cap at 30
            ];

            $xAxisData[] = $temp;
            $lineData[]  = $perf; // Simple line connecting points
        }

        return [
            'title' => [
                'text'      => 'Temperature Threshold Analysis',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
            ],
            'tooltip' => [
                'trigger' => 'item',
                // Note: formatter will be set by JavaScript in the chart controller
                // to use: params => `Temperature: ${params.value[0]}°C<br/>Performance: ${params.value[1]}%`
            ],
            'xAxis' => [
                'type'          => 'value',
                'name'          => 'Temperature (°C)',
                'nameLocation'  => 'middle',
                'nameGap'       => 40,
                'nameTextStyle' => ['fontSize' => 11],
                'min'           => -35,
                'max'           => 35,
            ],
            'yAxis' => [
                'type'          => 'value',
                'name'          => 'On-Time %',
                'nameLocation'  => 'middle',
                'nameGap'       => 40,
                'nameTextStyle' => ['fontSize' => 11],
                'min'           => 40,
                'max'           => 100,
            ],
            'series' => [
                [
                    'name'      => 'Performance',
                    'type'      => 'scatter',
                    'data'      => $scatterData,
                    'itemStyle' => ['color' => '#0284c7'],
                ],
                [
                    'name'       => 'Trend',
                    'type'       => 'line',
                    'data'       => array_map(fn ($temp, $perf) => [$temp, $perf], $xAxisData, $lineData),
                    'smooth'     => true,
                    'lineStyle'  => ['type' => 'dashed', 'color' => '#ef4444', 'width' => 2],
                    'showSymbol' => false,
                ],
            ],
            'grid' => [
                'left'         => '30',
                'right'        => '4%',
                'bottom'       => '10%',
                'containLabel' => true,
            ],
            // Mark line at -20°C threshold
            'markLine' => [
                'silent' => true,
                'data'   => [
                    [
                        'xAxis'     => -20,
                        'label'     => ['formatter' => 'Critical: -20°C', 'position' => 'insideEndTop'],
                        'lineStyle' => ['color' => '#dc2626', 'type' => 'solid', 'width' => 2],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build statistics for temperature threshold story card.
     *
     * @return array<string, mixed>
     */
    private function buildTemperatureThresholdStats(): array
    {
        // Query 1: Performance above -20°C
        $qb          = $this->performanceRepo->createQueryBuilder('p');
        $aboveResult = $qb->select('AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.temperatureCelsius >= -20')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->getQuery()
            ->getSingleResult();

        // Query 2: Performance below -20°C
        $qb2         = $this->performanceRepo->createQueryBuilder('p');
        $belowResult = $qb2->select('AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.temperatureCelsius < -20')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->getQuery()
            ->getSingleResult();

        $aboveThreshold = (float) ($aboveResult['avgPerf'] ?? 0.0);
        $belowThreshold = (float) ($belowResult['avgPerf'] ?? 0.0);

        return [
            'aboveThreshold'  => round($aboveThreshold, 1),
            'belowThreshold'  => round($belowThreshold, 1),
            'performanceDrop' => round($aboveThreshold - $belowThreshold, 1),
            'daysAbove'       => (int) ($aboveResult['days'] ?? 0),
            'daysBelow'       => (int) ($belowResult['days'] ?? 0),
        ];
    }

    /**
     * Build weather impact matrix heatmap.
     *
     * Shows all routes × all weather conditions.
     *
     * @return array<string, mixed>
     */
    private function buildWeatherImpactMatrix(): array
    {
        // Query: All routes × all weather conditions
        $qb = $this->performanceRepo->createQueryBuilder('p');
        $qb->select(
            'r.shortName',
            'w.weatherCondition',
            'AVG(p.onTimePercentage) as avgPerformance'
        )
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition IS NOT NULL')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->groupBy('r.id', 'r.shortName', 'w.weatherCondition')
            ->orderBy('r.shortName', 'ASC')
            ->addOrderBy('w.weatherCondition', 'ASC');

        $results = $qb->getQuery()->getResult();

        // Build heatmap data
        $routes     = [];
        $conditions = ['clear', 'cloudy', 'rain', 'snow', 'showers', 'thunderstorm'];
        $data       = [];

        // Index routes and conditions
        $routeIndex     = [];
        $conditionIndex = array_flip($conditions);

        foreach ($results as $row) {
            $routeName = 'Route '.$row['shortName'];
            if (!isset($routeIndex[$routeName])) {
                $routeIndex[$routeName] = count($routes);
                $routes[]               = $routeName;
            }

            $condition = $row['weatherCondition'];
            if (isset($conditionIndex[$condition])) {
                $yIndex = $routeIndex[$routeName];
                $xIndex = $conditionIndex[$condition];
                $value  = round((float) $row['avgPerformance'], 1);

                $data[] = [$xIndex, $yIndex, $value];
            }
        }

        return [
            'title' => [
                'text'      => 'Weather Impact Matrix',
                'subtext'   => 'All Routes × All Conditions',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
            ],
            'tooltip' => [
                'position' => 'top',
            ],
            'xAxis' => [
                'type'      => 'category',
                'data'      => array_map('ucfirst', $conditions),
                'splitArea' => ['show' => true],
            ],
            'yAxis' => [
                'type'      => 'category',
                'data'      => $routes,
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
                        // Formatter will be added by JS
                    ],
                ],
            ],
            'grid' => [
                'height' => '70%',
                'top'    => '15%',
            ],
        ];
    }

    /**
     * Build statistics for weather impact matrix story card.
     *
     * @return array<string, mixed>
     */
    private function buildWeatherImpactStats(): array
    {
        // Find worst performing weather condition overall
        $qb = $this->performanceRepo->createQueryBuilder('p');
        $qb->select(
            'w.weatherCondition',
            'AVG(p.onTimePercentage) as avgPerformance',
            'COUNT(p.id) as dayCount'
        )
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition IS NOT NULL')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->groupBy('w.weatherCondition')
            ->orderBy('avgPerformance', 'ASC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result === null) {
            return [
                'worstCondition' => 'N/A',
                'avgPerformance' => 0.0,
                'dayCount'       => 0,
            ];
        }

        return [
            'worstCondition' => ucfirst((string) $result['weatherCondition']),
            'avgPerformance' => round((float) $result['avgPerformance'], 1),
            'dayCount'       => (int) $result['dayCount'],
        ];
    }

    /**
     * Build bunching by weather chart.
     *
     * Queries bunching_incident table for real data grouped by weather condition.
     *
     * @return array<string, mixed>
     */
    private function buildBunchingByWeatherChart(): array
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        // Query bunching incidents grouped by weather condition
        $results = $this->bunchingRepo->countByWeatherCondition($startDate, $endDate);

        // Build data arrays
        $conditionMap = [
            'snow'   => ['label' => 'Snow', 'color' => '#ede9fe'],
            'rain'   => ['label' => 'Rain', 'color' => '#dbeafe'],
            'cloudy' => ['label' => 'Cloudy', 'color' => '#e5e7eb'],
            'clear'  => ['label' => 'Clear', 'color' => '#fef3c7'],
        ];

        $data = [];
        foreach ($conditionMap as $condition => $config) {
            $count = 0;
            foreach ($results as $dto) {
                if (strtolower($dto->weatherCondition) === $condition) {
                    $count = $dto->incidentCount;

                    break;
                }
            }
            $data[] = [
                'value'     => $count,
                'itemStyle' => ['color' => $config['color']],
            ];
        }

        $conditions = array_column($conditionMap, 'label');

        $totalIncidents = array_sum(array_column($data, 'value'));
        $hasData        = $totalIncidents > 0;

        return [
            'title' => [
                'text'      => 'Bunching Incidents by Weather',
                'subtext'   => $hasData ? 'Last 30 days' : 'No data available yet',
                'left'      => 'center',
                'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
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
                'type'          => 'value',
                'name'          => 'Incidents',
                'nameLocation'  => 'middle',
                'nameGap'       => 40,
                'nameTextStyle' => ['fontSize' => 11],
                'min'           => 0,
            ],
            'series' => [
                [
                    'name'  => 'Bunching Incidents',
                    'type'  => 'bar',
                    'data'  => $data,
                    'label' => [
                        'show'      => $hasData,
                        'position'  => 'top',
                        'formatter' => '{c} incidents',
                    ],
                ],
            ],
            'graphic' => $hasData ? [] : [
                [
                    'type'  => 'text',
                    'left'  => 'center',
                    'top'   => 'middle',
                    'style' => [
                        'text'       => "No bunching data yet\n\nRun 'app:detect:bunching' command\nto analyze arrival patterns",
                        'fontSize'   => 14,
                        'fill'       => '#94a3b8',
                        'textAlign'  => 'center',
                        'fontWeight' => 'normal',
                    ],
                ],
            ],
            'grid' => [
                'left'         => '30',
                'right'        => '4%',
                'bottom'       => '10%',
                'containLabel' => true,
            ],
        ];
    }

    /**
     * Build statistics for bunching story card.
     *
     * @return array<string, mixed>
     */
    private function buildBunchingByWeatherStats(): array
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        // Query bunching incidents grouped by weather condition
        $results = $this->bunchingRepo->countByWeatherCondition($startDate, $endDate);

        $snowIncidents  = 0;
        $rainIncidents  = 0;
        $clearIncidents = 0;

        foreach ($results as $dto) {
            $condition = strtolower($dto->weatherCondition);

            match ($condition) {
                'snow'  => $snowIncidents  = $dto->incidentCount,
                'rain'  => $rainIncidents  = $dto->incidentCount,
                'clear' => $clearIncidents = $dto->incidentCount,
                default => null,
            };
        }

        $multiplier = $clearIncidents                                     > 0 ? round($snowIncidents / $clearIncidents, 1) : 0.0;
        $hasData    = ($snowIncidents + $rainIncidents + $clearIncidents) > 0;

        return [
            'snowIncidents'  => $snowIncidents,
            'rainIncidents'  => $rainIncidents,
            'clearIncidents' => $clearIncidents,
            'multiplier'     => $multiplier,
            'hasData'        => $hasData,
        ];
    }
}
