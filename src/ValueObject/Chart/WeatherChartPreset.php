<?php

declare(strict_types=1);

namespace App\ValueObject\Chart;

use App\Enum\WeatherCondition;

/**
 * Preset chart configurations for weather impact analysis.
 *
 * Provides factory methods for creating commonly-used weather analysis charts
 * with consistent styling and configuration.
 */
final class WeatherChartPreset
{
    /**
     * Create winter operations comparison chart (clear vs snow).
     *
     * @param array<string> $routeNames Route labels (e.g., ["Route 1", "Route 2"])
     * @param array<float>  $clearData  Clear weather performance data
     * @param array<float>  $snowData   Snow weather performance data
     */
    public static function winterOperations(array $routeNames, array $clearData, array $snowData): Chart
    {
        return ChartBuilder::bar()
            ->title('Winter Operations: Clear vs Snow Performance')
            ->categoryXAxis($routeNames)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->legend('bottom')
            ->addSeries('Clear Weather', $clearData, [
                'itemStyle' => ['color' => '#fef3c7', 'borderColor' => '#f59e0b', 'borderWidth' => 1],
                'label'     => [
                    'show'      => true,
                    'position'  => 'top',
                    'formatter' => '{c}%',
                    'fontSize'  => 11,
                ],
            ])
            ->addSeries('Snow', $snowData, [
                'itemStyle' => ['color' => '#ede9fe', 'borderColor' => '#8b5cf6', 'borderWidth' => 1],
                'label'     => [
                    'show'      => true,
                    'position'  => 'top',
                    'formatter' => '{c}%',
                    'fontSize'  => 11,
                ],
            ])
            ->grid([
                'left'         => '35',
                'right'        => '4%',
                'bottom'       => '20%',
                'containLabel' => true,
            ])
            ->custom('xAxis', [
                'type'      => 'category',
                'data'      => $routeNames,
                'axisLabel' => ['rotate' => 45, 'interval' => 0],
            ])
            ->build();
    }

    /**
     * Create temperature threshold scatter chart with trend line.
     *
     * @param array<array{0: int, 1: float, symbolSize: int}> $scatterData Scatter plot data
     * @param array<array{0: int, 1: float}>                  $lineData    Trend line data
     */
    public static function temperatureThreshold(array $scatterData, array $lineData): Chart
    {
        return ChartBuilder::scatter()
            ->title('Temperature Threshold Analysis')
            ->valueXAxis('Temperature (°C)', min: -35, max: 35)
            ->valueYAxis('On-Time %', min: 40, max: 100)
            ->tooltip(['trigger' => 'item'])
            ->addSeries('Performance', $scatterData, [
                'type'      => 'scatter',
                'itemStyle' => ['color' => '#0284c7'],
            ])
            ->addSeries('Trend', $lineData, [
                'type'       => 'line',
                'smooth'     => true,
                'lineStyle'  => ['type' => 'dashed', 'color' => '#ef4444', 'width' => 2],
                'showSymbol' => false,
            ])
            ->grid([
                'left'         => '35',
                'right'        => '4%',
                'bottom'       => '10%',
                'containLabel' => true,
            ])
            ->custom('markLine', [
                'silent' => true,
                'data'   => [
                    [
                        'xAxis'     => -20,
                        'label'     => ['formatter' => 'Critical: -20°C', 'position' => 'insideEndTop'],
                        'lineStyle' => ['color' => '#dc2626', 'type' => 'solid', 'width' => 2],
                    ],
                ],
            ])
            ->build();
    }

    /**
     * Create weather impact matrix heatmap.
     *
     * @param array<string>                          $routes     Route labels
     * @param array<WeatherCondition>                $conditions Weather conditions
     * @param array<array{0: int, 1: int, 2: float}> $data       Heatmap data [x, y, value]
     */
    public static function weatherImpactMatrix(array $routes, array $conditions, array $data): Chart
    {
        $conditionLabels = array_map(fn ($c) => $c->label(), $conditions);

        return ChartBuilder::bar()
            ->title('Weather Impact Matrix', 'All Routes × All Conditions')
            ->tooltip(['position' => 'top'])
            ->grid([
                'height' => '70%',
                'top'    => '15%',
            ])
            ->custom('xAxis', [
                'type'      => 'category',
                'data'      => $conditionLabels,
                'splitArea' => ['show' => true],
            ])
            ->custom('yAxis', [
                'type'      => 'category',
                'data'      => $routes,
                'splitArea' => ['show' => true],
            ])
            ->custom('visualMap', [
                'min'        => 40,
                'max'        => 100,
                'calculable' => true,
                'orient'     => 'horizontal',
                'left'       => 'center',
                'bottom'     => '0%',
                'inRange'    => [
                    'color' => ['#dc2626', '#f97316', '#fbbf24', '#84cc16', '#10b981'],
                ],
            ])
            ->custom('series', [
                [
                    'name'  => 'Performance',
                    'type'  => 'heatmap',
                    'data'  => $data,
                    'label' => [
                        'show'     => true,
                        'fontSize' => 10,
                    ],
                ],
            ])
            ->build();
    }

    /**
     * Create bunching rate by weather condition bar chart.
     *
     * @param array<WeatherCondition>                                            $conditions Weather conditions to display
     * @param array<array{value: float, itemStyle: array, exposureHours: float}> $data       Chart data with rates
     * @param bool                                                               $hasData    Whether data exists (for empty state)
     */
    public static function bunchingByWeather(array $conditions, array $data, bool $hasData): Chart
    {
        $labels = array_map(fn ($c) => $c->label(), $conditions);

        $builder = ChartBuilder::bar()
            ->title(
                'Bunching Rate by Weather Condition',
                $hasData ? 'Incidents per hour (last 30 days)' : 'No data available yet'
            )
            ->categoryXAxis($labels)
            ->valueYAxis('Incidents/Hour', min: 0)
            ->tooltip(['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']])
            ->addSeries('Bunching Rate', $data, [
                'label' => [
                    'show'      => $hasData,
                    'position'  => 'top',
                    'formatter' => '{c}',
                    'fontSize'  => 11,
                ],
            ])
            ->grid([
                'left'         => '50',
                'right'        => '4%',
                'bottom'       => '10%',
                'containLabel' => true,
            ]);

        // Add empty state graphic if no data
        if (!$hasData) {
            $builder->custom('graphic', [
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
            ]);
        }

        return $builder->build();
    }
}
