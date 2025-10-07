<?php

declare(strict_types=1);

namespace App\Enum;

enum VehiclePunctualityLabel: string
{
    case AHEAD   = 'ahead';
    case ON_TIME = 'on_time';
    case LATE    = 'late';
}
