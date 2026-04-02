<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function sprintf;

/**
 * Data transfer object for analytics summary cards.
 *
 * Contains high-level metrics for the selected date range
 * displayed as summary cards at the top of the analytics page.
 */
final readonly class AnalyticsSummaryDto
{
    public function __construct(
        public int $totalPredictions,
        public float $avgOnTimePercentage,
        public int $avgDelaySec,
        public int $daysWithData,
        public int $routesTracked,
        public int $vehiclesTracked,
        public ?int $bunchingIncidents = null,
    ) {
    }

    /**
     * Get system-wide letter grade.
     */
    public function getGrade(): string
    {
        return match (true) {
            $this->avgOnTimePercentage >= 90 => 'A',
            $this->avgOnTimePercentage >= 80 => 'B',
            $this->avgOnTimePercentage >= 70 => 'C',
            $this->avgOnTimePercentage >= 60 => 'D',
            default                          => 'F',
        };
    }

    /**
     * Format average delay for display.
     */
    public function getFormattedAvgDelay(): string
    {
        $absDelay = abs($this->avgDelaySec);
        $minutes  = intdiv($absDelay, 60);
        $seconds  = $absDelay % 60;

        if ($this->avgDelaySec === 0) {
            return 'On time';
        }

        if ($this->avgDelaySec < 0) {
            return sprintf('%d:%02d early', $minutes, $seconds);
        }

        return sprintf('%d:%02d late', $minutes, $seconds);
    }

    /**
     * Format total predictions for display (e.g., "14.8M").
     */
    public function getFormattedPredictions(): string
    {
        if ($this->totalPredictions >= 1000000) {
            return sprintf('%.1fM', $this->totalPredictions / 1000000);
        }

        if ($this->totalPredictions >= 1000) {
            return sprintf('%.1fK', $this->totalPredictions / 1000);
        }

        return (string) $this->totalPredictions;
    }
}
