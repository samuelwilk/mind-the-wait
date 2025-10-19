<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\WeatherCondition;

/**
 * Data transfer object for bunching incident rates by weather condition.
 *
 * Represents normalized bunching data showing incidents per hour of exposure,
 * allowing fair comparison across different weather conditions.
 */
final readonly class BunchingRateDto
{
    /**
     * @param WeatherCondition $weatherCondition Weather condition for this rate
     * @param int              $incidentCount    Total bunching incidents observed
     * @param float            $exposureHours    Hours of this weather condition in analysis period
     * @param float            $incidentsPerHour Normalized rate (incidents / exposure hours)
     */
    public function __construct(
        public WeatherCondition $weatherCondition,
        public int $incidentCount,
        public float $exposureHours,
        public float $incidentsPerHour,
    ) {
    }
}
