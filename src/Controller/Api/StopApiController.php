<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\StopRepository;
use App\Service\Prediction\ArrivalPredictorInterface;
use OpenApi\Attributes as OA;
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
    #[OA\Get(
        summary: 'List stops',
        description: 'Returns all stops or stops for a specific route',
        tags: ['Stops']
    )]
    #[OA\Parameter(
        name: 'route_id',
        description: 'Filter stops by GTFS route ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of stops',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'stops',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '4000'),
                            new OA\Property(property: 'name', type: 'string', example: '19th Street / 2nd Avenue'),
                            new OA\Property(property: 'lat', type: 'number', format: 'float', example: 52.124263),
                            new OA\Property(property: 'lon', type: 'number', format: 'float', example: -106.666571),
                        ]
                    )
                ),
                new OA\Property(property: 'timestamp', type: 'integer', example: 1760939266),
            ]
        )
    )]
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
                'lat'  => $s->getLat(),
                'lon'  => $s->getLong(),
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
    #[OA\Get(
        summary: 'Get stop predictions',
        description: 'Returns next vehicle arrivals for a specific stop',
        tags: ['Stops']
    )]
    #[OA\Parameter(
        name: 'gtfsId',
        description: 'Stop GTFS ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Maximum number of predictions to return (default: 5)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 5)
    )]
    #[OA\Parameter(
        name: 'route_id',
        description: 'Filter predictions by route ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Arrival predictions for stop',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'stop',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '4000'),
                        new OA\Property(property: 'name', type: 'string', example: '19th Street / 2nd Avenue'),
                        new OA\Property(property: 'lat', type: 'number', format: 'float', example: 52.124263),
                        new OA\Property(property: 'lon', type: 'number', format: 'float', example: -106.666571),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'predictions',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'route_id', type: 'string', example: '14514'),
                            new OA\Property(property: 'route_short_name', type: 'string', example: '16'),
                            new OA\Property(property: 'arrival_time', type: 'string', format: 'date-time', example: '2025-10-19T12:30:00Z'),
                            new OA\Property(property: 'delay_sec', type: 'integer', example: 120),
                            new OA\Property(property: 'confidence', type: 'string', enum: ['HIGH', 'MEDIUM', 'LOW'], example: 'HIGH'),
                        ]
                    )
                ),
                new OA\Property(property: 'timestamp', type: 'integer', example: 1760939266),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Stop not found'
    )]
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
                'lat'  => $stop->getLat(),
                'lon'  => $stop->getLong(),
            ],
            'predictions' => array_map(fn ($p) => $p->toArray($now), $predictions),
            'timestamp'   => $now,
        ], headers: [
            'Cache-Control' => 'no-cache', // Always fresh
        ]);
    }
}
