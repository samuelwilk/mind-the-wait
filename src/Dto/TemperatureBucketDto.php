<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Data transfer object for performance data grouped by temperature bucket.
 *
 * Used in temperature threshold analysis to show how performance varies
 * across different temperature ranges.
 */
final readonly class TemperatureBucketDto
{
    /**
     * @param int   $temperatureBucket Temperature bucket (e.g., -30, -25, -20, -15...)
     * @param float $avgPerformance    Average on-time performance %
     * @param int   $observationCount  Number of observations in this bucket
     */
    public function __construct(
        public int $temperatureBucket,
        public float $avgPerformance,
        public int $observationCount,
    ) {
    }
}
