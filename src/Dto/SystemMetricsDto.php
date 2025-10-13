<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class SystemMetricsDto
{
    /**
     * @param array<int, RouteMetricDto> $topPerformers
     * @param array<int, RouteMetricDto> $needsAttention
     * @param array<int, RouteMetricDto> $historicalTopPerformers
     * @param array<int, RouteMetricDto> $historicalWorstPerformers
     * @param string                     $winterWeatherImpactInsight  AI-generated insight card (HTML)
     * @param string                     $temperatureThresholdInsight AI-generated insight card (HTML)
     */
    public function __construct(
        public string $systemGrade,
        public float $onTimePercentage,
        public int $activeVehicles,
        public int $totalRoutes,
        public float $changeVsYesterday,
        public ?WeatherDataDto $currentWeather,
        public array $topPerformers,
        public array $needsAttention,
        public array $historicalTopPerformers,
        public array $historicalWorstPerformers,
        public string $winterWeatherImpactInsight,
        public string $temperatureThresholdInsight,
        public int $timestamp,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'system_grade'                => $this->systemGrade,
            'on_time_percentage'          => $this->onTimePercentage,
            'active_vehicles'             => $this->activeVehicles,
            'total_routes'                => $this->totalRoutes,
            'change_vs_yesterday'         => $this->changeVsYesterday,
            'current_weather'             => $this->currentWeather?->toArray(),
            'top_performers'              => array_map(fn (RouteMetricDto $r) => $r->toArray(), $this->topPerformers),
            'needs_attention'             => array_map(fn (RouteMetricDto $r) => $r->toArray(), $this->needsAttention),
            'historical_top_performers'   => array_map(fn (RouteMetricDto $r) => $r->toArray(), $this->historicalTopPerformers),
            'historical_worst_performers' => array_map(fn (RouteMetricDto $r) => $r->toArray(), $this->historicalWorstPerformers),
            'timestamp'                   => $this->timestamp,
        ];
    }
}
