<?php

declare(strict_types=1);

namespace App\Enum;

enum ScoreGradeEnum: string
{
    case A  = 'A';
    case B  = 'B';
    case C  = 'C';
    case D  = 'D';
    case F  = 'F';
    case NA = 'N/A';  // Insufficient data to calculate a grade

    public static function fromObserved(?int $observedHeadwaySec, int $vehicleCount, ?int $delaySec = null): self
    {
        // Multi-vehicle routes: grade based on headway
        if ($observedHeadwaySec !== null) {
            return $observedHeadwaySec <= 600 ? self::A
                : ($observedHeadwaySec <= 900 ? self::B
                    : ($observedHeadwaySec <= 1200 ? self::C : self::D));
        }

        // Single vehicle: grade based on schedule adherence (delay)
        if ($vehicleCount === 1) {
            if ($delaySec !== null) {
                return self::fromDelay($delaySec);
            }

            // No delay data available, assign neutral "C" grade
            return self::C;
        }

        // No vehicles or insufficient data
        return self::NA;
    }

    /**
     * Grade based on delay from schedule (for single-vehicle routes).
     *
     * @param int $delaySec Delay in seconds (positive = late, negative = early)
     */
    public static function fromDelay(int $delaySec): self
    {
        // Early or on-time (within ±60 seconds)
        if ($delaySec <= 60) {
            return self::A;
        }

        // Slightly late (1-3 minutes)
        if ($delaySec <= 180) {
            return self::B;
        }

        // Moderately late (3-5 minutes)
        if ($delaySec <= 300) {
            return self::C;
        }

        // Late (5-10 minutes)
        if ($delaySec <= 600) {
            return self::D;
        }

        // Very late (>10 minutes)
        return self::F;
    }
}
