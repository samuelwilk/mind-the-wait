<?php

namespace App\Enum;

enum RouteTypeEnum: int
{
    case Tram       = 0;  // Tram, Streetcar, Light rail
    case Subway     = 1;  // Subway / Metro
    case Rail       = 2;  // Rail
    case Bus        = 3;  // Bus
    case Ferry      = 4;  // Ferry
    case CableCar   = 5;  // Cable tram / cable car
    case Gondola    = 6;  // Aerial lift / suspended cable car
    case Funicular  = 7;  // Funicular
    case Trolleybus = 11; // Trolleybus
    case Monorail   = 12; // Monorail
}
