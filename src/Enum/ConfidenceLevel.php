<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Indicates confidence/quality of score calculation.
 */
enum ConfidenceLevel: string
{
    /**
     * HIGH: Score based on multi-vehicle headway calculation.
     * Most reliable - actual observed service frequency.
     */
    case HIGH = 'high';

    /**
     * MEDIUM: Score based on single-vehicle schedule adherence.
     * Reliable - compares vehicle position to scheduled times.
     */
    case MEDIUM = 'medium';

    /**
     * LOW: Score is default/estimated due to limited data.
     * Least reliable - no headway or schedule adherence available.
     */
    case LOW = 'low';
}
