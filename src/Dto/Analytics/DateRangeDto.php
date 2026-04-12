<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function sprintf;

/**
 * Data transfer object for date range selection in analytics.
 *
 * Provides preset date ranges and custom date range functionality
 * for filtering analytics data.
 */
final readonly class DateRangeDto
{
    public const PRESET_LAST_30_DAYS = 'last_30_days';
    public const PRESET_LAST_60_DAYS = 'last_60_days';
    public const PRESET_LAST_90_DAYS = 'last_90_days';
    public const PRESET_ALL_TIME     = 'all_time';
    public const PRESET_CUSTOM       = 'custom';

    public function __construct(
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public string $preset = self::PRESET_CUSTOM,
    ) {
    }

    /**
     * Create a date range from a preset identifier.
     */
    public static function fromPreset(string $preset): self
    {
        $today = new \DateTimeImmutable('today');

        return match ($preset) {
            self::PRESET_LAST_30_DAYS => new self(
                startDate: $today->modify('-30 days'),
                endDate: $today->modify('+1 day'),
                preset: $preset,
            ),
            self::PRESET_LAST_60_DAYS => new self(
                startDate: $today->modify('-60 days'),
                endDate: $today->modify('+1 day'),
                preset: $preset,
            ),
            self::PRESET_LAST_90_DAYS => new self(
                startDate: $today->modify('-90 days'),
                endDate: $today->modify('+1 day'),
                preset: $preset,
            ),
            self::PRESET_ALL_TIME => new self(
                startDate: new \DateTimeImmutable('2025-10-15'),
                endDate: $today->modify('+1 day'),
                preset: $preset,
            ),
            default => self::fromPreset(self::PRESET_ALL_TIME),
        };
    }

    /**
     * Create a date range from custom dates.
     */
    public static function custom(\DateTimeImmutable $start, \DateTimeImmutable $end): self
    {
        return new self(
            startDate: $start,
            endDate: $end,
            preset: self::PRESET_CUSTOM,
        );
    }

    /**
     * Get all available presets with labels.
     *
     * @return array<string, string>
     */
    public static function getPresets(): array
    {
        return [
            self::PRESET_LAST_30_DAYS => 'Last 30 Days',
            self::PRESET_LAST_60_DAYS => 'Last 60 Days',
            self::PRESET_LAST_90_DAYS => 'Last 90 Days',
            self::PRESET_ALL_TIME     => 'All Time',
        ];
    }

    /**
     * Get formatted date range label.
     */
    public function getLabel(): string
    {
        if ($this->preset !== self::PRESET_CUSTOM && isset(self::getPresets()[$this->preset])) {
            return self::getPresets()[$this->preset];
        }

        return sprintf(
            '%s - %s',
            $this->startDate->format('M j, Y'),
            $this->endDate->modify('-1 day')->format('M j, Y')
        );
    }

    /**
     * Get the number of days in the range.
     */
    public function getDayCount(): int
    {
        return (int) $this->startDate->diff($this->endDate)->days;
    }
}
