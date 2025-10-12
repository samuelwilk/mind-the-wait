<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Transit impact severity classification for weather conditions.
 */
enum TransitImpact: string
{
    case NONE     = 'none';
    case MINOR    = 'minor';
    case MODERATE = 'moderate';
    case SEVERE   = 'severe';

    public function getLabel(): string
    {
        return match ($this) {
            self::NONE     => 'No Impact',
            self::MINOR    => 'Minor Impact',
            self::MODERATE => 'Moderate Impact',
            self::SEVERE   => 'Severe Impact',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::NONE     => 'Clear conditions, normal operations',
            self::MINOR    => 'Slight delays possible (2-5 min)',
            self::MODERATE => 'Noticeable delays expected (5-15 min)',
            self::SEVERE   => 'Major delays, service disruptions (15+ min)',
        };
    }

    /**
     * Get color for UI display.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::NONE     => 'green',
            self::MINOR    => 'yellow',
            self::MODERATE => 'orange',
            self::SEVERE   => 'red',
        };
    }
}
