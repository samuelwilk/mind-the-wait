<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Schedule Realism Grade - measures how realistic a route's schedule is.
 *
 * Calculated as: Actual Travel Time / Scheduled Travel Time
 *
 * Example:
 * - Scheduled: 45 minutes
 * - Actual average: 60 minutes
 * - Ratio: 1.33 → "Severely Under-scheduled"
 */
enum ScheduleRealismGrade: string
{
    case OVER_SCHEDULED           = 'over_scheduled';
    case WELL_SCHEDULED           = 'well_scheduled';
    case REALISTIC                = 'realistic';
    case UNDER_SCHEDULED          = 'under_scheduled';
    case SEVERELY_UNDER_SCHEDULED = 'severely_under_scheduled';
    case INSUFFICIENT_DATA        = 'insufficient_data';

    /**
     * Determine schedule realism grade from ratio.
     *
     * @param float|null $ratio Actual time / Scheduled time (null if insufficient data)
     */
    public static function fromRatio(?float $ratio): self
    {
        if ($ratio === null) {
            return self::INSUFFICIENT_DATA;
        }

        return match (true) {
            $ratio < 0.85 => self::OVER_SCHEDULED,
            $ratio < 0.95 => self::WELL_SCHEDULED,
            $ratio < 1.05 => self::REALISTIC,
            $ratio < 1.15 => self::UNDER_SCHEDULED,
            default       => self::SEVERELY_UNDER_SCHEDULED,
        };
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::OVER_SCHEDULED           => 'Over-scheduled',
            self::WELL_SCHEDULED           => 'Well-scheduled',
            self::REALISTIC                => 'Realistic',
            self::UNDER_SCHEDULED          => 'Under-scheduled',
            self::SEVERELY_UNDER_SCHEDULED => 'Severely Under-scheduled',
            self::INSUFFICIENT_DATA        => 'Insufficient Data',
        };
    }

    /**
     * Detailed description for tooltips/help text.
     */
    public function description(): string
    {
        return match ($this) {
            self::OVER_SCHEDULED           => 'Schedule has too much time padding. Buses arrive consistently early.',
            self::WELL_SCHEDULED           => 'Schedule has appropriate padding. Good on-time performance expected.',
            self::REALISTIC                => 'Schedule accurately reflects actual travel time.',
            self::UNDER_SCHEDULED          => 'Schedule needs 5-15% more time. Buses consistently run late.',
            self::SEVERELY_UNDER_SCHEDULED => 'Schedule is unrealistic. Needs 15%+ more time to be achievable.',
            self::INSUFFICIENT_DATA        => 'Not enough data to calculate schedule realism (need 5+ days).',
        };
    }

    /**
     * Tailwind CSS color classes for UI display.
     */
    public function getColorClass(): string
    {
        return match ($this) {
            self::OVER_SCHEDULED           => 'bg-blue-100 text-blue-800',
            self::WELL_SCHEDULED           => 'bg-success-100 text-success-800',
            self::REALISTIC                => 'bg-success-100 text-success-800',
            self::UNDER_SCHEDULED          => 'bg-warning-100 text-warning-800',
            self::SEVERELY_UNDER_SCHEDULED => 'bg-danger-100 text-danger-800',
            self::INSUFFICIENT_DATA        => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Icon emoji for visual identification.
     */
    public function icon(): string
    {
        return match ($this) {
            self::OVER_SCHEDULED           => '⏰',
            self::WELL_SCHEDULED           => '✅',
            self::REALISTIC                => '🎯',
            self::UNDER_SCHEDULED          => '⚠️',
            self::SEVERELY_UNDER_SCHEDULED => '🚨',
            self::INSUFFICIENT_DATA        => '❓',
        };
    }

    /**
     * Get recommendation text for transit agencies.
     */
    public function recommendation(): string
    {
        return match ($this) {
            self::OVER_SCHEDULED           => 'Consider reducing schedule padding to improve efficiency.',
            self::WELL_SCHEDULED           => 'Schedule is well-calibrated. Maintain current padding.',
            self::REALISTIC                => 'Schedule is accurate. Monitor for seasonal variations.',
            self::UNDER_SCHEDULED          => 'Add 5-15% more time to schedule for better on-time performance.',
            self::SEVERELY_UNDER_SCHEDULED => 'Schedule needs major revision. Add 15%+ time or review route.',
            self::INSUFFICIENT_DATA        => 'Collect more data (5+ days) before making schedule changes.',
        };
    }
}
