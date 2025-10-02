<?php

namespace App\Enum;

enum GtfsKindEnum: string
{
    case Vehicles = 'vehicles';
    case Trips    = 'trips';
    case Alerts   = 'alerts';

    public function envVar(): string
    {
        return match ($this) {
            self::Vehicles => 'GTFS_RT_VEHICLES',
            self::Trips    => 'GTFS_RT_TRIPS',
            self::Alerts   => 'GTFS_RT_ALERTS',
        };
    }

    public function redisKey(): string
    {
        return "mtw:{$this->value}";
    }
}
