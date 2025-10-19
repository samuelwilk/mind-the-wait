<?php

declare(strict_types=1);

namespace App\Dto;

use App\ValueObject\Chart\Chart;

/**
 * Weather impact insights data transfer object.
 *
 * Contains all chart configurations, statistics, and AI-generated narratives for the weather impact page.
 */
final readonly class WeatherImpactDto
{
    /**
     * @param Chart                $winterOperationsChart         Chart for clear vs snow comparison
     * @param array<string, mixed> $winterOperationsStats         Statistics for story card
     * @param string               $winterOperationsNarrative     AI-generated insight text (HTML)
     * @param Chart                $temperatureThresholdChart     Chart for temperature analysis
     * @param array<string, mixed> $temperatureThresholdStats     Statistics for story card
     * @param string               $temperatureThresholdNarrative AI-generated insight text (HTML)
     * @param Chart                $weatherImpactMatrix           Chart for route×weather heatmap
     * @param array<string, mixed> $weatherImpactStats            Statistics for story card
     * @param string               $weatherImpactNarrative        AI-generated insight text (HTML)
     * @param Chart                $bunchingByWeatherChart        Chart for bunching analysis
     * @param array<string, mixed> $bunchingByWeatherStats        Statistics for story card
     * @param string               $bunchingByWeatherNarrative    AI-generated insight text (HTML)
     * @param string               $keyTakeaway                   AI-generated key takeaway (HTML)
     */
    public function __construct(
        public Chart $winterOperationsChart,
        public array $winterOperationsStats,
        public string $winterOperationsNarrative,
        public Chart $temperatureThresholdChart,
        public array $temperatureThresholdStats,
        public string $temperatureThresholdNarrative,
        public Chart $weatherImpactMatrix,
        public array $weatherImpactStats,
        public string $weatherImpactNarrative,
        public Chart $bunchingByWeatherChart,
        public array $bunchingByWeatherStats,
        public string $bunchingByWeatherNarrative,
        public string $keyTakeaway,
    ) {
    }
}
