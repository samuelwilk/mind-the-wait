<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\WeatherCondition;

/**
 * Data transfer object for weather impact matrix heatmap cell.
 *
 * Represents a single cell in the route × weather condition matrix,
 * showing how a specific route performs under a specific weather condition.
 */
final readonly class WeatherImpactMatrixDto
{
    /**
     * @param string           $routeShortName   Route short name (e.g., "16")
     * @param WeatherCondition $weatherCondition Weather condition
     * @param float            $avgPerformance   Average on-time performance %
     */
    public function __construct(
        public string $routeShortName,
        public WeatherCondition $weatherCondition,
        public float $avgPerformance,
    ) {
    }
}
