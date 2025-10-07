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

    public static function fromObserved(?int $observedHeadwaySec, int $vehicleCount): self
    {
        if ($observedHeadwaySec !== null) {
            return $observedHeadwaySec <= 600 ? self::A
                : ($observedHeadwaySec <= 900 ? self::B
                    : ($observedHeadwaySec <= 1200 ? self::C : self::D));
        }

        // Insufficient data: cannot calculate meaningful headway
        // Need at least 2 vehicles with timestamps to compute headway
        return self::NA;
    }
}
