<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\StopRepository;
use App\Service\Prediction\ArrivalPredictorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;
use function time;

/**
 * Mobile API endpoints for iOS app - Stop operations.
 *
 * Provides JSON APIs for stop listing and arrival predictions optimized for mobile consumption.
 */
#[Route('/api/v1', name: 'api_v1_')]
final class StopApiController extends AbstractController
{
    /**
     * Get list of stops, optionally filtered by route.
     *
     * Returns all stops or stops served by a specific route if route_id query parameter is provided.
     * Cached for 1 hour since stop data rarely changes.
     *
     * @param Request        $request  HTTP request
     * @param StopRepository $stopRepo Stop repository
     */
    #[Route('/stops', name: 'stops_list', methods: ['GET'])]
    public function listStops(
        Request $request,
        StopRepository $stopRepo,
    ): JsonResponse {
        $routeId = $request->query->get('route_id');

        // If route_id provided, filter stops to that route
        if ($routeId) {
            $stops = $stopRepo->findByRoute($routeId);
        } else {
            $stops = $stopRepo->findAll();
        }

        return $this->json([
            'stops' => array_map(fn ($s) => [
                'id'   => $s->getGtfsId(),
                'name' => $s->getName(),
                'lat'  => $s->getLatitude(),
                'lon'  => $s->getLongitude(),
            ], $stops),
            'timestamp' => time(),
        ], headers: [
            'Cache-Control' => 'public, max-age=3600', // 1 hour (stops rarely change)
        ]);
    }

    /**
     * Get arrival predictions for a specific stop.
     *
     * Returns next arrivals (up to limit) for vehicles arriving at this stop.
     * Includes route info, predicted arrival time, delay, and confidence level.
     * Always fresh (no cache).
     *
     * @param string                    $gtfsId    Stop GTFS ID
     * @param Request                   $request   HTTP request
     * @param StopRepository            $stopRepo  Stop repository
     * @param ArrivalPredictorInterface $predictor Arrival prediction service
     */
    #[Route('/stops/{gtfsId}/predictions', name: 'stop_predictions', methods: ['GET'])]
    public function stopPredictions(
        string $gtfsId,
        Request $request,
        StopRepository $stopRepo,
        ArrivalPredictorInterface $predictor,
    ): JsonResponse {
        $stop = $stopRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($stop === null) {
            throw $this->createNotFoundException('Stop not found');
        }

        // Get limit from query param (default: 5)
        $limit   = (int) $request->query->get('limit', 5);
        $routeId = $request->query->get('route_id'); // Optional filter by route

        // Get next arrivals
        $predictions = $predictor->predictArrivalsForStop($gtfsId, $limit, $routeId);

        $now = time();

        return $this->json([
            'stop' => [
                'id'   => $gtfsId,
                'name' => $stop->getName(),
                'lat'  => $stop->getLatitude(),
                'lon'  => $stop->getLongitude(),
            ],
            'predictions' => array_map(fn ($p) => $p->toArray($now), $predictions),
            'timestamp'   => $now,
        ], headers: [
            'Cache-Control' => 'no-cache', // Always fresh
        ]);
    }
}
