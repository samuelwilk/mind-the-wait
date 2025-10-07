<?php

declare(strict_types=1);

namespace App\Enum;

enum VehicleStatusColor: string
{
    case GREEN  = 'green';
    case YELLOW = 'yellow';
    case RED    = 'red';
}
