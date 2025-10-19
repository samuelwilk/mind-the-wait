<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\WeatherCondition;

/**
 * Data transfer object for route performance by weather condition.
 *
 * Used to analyze how a specific route performs under different weather conditions.
 */
final readonly class RouteWeatherPerformanceDto
{
    /**
     * @param WeatherCondition $weatherCondition Weather condition
     * @param float            $avgPerformance   Average on-time percentage
     * @param int              $dayCount         Number of days with this condition
     */
    public function __construct(
        public WeatherCondition $weatherCondition,
        public float $avgPerformance,
        public int $dayCount,
    ) {
    }
}
