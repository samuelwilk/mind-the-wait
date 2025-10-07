<?php

namespace App\Enum;

enum GtfsSourceEnum: string
{
    case Zip    = 'zip';
    case Arcgis = 'arcgis';
}
