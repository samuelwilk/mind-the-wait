<?php

declare(strict_types=1);

namespace App\Dto;

use App\Dto\Insight\BunchingByWeatherStatsDto;
use App\Dto\Insight\TemperatureThresholdStatsDto;
use App\Dto\Insight\WeatherImpactMatrixStatsDto;
use App\Dto\Insight\WinterOperationsStatsDto;
use App\ValueObject\Chart\Chart;

/**
 * Weather impact insights data transfer object.
 *
 * Contains all chart configurations, statistics, and AI-generated narratives for the weather impact page.
 */
final readonly class WeatherImpactDto
{
    /**
     * @param Chart                        $winterOperationsChart         Chart for clear vs snow comparison
     * @param WinterOperationsStatsDto     $winterOperationsStats         Statistics for story card
     * @param string                       $winterOperationsNarrative     AI-generated insight text (HTML)
     * @param Chart                        $temperatureThresholdChart     Chart for temperature analysis
     * @param TemperatureThresholdStatsDto $temperatureThresholdStats     Statistics for story card
     * @param string                       $temperatureThresholdNarrative AI-generated insight text (HTML)
     * @param Chart                        $weatherImpactMatrix           Chart for route×weather heatmap
     * @param WeatherImpactMatrixStatsDto  $weatherImpactStats            Statistics for story card
     * @param string                       $weatherImpactNarrative        AI-generated insight text (HTML)
     * @param Chart                        $bunchingByWeatherChart        Chart for bunching analysis
     * @param BunchingByWeatherStatsDto    $bunchingByWeatherStats        Statistics for story card
     * @param string                       $bunchingByWeatherNarrative    AI-generated insight text (HTML)
     * @param string                       $keyTakeaway                   AI-generated key takeaway (HTML)
     */
    public function __construct(
        public Chart $winterOperationsChart,
        public WinterOperationsStatsDto $winterOperationsStats,
        public string $winterOperationsNarrative,
        public Chart $temperatureThresholdChart,
        public TemperatureThresholdStatsDto $temperatureThresholdStats,
        public string $temperatureThresholdNarrative,
        public Chart $weatherImpactMatrix,
        public WeatherImpactMatrixStatsDto $weatherImpactStats,
        public string $weatherImpactNarrative,
        public Chart $bunchingByWeatherChart,
        public BunchingByWeatherStatsDto $bunchingByWeatherStats,
        public string $bunchingByWeatherNarrative,
        public string $keyTakeaway,
    ) {
    }
}
