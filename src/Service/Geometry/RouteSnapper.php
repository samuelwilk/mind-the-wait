<?php

declare(strict_types=1);

namespace App\Service\Geometry;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;

use function count;

use const PHP_FLOAT_MAX;

/**
 * Snaps a GPS lat/lon to the nearest point on a route shape polyline.
 *
 * Returns the interpolated distance-traveled along the route, which can
 * be compared against stop_time.shape_dist_traveled to determine which
 * stops a vehicle has passed.
 */
final readonly class RouteSnapper
{
    private const EARTH_RADIUS_KM = 6371.0;
    private const SHAPE_CACHE_TTL = 3600;

    public function __construct(
        private EntityManagerInterface $em,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Snap a GPS position to a shape polyline.
     *
     * @return array{dist_traveled: float, perp_distance_km: float}|null
     *                                                                   dist_traveled = interpolated distance along the shape
     *                                                                   perp_distance_km = perpendicular distance from route (for off-route detection)
     */
    public function snap(string $shapeId, float $lat, float $lon): ?array
    {
        $points = $this->getShapePoints($shapeId);
        if (count($points) < 2) {
            return null;
        }

        $bestDist     = PHP_FLOAT_MAX;
        $bestTraveled = 0.0;

        for ($i = 0, $n = count($points) - 1; $i < $n; ++$i) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];

            // Find closest point on the segment p1-p2 to (lat, lon)
            [$closestLat, $closestLon, $t] = $this->closestPointOnSegment(
                $lat, $lon,
                $p1['lat'], $p1['lon'],
                $p2['lat'], $p2['lon'],
            );

            $distance = $this->haversine($lat, $lon, $closestLat, $closestLon);

            if ($distance < $bestDist) {
                $bestDist = $distance;
                // Interpolate dist_traveled between the two points
                $bestTraveled = $p1['dist'] + $t * ($p2['dist'] - $p1['dist']);
            }
        }

        if ($bestDist === PHP_FLOAT_MAX) {
            return null;
        }

        return [
            'dist_traveled'    => $bestTraveled,
            'perp_distance_km' => $bestDist,
        ];
    }

    /**
     * Get shape polyline points (cached).
     *
     * @return list<array{lat: float, lon: float, dist: float}>
     */
    private function getShapePoints(string $shapeId): array
    {
        $cacheKey = 'shape_points_'.md5($shapeId);
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $conn = $this->em->getConnection();
        $rows = $conn->executeQuery(
            'SELECT lat, lon, dist_traveled FROM shape WHERE shape_id = :id ORDER BY sequence',
            ['id' => $shapeId],
        )->fetchAllAssociative();

        $points = [];
        foreach ($rows as $row) {
            $points[] = [
                'lat'  => (float) $row['lat'],
                'lon'  => (float) $row['lon'],
                'dist' => (float) $row['dist_traveled'],
            ];
        }

        $item->set($points);
        $item->expiresAfter(self::SHAPE_CACHE_TTL);
        $this->cache->save($item);

        return $points;
    }

    /**
     * Find the closest point on a line segment to a given point.
     *
     * @return array{0: float, 1: float, 2: float} [lat, lon, t] where t is 0..1 along segment
     */
    private function closestPointOnSegment(
        float $pLat, float $pLon,
        float $aLat, float $aLon,
        float $bLat, float $bLon,
    ): array {
        $dx = $bLon - $aLon;
        $dy = $bLat - $aLat;

        if ($dx === 0.0 && $dy === 0.0) {
            return [$aLat, $aLon, 0.0];
        }

        // Project point onto line, clamped to [0, 1]
        $t = (($pLon - $aLon) * $dx + ($pLat - $aLat) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        return [
            $aLat + $t * $dy,
            $aLon + $t * $dx,
            $t,
        ];
    }

    /**
     * Haversine distance in km.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2)                                              ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
