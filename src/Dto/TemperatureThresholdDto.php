<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Data transfer object for performance comparison above/below temperature threshold.
 *
 * Used in temperature threshold analysis to compare performance
 * at different temperature ranges (typically above/below -20°C).
 */
final readonly class TemperatureThresholdDto
{
    /**
     * @param float $avgPerformance Average on-time performance %
     * @param int   $dayCount       Number of days analyzed
     */
    public function __construct(
        public float $avgPerformance,
        public int $dayCount,
    ) {
    }
}
