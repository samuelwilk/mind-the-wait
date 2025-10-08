<?php

declare(strict_types=1);

namespace App\Enum;

enum PredictionConfidence: string
{
    case HIGH   = 'high';   // From GTFS-RT TripUpdate predictions
    case MEDIUM = 'medium'; // From GPS interpolation
    case LOW    = 'low';    // From static schedule only
}
