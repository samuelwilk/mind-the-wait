<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\WeatherImpactDto;
use App\Enum\WeatherCondition;
use App\Repository\BunchingIncidentRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\ValueObject\Chart\Chart;
use App\ValueObject\Chart\WeatherChartPreset;

use function array_map;
use function count;

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
     */
    private function buildWinterOperationsChart(): Chart
    {
        // Get winter performance comparison from repository
        $results = $this->performanceRepo->findWinterPerformanceComparison(minDays: 3, limit: 10);

        // Extract data from DTOs
        $routes    = array_map(fn ($dto) => 'Route '.$dto->shortName, $results);
        $clearData = array_map(fn ($dto) => round($dto->clearPerformance, 1), $results);
        $snowData  = array_map(fn ($dto) => round($dto->snowPerformance, 1), $results);

        return WeatherChartPreset::winterOperations($routes, $clearData, $snowData);
    }

    /**
     * Build statistics for winter operations story card.
     *
     * @return array<string, mixed>
     */
    private function buildWinterOperationsStats(): array
    {
        // Get worst affected route from repository
        $results = $this->performanceRepo->findWinterPerformanceComparison(minDays: 3, limit: 1);

        if (empty($results)) {
            return [
                'worstRoute'      => 'N/A',
                'clearPerf'       => 0.0,
                'snowPerf'        => 0.0,
                'performanceDrop' => 0.0,
            ];
        }

        $worst = $results[0];

        return [
            'worstRoute'      => 'Route '.$worst->shortName,
            'clearPerf'       => round($worst->clearPerformance, 1),
            'snowPerf'        => round($worst->snowPerformance, 1),
            'performanceDrop' => round($worst->performanceDrop, 1),
        ];
    }

    /**
     * Build temperature threshold analysis chart.
     *
     * Shows performance by temperature bucket with trend line.
     */
    private function buildTemperatureThresholdChart(): Chart
    {
        // Get temperature bucket data from repository
        $results = $this->performanceRepo->findPerformanceByTemperatureBucket();

        // Build scatter and line data
        $scatterData = [];
        $lineData    = [];

        foreach ($results as $dto) {
            // Scatter point with symbol size based on observation count
            $scatterData[] = [
                'value'      => [$dto->temperatureBucket, $dto->avgPerformance],
                'symbolSize' => min(5 + ($dto->observationCount / 2), 30),
            ];

            // Trend line data point
            $lineData[] = [$dto->temperatureBucket, $dto->avgPerformance];
        }

        return WeatherChartPreset::temperatureThreshold($scatterData, $lineData);
    }

    /**
     * Build statistics for temperature threshold story card.
     *
     * @return array<string, mixed>
     */
    private function buildTemperatureThresholdStats(): array
    {
        // Get temperature threshold comparison from repository
        $result = $this->performanceRepo->findPerformanceByTemperatureThreshold(threshold: -20.0);

        $aboveThreshold = $result['above']->avgPerformance;
        $belowThreshold = $result['below']->avgPerformance;

        return [
            'aboveThreshold'  => $aboveThreshold,
            'belowThreshold'  => $belowThreshold,
            'performanceDrop' => round($aboveThreshold - $belowThreshold, 1),
            'daysAbove'       => $result['above']->dayCount,
            'daysBelow'       => $result['below']->dayCount,
        ];
    }

    /**
     * Build weather impact matrix heatmap.
     *
     * Shows all routes × all weather conditions.
     */
    private function buildWeatherImpactMatrix(): Chart
    {
        // Get weather impact matrix from repository
        $results = $this->performanceRepo->findWeatherImpactMatrix();

        // Build heatmap data with indexing
        $routes     = [];
        $conditions = WeatherCondition::chartConditions();
        $data       = [];

        // Create indices
        $routeIndex     = [];
        $conditionIndex = [];

        foreach ($conditions as $index => $condition) {
            $conditionIndex[$condition->value] = $index;
        }

        foreach ($results as $dto) {
            $routeName = 'Route '.$dto->routeShortName;
            if (!isset($routeIndex[$routeName])) {
                $routeIndex[$routeName] = count($routes);
                $routes[]               = $routeName;
            }

            $conditionValue = $dto->weatherCondition->value;
            if (isset($conditionIndex[$conditionValue])) {
                $yIndex = $routeIndex[$routeName];
                $xIndex = $conditionIndex[$conditionValue];

                $data[] = [$xIndex, $yIndex, $dto->avgPerformance];
            }
        }

        return WeatherChartPreset::weatherImpactMatrix($routes, $conditions, $data);
    }

    /**
     * Build statistics for weather impact matrix story card.
     *
     * @return array<string, mixed>
     */
    private function buildWeatherImpactStats(): array
    {
        // Get worst performing weather condition from repository
        $result = $this->performanceRepo->findWorstPerformingWeatherCondition();

        if ($result === null) {
            return [
                'worstCondition' => 'N/A',
                'avgPerformance' => 0.0,
                'dayCount'       => 0,
            ];
        }

        return [
            'worstCondition' => $result->weatherCondition->label(),
            'avgPerformance' => $result->avgPerformance,
            'dayCount'       => $result->dayCount,
        ];
    }

    /**
     * Build bunching by weather chart.
     *
     * Shows normalized bunching rates (incidents per hour) by weather condition.
     */
    private function buildBunchingByWeatherChart(): Chart
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        // Get normalized data (incidents per hour)
        $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

        // Create lookup map for quick access
        $resultsByCondition = [];
        foreach ($results as $dto) {
            $resultsByCondition[$dto->weatherCondition->value] = $dto;
        }

        // Build chart data
        $conditions     = WeatherCondition::bunchingConditions();
        $data           = [];
        $totalIncidents = 0;

        foreach ($conditions as $condition) {
            $dto = $resultsByCondition[$condition->value] ?? null;

            $data[] = [
                'value'         => $dto?->incidentsPerHour ?? 0.0,
                'itemStyle'     => ['color' => $condition->chartColor()],
                'exposureHours' => $dto?->exposureHours ?? 0.0,
            ];

            $totalIncidents += $dto?->incidentCount ?? 0;
        }

        $hasData = $totalIncidents > 0;

        return WeatherChartPreset::bunchingByWeather($conditions, $data, $hasData);
    }

    /**
     * Build statistics for bunching story card with normalized rates.
     *
     * @return array<string, mixed>
     */
    private function buildBunchingByWeatherStats(): array
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

        $snowRate   = 0.0;
        $rainRate   = 0.0;
        $clearRate  = 0.0;
        $snowHours  = 0.0;
        $rainHours  = 0.0;
        $clearHours = 0.0;

        foreach ($results as $dto) {
            match ($dto->weatherCondition) {
                WeatherCondition::SNOW => [
                    $snowRate  = $dto->incidentsPerHour,
                    $snowHours = $dto->exposureHours,
                ],
                WeatherCondition::RAIN => [
                    $rainRate  = $dto->incidentsPerHour,
                    $rainHours = $dto->exposureHours,
                ],
                WeatherCondition::CLEAR => [
                    $clearRate  = $dto->incidentsPerHour,
                    $clearHours = $dto->exposureHours,
                ],
                default => null,
            };
        }

        // Calculate multiplier (how much worse is snow vs clear?)
        $multiplier = $clearRate      > 0 ? round($snowRate / $clearRate, 1) : 0.0;
        $hasData    = count($results) > 0;

        return [
            'snow_rate'   => $snowRate,
            'rain_rate'   => $rainRate,
            'clear_rate'  => $clearRate,
            'snow_hours'  => $snowHours,
            'rain_hours'  => $rainHours,
            'clear_hours' => $clearHours,
            'multiplier'  => $multiplier,
            'hasData'     => $hasData,
        ];
    }
}
