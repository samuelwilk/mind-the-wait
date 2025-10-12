<?php

declare(strict_types=1);

namespace App\Service\Proximity;

/**
 * Calculates distances between geographic coordinates.
 */
final readonly class ProximityCalculator
{
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Calculate distance between two points using Haversine formula.
     *
     * @return float Distance in meters
     */
    public function distanceBetween(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad  = deg2rad($lat1);
        $lat2Rad  = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
