<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\WeatherCondition;

/**
 * Data transfer object for transit performance under specific weather conditions.
 *
 * Used in weather impact matrix and temperature threshold analysis.
 */
final readonly class WeatherPerformanceDto
{
    /**
     * @param WeatherCondition $weatherCondition Weather condition
     * @param float            $avgPerformance   Average on-time performance %
     * @param int              $dayCount         Number of days analyzed
     * @param float            $avgTemperature   Average temperature (Celsius)
     */
    public function __construct(
        public WeatherCondition $weatherCondition,
        public float $avgPerformance,
        public int $dayCount,
        public float $avgTemperature,
    ) {
    }
}
