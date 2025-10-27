<?php

declare(strict_types=1);

namespace App\ValueObject\Chart;

/**
 * Preset chart configurations for route performance analysis.
 *
 * Provides factory methods for creating commonly-used route analysis charts
 * with consistent styling and configuration.
 */
final class RoutePerformanceChartPreset
{
    /**
     * Create 30-day performance trend chart with smooth area.
     *
     * @param array<string> $dates  Date labels (e.g., ["Sep 14", "Sep 15"])
     * @param array<float>  $values Performance values (on-time percentages)
     */
    public static function performanceTrend(array $dates, array $values): Chart
    {
        return ChartBuilder::line()
            ->title('30-Day Performance Trend')
            ->categoryXAxis($dates)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->tooltip(['trigger' => 'axis', 'formatter' => '{b}<br/>On-Time: {c}%'])
            ->addSeries('On-Time Performance', $values, [
                'smooth'    => true,
                'lineStyle' => ['width' => 3],
                'itemStyle' => ['color' => '#0284c7'],
                'areaStyle' => ['color' => 'rgba(2, 132, 199, 0.1)'],
            ])
            ->grid([
                'left'         => '35',
                'right'        => '4%',
                'top'          => '60',
                'bottom'       => '15%',
                'containLabel' => true,
            ])
            ->custom('xAxis', [
                'type'      => 'category',
                'data'      => $dates,
                'axisLabel' => ['rotate' => 45],
            ])
            ->build();
    }

    /**
     * Create weather impact comparison bar chart.
     *
     * @param array<string> $conditions Weather condition labels
     * @param array<float>  $values     Performance values
     * @param array<string> $colors     Bar colors for each condition
     */
    public static function weatherImpact(array $conditions, array $values, array $colors): Chart
    {
        $data = array_map(
            fn ($value, $color) => ['value' => $value, 'itemStyle' => ['color' => $color]],
            $values,
            $colors
        );

        return ChartBuilder::bar()
            ->title('Performance by Weather Condition')
            ->categoryXAxis($conditions)
            ->valueYAxis('On-Time %', min: 0, max: 100)
            ->tooltip(['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']])
            ->addSeries('On-Time Performance', $data, [
                'label' => [
                    'show'      => true,
                    'position'  => 'top',
                    'formatter' => '{c}%',
                ],
            ])
            ->grid([
                'left'         => '35',
                'right'        => '4%',
                'top'          => '60',
                'bottom'       => '3%',
                'containLabel' => true,
            ])
            ->build();
    }

    /**
     * Create time-of-day heatmap showing performance by day of week and time.
     *
     * @param array<string>                          $days  Day labels (e.g., ["Mon", "Tue"])
     * @param array<string>                          $hours Hour labels (e.g., ["0-6", "6-9"])
     * @param array<array{0: int, 1: int, 2: float}> $data  Heatmap data [dayIndex, hourIndex, value]
     */
    public static function timeOfDayHeatmap(array $days, array $hours, array $data): Chart
    {
        return ChartBuilder::bar()
            ->title('Performance by Day & Time')
            ->tooltip(['position' => 'top', 'formatter' => 'Day: {b}<br/>Time: {c0}<br/>Performance: {c2}%'])
            ->grid([
                'height' => '60%',
                'top'    => '15%',
            ])
            ->custom('xAxis', [
                'type'      => 'category',
                'data'      => $days,
                'splitArea' => ['show' => true],
            ])
            ->custom('yAxis', [
                'type'      => 'category',
                'data'      => $hours,
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
     * Create stop-level reliability chart showing which stops cause delays.
     *
     * Horizontal bar chart sorted by average delay (worst stops first).
     *
     * @param array<string> $stopNames Stop name labels
     * @param array<int>    $delays    Average delay in seconds for each stop
     * @param array<string> $colors    Bar colors indicating delay severity
     */
    public static function stopReliability(array $stopNames, array $delays, array $colors): Chart
    {
        // Convert delays to minutes for readability
        $delaysInMinutes = array_map(fn ($delay) => round($delay / 60, 1), $delays);

        $data = array_map(
            fn ($value, $color) => ['value' => $value, 'itemStyle' => ['color' => $color]],
            $delaysInMinutes,
            $colors
        );

        return ChartBuilder::bar()
            ->title('Stop-Level Reliability (Worst Delays First)')
            ->custom('yAxis', [
                'type' => 'category',
                'data' => $stopNames,
            ])
            ->custom('xAxis', [
                'type' => 'value',
                'name' => 'Avg Delay (minutes)',
            ])
            ->tooltip([
                'trigger'   => 'axis',
                'formatter' => '{b}<br/>Avg Delay: {c} min',
            ])
            ->custom('series', [
                [
                    'name'  => 'Average Delay',
                    'type'  => 'bar',
                    'data'  => $data,
                    'label' => [
                        'show'      => true,
                        'position'  => 'right',
                        'formatter' => '{c} min',
                    ],
                ],
            ])
            ->grid([
                'left'         => '35%',
                'right'        => '15%',
                'top'          => '60',
                'bottom'       => '5%',
                'containLabel' => false,
            ])
            ->build();
    }
}
