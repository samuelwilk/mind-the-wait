<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Weather impact insights data transfer object.
 *
 * Contains all chart configurations, statistics, and AI-generated narratives for the weather impact page.
 */
final readonly class WeatherImpactDto
{
    /**
     * @param array<string, mixed> $winterOperationsChart         ECharts config for clear vs snow comparison
     * @param array<string, mixed> $winterOperationsStats         Statistics for story card
     * @param string               $winterOperationsNarrative     AI-generated insight text (HTML)
     * @param array<string, mixed> $temperatureThresholdChart     ECharts config for temperature analysis
     * @param array<string, mixed> $temperatureThresholdStats     Statistics for story card
     * @param string               $temperatureThresholdNarrative AI-generated insight text (HTML)
     * @param array<string, mixed> $weatherImpactMatrix           ECharts config for route×weather heatmap
     * @param array<string, mixed> $weatherImpactStats            Statistics for story card
     * @param string               $weatherImpactNarrative        AI-generated insight text (HTML)
     * @param array<string, mixed> $bunchingByWeatherChart        ECharts config for bunching analysis
     * @param array<string, mixed> $bunchingByWeatherStats        Statistics for story card
     * @param string               $bunchingByWeatherNarrative    AI-generated insight text (HTML)
     * @param string               $keyTakeaway                   AI-generated key takeaway (HTML)
     */
    public function __construct(
        public array $winterOperationsChart,
        public array $winterOperationsStats,
        public string $winterOperationsNarrative,
        public array $temperatureThresholdChart,
        public array $temperatureThresholdStats,
        public string $temperatureThresholdNarrative,
        public array $weatherImpactMatrix,
        public array $weatherImpactStats,
        public string $weatherImpactNarrative,
        public array $bunchingByWeatherChart,
        public array $bunchingByWeatherStats,
        public string $bunchingByWeatherNarrative,
        public string $keyTakeaway,
    ) {
    }
}
