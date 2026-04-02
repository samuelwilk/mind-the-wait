<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function sprintf;

/**
 * Data transfer object for individual vehicle performance metrics.
 *
 * Tracks reliability metrics for a specific vehicle across all routes
 * during a time period.
 */
final readonly class VehiclePerformanceDto
{
    public function __construct(
        public string $vehicleId,
        public int $totalPredictions,
        public float $onTimePercentage,
        public int $avgDelaySec,
        public int $routesServed,
        public ?string $mostReliableRoute = null,
        public ?float $mostReliableRouteOnTime = null,
    ) {
    }

    /**
     * Get letter grade based on on-time percentage.
     */
    public function getGrade(): string
    {
        return match (true) {
            $this->onTimePercentage >= 90 => 'A',
            $this->onTimePercentage >= 80 => 'B',
            $this->onTimePercentage >= 70 => 'C',
            $this->onTimePercentage >= 60 => 'D',
            default                       => 'F',
        };
    }

    /**
     * Get grade color class for UI.
     */
    public function getGradeColor(): string
    {
        return match ($this->getGrade()) {
            'A', 'B' => 'text-green-600',
            'C'     => 'text-yellow-600',
            'D'     => 'text-orange-600',
            default => 'text-red-600',
        };
    }

    /**
     * Format average delay for display.
     */
    public function getFormattedDelay(): string
    {
        $absDelay = abs($this->avgDelaySec);
        $minutes  = intdiv($absDelay, 60);
        $seconds  = $absDelay % 60;

        if ($this->avgDelaySec < 0) {
            return sprintf('-%d:%02d early', $minutes, $seconds);
        }

        if ($this->avgDelaySec === 0) {
            return 'On time';
        }

        return sprintf('+%d:%02d late', $minutes, $seconds);
    }
}
