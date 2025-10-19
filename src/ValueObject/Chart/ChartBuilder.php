<?php

declare(strict_types=1);

namespace App\ValueObject\Chart;

/**
 * Fluent builder for creating ECharts configurations.
 *
 * Provides a type-safe, chainable API for constructing chart configurations
 * instead of building large nested arrays manually.
 *
 * Example usage:
 *
 * ```php
 * $chart = ChartBuilder::bar()
 *     ->title('Route Performance', 'Last 30 days')
 *     ->categoryXAxis(['Route 1', 'Route 2', 'Route 3'])
 *     ->valueYAxis('On-Time %', min: 0, max: 100)
 *     ->addSeries('Performance', [78, 85, 92])
 *     ->build();
 * ```
 */
final class ChartBuilder
{
    /** @var array<string, mixed> */
    private array $config = [];

    /** @var array<int, array<string, mixed>> */
    private array $series = [];

    private function __construct(string $chartType)
    {
        $this->config = [
            'type'    => $chartType,
            'tooltip' => [
                'trigger'     => 'axis',
                'axisPointer' => [
                    'type' => 'shadow',
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
     * Create a bar chart builder.
     */
    public static function bar(): self
    {
        return new self('bar');
    }

    /**
     * Create a line chart builder.
     */
    public static function line(): self
    {
        return new self('line');
    }

    /**
     * Create a scatter chart builder.
     */
    public static function scatter(): self
    {
        return new self('scatter');
    }

    /**
     * Set chart title and optional subtitle.
     *
     * @param string      $title    Main title text
     * @param string|null $subtitle Optional subtitle text
     */
    public function title(string $title, ?string $subtitle = null): self
    {
        $this->config['title'] = [
            'text' => $title,
            'left' => 'center',
        ];

        if ($subtitle !== null) {
            $this->config['title']['subtext'] = $subtitle;
        }

        return $this;
    }

    /**
     * Add a data series to the chart.
     *
     * @param string                    $name   Series name (shown in legend/tooltip)
     * @param array<int, mixed>         $data   Series data points
     * @param array<string, mixed>|null $config Additional series configuration
     */
    public function addSeries(string $name, array $data, ?array $config = []): self
    {
        $series = [
            'name' => $name,
            'type' => $this->config['type'],
            'data' => $data,
        ];

        if ($config !== null && $config !== []) {
            $series = array_merge($series, $config);
        }

        $this->series[] = $series;

        return $this;
    }

    /**
     * Configure category-based X-axis (for bar/line charts).
     *
     * @param array<int, string> $categories Category labels
     * @param string|null        $name       Axis name
     */
    public function categoryXAxis(array $categories, ?string $name = null): self
    {
        $this->config['xAxis'] = [
            'type' => 'category',
            'data' => $categories,
        ];

        if ($name !== null) {
            $this->config['xAxis']['name'] = $name;
        }

        return $this;
    }

    /**
     * Configure value-based X-axis (for scatter charts).
     *
     * @param string   $name Axis name
     * @param int|null $min  Minimum value
     * @param int|null $max  Maximum value
     */
    public function valueXAxis(string $name, ?int $min = null, ?int $max = null): self
    {
        $this->config['xAxis'] = [
            'type' => 'value',
            'name' => $name,
        ];

        if ($min !== null) {
            $this->config['xAxis']['min'] = $min;
        }

        if ($max !== null) {
            $this->config['xAxis']['max'] = $max;
        }

        return $this;
    }

    /**
     * Configure value-based Y-axis.
     *
     * @param string   $name Axis name
     * @param int|null $min  Minimum value
     * @param int|null $max  Maximum value
     */
    public function valueYAxis(string $name, ?int $min = null, ?int $max = null): self
    {
        $this->config['yAxis'] = [
            'type'          => 'value',
            'name'          => $name,
            'nameLocation'  => 'middle',
            'nameGap'       => 50,
            'nameTextStyle' => ['fontSize' => 11],
        ];

        if ($min !== null) {
            $this->config['yAxis']['min'] = $min;
        }

        if ($max !== null) {
            $this->config['yAxis']['max'] = $max;
        }

        return $this;
    }

    /**
     * Enable legend with series names.
     *
     * @param string $position Legend position: 'top', 'bottom', 'left', 'right'
     */
    public function legend(string $position = 'top'): self
    {
        $this->config['legend'] = [
            $position => '5%',
        ];

        return $this;
    }

    /**
     * Configure tooltip settings.
     *
     * @param array<string, mixed> $config Tooltip configuration
     */
    public function tooltip(array $config): self
    {
        $this->config['tooltip'] = array_merge($this->config['tooltip'] ?? [], $config);

        return $this;
    }

    /**
     * Configure grid (chart positioning/padding).
     *
     * @param array<string, mixed> $config Grid configuration
     */
    public function grid(array $config): self
    {
        $this->config['grid'] = array_merge($this->config['grid'] ?? [], $config);

        return $this;
    }

    /**
     * Add custom configuration key-value pair.
     *
     * Use this for advanced ECharts options not covered by builder methods.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     */
    public function custom(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * Build and return the immutable Chart value object.
     */
    public function build(): Chart
    {
        $config = $this->config;
        if ($this->series !== []) {
            $config['series'] = $this->series;
        }

        return new Chart($config);
    }
}
