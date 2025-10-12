<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RealtimeRepository;
use App\Repository\StopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use function array_slice;
use function count;
use function sqrt;

#[Route('/api/stops')]
final class StopController extends AbstractController
{
    public function __construct(
        private readonly StopRepository $stopRepo,
        private readonly RealtimeRepository $realtimeRepo,
    ) {
    }

    #[Route('', name: 'stops_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $lat  = $request->query->get('lat');
        $lon  = $request->query->get('lon');
        $name = $request->query->get('name');

        if ($lat !== null && $lon !== null) {
            return $this->searchByLocation((float) $lat, (float) $lon, $request->query->getInt('limit', 10));
        }

        if ($name !== null) {
            return $this->searchByName($name, $request->query->getInt('limit', 20));
        }

        return $this->json(['error' => 'Provide either lat/lon or name parameter'], 400);
    }

    #[Route('/{stopId}/nearby', name: 'stops_nearby_vehicles', methods: ['GET'])]
    public function nearbyVehicles(string $stopId, Request $request): JsonResponse
    {
        $stop = $this->stopRepo->findOneBy(['gtfsId' => $stopId]);
        if (!$stop) {
            return $this->json(['error' => 'Stop not found'], 404);
        }

        $radiusMeters = $request->query->getInt('radius', 500);
        $vehicles     = $this->realtimeRepo->getVehicles();

        $nearby = [];
        foreach ($vehicles as $vehicle) {
            if ($vehicle->lat === null || $vehicle->lon === null) {
                continue;
            }

            $distanceMeters = $this->haversineDistanceMeters(
                $stop->getLat(),
                $stop->getLong(),
                $vehicle->lat,
                $vehicle->lon
            );

            if ($distanceMeters <= $radiusMeters) {
                $nearby[] = [
                    'route_id'        => $vehicle->routeId,
                    'trip_id'         => $vehicle->tripId,
                    'distance_meters' => round($distanceMeters),
                    'lat'             => $vehicle->lat,
                    'lon'             => $vehicle->lon,
                    'timestamp'       => $vehicle->timestamp,
                ];
            }
        }

        // Sort by distance
        usort($nearby, fn ($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);

        return $this->json([
            'stop_id'   => $stopId,
            'stop_name' => $stop->getName(),
            'stop_lat'  => $stop->getLat(),
            'stop_lon'  => $stop->getLong(),
            'radius_m'  => $radiusMeters,
            'count'     => count($nearby),
            'vehicles'  => $nearby,
        ]);
    }

    private function searchByLocation(float $lat, float $lon, int $limit): JsonResponse
    {
        $allStops = $this->stopRepo->findAll();
        $results  = [];

        foreach ($allStops as $stop) {
            $distance  = $this->haversineDistance($lat, $lon, $stop->getLat(), $stop->getLong());
            $results[] = [
                'gtfs_id'     => $stop->getGtfsId(),
                'name'        => $stop->getName(),
                'lat'         => $stop->getLat(),
                'lon'         => $stop->getLong(),
                'distance_km' => round($distance, 2),
            ];
        }

        // Sort by distance
        usort($results, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $this->json(array_slice($results, 0, $limit));
    }

    private function searchByName(string $name, int $limit): JsonResponse
    {
        $stops = $this->stopRepo->createQueryBuilder('s')
            ->where('LOWER(s.name) LIKE LOWER(:name)')
            ->setParameter('name', '%'.$name.'%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn ($stop) => [
            'gtfs_id' => $stop->getGtfsId(),
            'name'    => $stop->getName(),
            'lat'     => $stop->getLat(),
            'lon'     => $stop->getLong(),
        ], $stops));
    }

    /**
     * Calculate distance between two lat/lon points using Haversine formula.
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Calculate distance between two lat/lon points in meters.
     */
    private function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return $this->haversineDistance($lat1, $lon1, $lat2, $lon2) * 1000;
    }
}
