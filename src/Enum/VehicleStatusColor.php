<?php

declare(strict_types=1);

namespace App\Enum;

enum VehicleStatusColor: string
{
    case GREEN  = 'green';  // way early
    case BLUE   = 'blue';   // slightly early
    case YELLOW = 'yellow'; // on time
    case ORANGE = 'orange'; // slightly late
    case RED    = 'red';    // late
    case PURPLE = 'purple'; // catastrophically late
}
