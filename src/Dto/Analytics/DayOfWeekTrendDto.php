<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

/**
 * Data transfer object for day-of-week performance trend.
 *
 * Aggregates system-wide performance by day of week
 * (Monday through Sunday).
 */
final readonly class DayOfWeekTrendDto
{
    private const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function __construct(
        public int $dayOfWeek,
        public float $avgOnTimePercentage,
        public int $avgDelaySec,
        public int $sampleCount,
    ) {
    }

    /**
     * Get human-readable day name.
     */
    public function getDayName(): string
    {
        return self::DAY_NAMES[$this->dayOfWeek] ?? 'Unknown';
    }

    /**
     * Get short day name (Mon, Tue, etc.).
     */
    public function getShortDayName(): string
    {
        return substr($this->getDayName(), 0, 3);
    }

    /**
     * Check if this is a weekend day.
     */
    public function isWeekend(): bool
    {
        return $this->dayOfWeek === 0 || $this->dayOfWeek === 6;
    }
}
