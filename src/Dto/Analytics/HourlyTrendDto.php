<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function sprintf;

/**
 * Data transfer object for hourly performance trend.
 *
 * Aggregates system-wide performance by hour of day (0-23).
 */
final readonly class HourlyTrendDto
{
    public function __construct(
        public int $hour,
        public float $avgOnTimePercentage,
        public int $avgDelaySec,
        public int $sampleCount,
    ) {
    }

    /**
     * Get formatted hour label (e.g., "6 AM", "2 PM").
     */
    public function getHourLabel(): string
    {
        if ($this->hour === 0) {
            return '12 AM';
        }

        if ($this->hour === 12) {
            return '12 PM';
        }

        if ($this->hour < 12) {
            return sprintf('%d AM', $this->hour);
        }

        return sprintf('%d PM', $this->hour - 12);
    }

    /**
     * Determine if this is a peak hour (6-9 AM or 4-7 PM).
     */
    public function isPeakHour(): bool
    {
        return ($this->hour >= 6 && $this->hour <= 9) || ($this->hour >= 16 && $this->hour <= 19);
    }

    /**
     * Get period label (Morning Rush, Midday, Evening Rush, Night).
     */
    public function getPeriodLabel(): string
    {
        return match (true) {
            $this->hour >= 6  && $this->hour < 10 => 'Morning Rush',
            $this->hour >= 10 && $this->hour < 16 => 'Midday',
            $this->hour >= 16 && $this->hour < 20 => 'Evening Rush',
            default                               => 'Night',
        };
    }
}
