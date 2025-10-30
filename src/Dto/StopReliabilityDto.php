<?php

declare(strict_types=1);

namespace App\Dto;

use function sprintf;

/**
 * Stop-level reliability metrics for a specific route.
 *
 * Shows which stops on a route are consistently causing delays,
 * enabling transit agencies to identify bottleneck locations.
 */
final readonly class StopReliabilityDto
{
    public function __construct(
        public int $stopId,
        public string $stopName,
        public int $avgDelaySec,
        public float $onTimePercentage,
        public int $sampleSize,
        public string $confidenceLevel,
        public int $stopSequence,
        public int $direction,
    ) {
    }

    /**
     * Format delay as human-readable string.
     */
    public function getDelayFormatted(): string
    {
        $absDelay = abs($this->avgDelaySec);
        $minutes  = (int) floor($absDelay / 60);
        $seconds  = $absDelay % 60;

        $sign = $this->avgDelaySec >= 0 ? '+' : '-';

        if ($minutes === 0) {
            return sprintf('%s%ds', $sign, $seconds);
        }

        return sprintf('%s%dm %ds', $sign, $minutes, $seconds);
    }

    /**
     * Determine if this stop is a bottleneck (consistently late).
     */
    public function isBottleneck(): bool
    {
        // Consider a bottleneck if:
        // - Average delay > 3 minutes (180 seconds)
        // - On-time percentage < 50%
        // - Sufficient sample size (>= 10)
        return $this->avgDelaySec > 180
            && $this->onTimePercentage < 50.0
            && $this->sampleSize >= 10;
    }

    /**
     * Get Tailwind CSS color class based on average delay.
     */
    public function getColorClass(): string
    {
        return match (true) {
            $this->avgDelaySec <= -180 => 'text-blue-600',      // Very early
            $this->avgDelaySec < -60   => 'text-blue-500',      // Early
            $this->avgDelaySec <= 60   => 'text-success-600',   // On-time
            $this->avgDelaySec <= 180  => 'text-warning-600',   // Slightly late
            $this->avgDelaySec <= 300  => 'text-danger-500',    // Late
            default                    => 'text-danger-700',    // Very late
        };
    }
}
