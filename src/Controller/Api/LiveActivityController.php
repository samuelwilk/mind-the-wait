<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RealtimeRepository;
use App\Repository\RouteRepository;
use App\Repository\StopRepository;
use App\Service\Prediction\ArrivalPredictorInterface;
use App\Service\Realtime\VehicleStatusService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use function array_filter;
use function array_map;
use function count;
use function round;
use function time;

/**
 * API endpoints for iOS Live Activities (Dynamic Island).
 *
 * Provides minimal payload responses optimized for Live Activities
 * which have strict size limits (<4KB) and fast update requirements.
 */
#[Route('/api/v1/live-activity', name: 'api_v1_live_activity_')]
final class LiveActivityController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepo,
        private readonly StopRepository $stopRepo,
        private readonly RealtimeRepository $realtimeRepo,
        private readonly VehicleStatusService $vehicleStatus,
        private readonly ArrivalPredictorInterface $arrivalPredictor,
    ) {
    }

    /**
     * Get route status data optimized for Live Activities (Dynamic Island).
     * Returns only essential data with minimal payload size.
     *
     * Response is always fresh (no caching) for real-time accuracy.
     *
     * @param string $gtfsId Route GTFS ID
     */
    #[Route('/route/{gtfsId}', name: 'route_status', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get route status for Live Activity',
        description: 'Returns minimal route status payload optimized for iOS Live Activities (<4KB)',
        tags: ['Live Activities']
    )]
    #[OA\Parameter(
        name: 'gtfsId',
        description: 'Route GTFS ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Route status summary',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'route_id', type: 'string', example: '14514'),
                new OA\Property(property: 'short_name', type: 'string', example: '16'),
                new OA\Property(property: 'color', type: 'string', example: 'FF8000'),
                new OA\Property(property: 'active_vehicles', type: 'integer', example: 5),
                new OA\Property(property: 'on_time_vehicles', type: 'integer', example: 3),
                new OA\Property(property: 'late_vehicles', type: 'integer', example: 2),
                new OA\Property(property: 'early_vehicles', type: 'integer', example: 0),
                new OA\Property(property: 'health_percent', type: 'integer', example: 60),
                new OA\Property(property: 'timestamp', type: 'integer', example: 1760940344),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Route not found'
    )]
    public function routeStatus(string $gtfsId): JsonResponse
    {
        $route = $this->routeRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($route === null) {
            return $this->json(['error' => 'Route not found'], 404);
        }

        // Get realtime vehicles for this route
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicles = array_filter(
            $snapshot['vehicles'] ?? [],
            fn (array $v) => ($v['route'] ?? null) === $gtfsId
        );

        // Count vehicles by status
        $onTimeCount = 0;
        $lateCount   = 0;
        $earlyCount  = 0;

        foreach ($vehicles as $vehicle) {
            $status   = $this->vehicleStatus->buildStatus($vehicle);
            $delaySec = $status['delay_sec'] ?? null;

            if ($delaySec === null) {
                continue;
            }

            if ($delaySec > 180) {
                ++$lateCount;
            } elseif ($delaySec < -180) {
                ++$earlyCount;
            } else {
                ++$onTimeCount;
            }
        }

        $totalVehicles = count($vehicles);
        $healthPercent = $totalVehicles > 0
            ? (int) round(($onTimeCount / $totalVehicles) * 100)
            : 0;

        // Minimal payload for Live Activities (keep under 4KB)
        return $this->json([
            'route_id'         => $gtfsId,
            'short_name'       => $route->getShortName(),
            'color'            => $route->getColour(),
            'active_vehicles'  => $totalVehicles,
            'on_time_vehicles' => $onTimeCount,
            'late_vehicles'    => $lateCount,
            'early_vehicles'   => $earlyCount,
            'health_percent'   => $healthPercent,
            'timestamp'        => $snapshot['timestamp'] ?? time(),
        ], headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Get next arrival predictions for a stop (for Live Activity countdowns).
     *
     * Returns only the next 3 arrivals with minimal data for Dynamic Island display.
     * Response is always fresh (no caching) for real-time countdowns.
     *
     * @param string $gtfsId Stop GTFS ID
     */
    #[Route('/stop/{gtfsId}/next-arrivals', name: 'stop_next_arrivals', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get next arrivals for Live Activity',
        description: 'Returns next 3 arrivals with minimal payload for Live Activity countdown',
        tags: ['Live Activities']
    )]
    #[OA\Parameter(
        name: 'gtfsId',
        description: 'Stop GTFS ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Next arrivals at stop',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'stop_id', type: 'string', example: '4000'),
                new OA\Property(property: 'stop_name', type: 'string', example: '19th Street / 2nd Avenue'),
                new OA\Property(
                    property: 'arrivals',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'route_short_name', type: 'string', example: '16'),
                            new OA\Property(property: 'seconds_until', type: 'integer', example: 180),
                            new OA\Property(property: 'confidence', type: 'string', enum: ['HIGH', 'MEDIUM', 'LOW'], example: 'HIGH'),
                        ]
                    )
                ),
                new OA\Property(property: 'timestamp', type: 'integer', example: 1760940344),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Stop not found'
    )]
    public function stopNextArrivals(string $gtfsId): JsonResponse
    {
        $stop = $this->stopRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($stop === null) {
            return $this->json(['error' => 'Stop not found'], 404);
        }

        // Get next 3 arrivals only (Live Activities have strict size limits)
        $predictions = $this->arrivalPredictor->predictArrivalsForStop($gtfsId, limit: 3);

        return $this->json([
            'stop_id'   => $gtfsId,
            'stop_name' => $stop->getName(),
            'arrivals'  => array_map(fn ($p) => [
                'route_short_name' => $p->routeShortName,
                'seconds_until'    => (int) $p->predictedArrivalAt->getTimestamp() - time(),
                'confidence'       => $p->confidence->value,
            ], $predictions),
            'timestamp' => time(),
        ], headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
