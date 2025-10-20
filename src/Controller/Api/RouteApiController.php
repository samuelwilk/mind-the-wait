<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\CityRepository;
use App\Repository\RouteRepository;
use App\Service\Dashboard\RoutePerformanceService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function array_map;
use function time;

/**
 * Mobile API endpoints for iOS app - Route operations.
 *
 * Provides JSON APIs for route listing and detail views optimized for mobile consumption.
 */
#[Route('/api/v1', name: 'api_v1_')]
final class RouteApiController extends AbstractController
{
    public function __construct(
        private readonly RoutePerformanceService $performanceService,
    ) {
    }

    /**
     * Get list of all routes with current performance metrics.
     *
     * Returns route list with 30-day average performance, current grade,
     * and active vehicle count. Optionally filter by city slug.
     * Cached for 5 minutes.
     *
     * @param Request        $request  HTTP request
     * @param CityRepository $cityRepo City repository
     */
    #[Route('/routes', name: 'routes_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List routes',
        description: 'Returns all routes with performance metrics and grades',
        tags: ['Routes']
    )]
    #[OA\Parameter(
        name: 'city',
        description: 'Filter routes by city slug',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of routes with performance metrics',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'routes',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '14514'),
                            new OA\Property(property: 'short_name', type: 'string', example: '16'),
                            new OA\Property(property: 'long_name', type: 'string', example: 'Exhibition / 8th Street'),
                            new OA\Property(property: 'color', type: 'string', example: 'FF8000'),
                            new OA\Property(property: 'grade', type: 'string', enum: ['A', 'B', 'C', 'D', 'F'], example: 'A'),
                            new OA\Property(property: 'on_time_pct', type: 'number', format: 'float', example: 92.5),
                            new OA\Property(property: 'active_vehicles', type: 'integer', example: 3),
                        ]
                    )
                ),
                new OA\Property(property: 'timestamp', type: 'integer', example: 1760939266),
                new OA\Property(property: 'city', type: 'string', nullable: true, example: 'saskatoon'),
            ]
        )
    )]
    public function listRoutes(
        Request $request,
        CityRepository $cityRepo,
    ): JsonResponse {
        $citySlug = $request->query->get('city');
        $city     = null;

        // If city parameter provided, validate it exists
        if ($citySlug !== null) {
            $city = $cityRepo->findBySlug($citySlug);

            if ($city === null) {
                return $this->json(['error' => 'City not found'], 404);
            }
        }

        $routes = $this->performanceService->getRouteListWithMetrics($city);

        return $this->json([
            'routes' => array_map(fn ($r) => [
                'id'              => $r->routeId,
                'short_name'      => $r->shortName,
                'long_name'       => $r->longName,
                'color'           => $r->colour,
                'grade'           => $r->grade,
                'on_time_pct'     => $r->onTimePercentage,
                'active_vehicles' => $r->activeVehicles,
            ], $routes),
            'timestamp' => time(),
            'city'      => $city?->getSlug(),
        ], headers: [
            'Cache-Control' => 'public, max-age=300', // 5 min cache
        ]);
    }

    /**
     * Get detailed performance metrics for a specific route.
     *
     * Returns route metadata and 30-day performance statistics.
     * Charts are omitted for mobile (too heavy). Cached for 10 minutes.
     *
     * @param string          $gtfsId    Route GTFS ID
     * @param RouteRepository $routeRepo Route repository
     */
    #[Route('/routes/{gtfsId}', name: 'route_detail', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get route details',
        description: 'Returns detailed route metadata and 30-day performance statistics',
        tags: ['Routes']
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
        description: 'Route details with performance stats',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'route',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '14514'),
                        new OA\Property(property: 'short_name', type: 'string', example: '16'),
                        new OA\Property(property: 'long_name', type: 'string', example: 'Exhibition / 8th Street'),
                        new OA\Property(property: 'color', type: 'string', example: 'FF8000'),
                    ],
                    type: 'object'
                ),
                new OA\Property(
                    property: 'stats',
                    type: 'object',
                    description: '30-day performance statistics'
                ),
                new OA\Property(property: 'timestamp', type: 'integer', example: 1760940344),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Route not found'
    )]
    public function routeDetail(
        string $gtfsId,
        RouteRepository $routeRepo,
    ): JsonResponse {
        $route = $routeRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($route === null) {
            throw $this->createNotFoundException('Route not found');
        }

        $detail = $this->performanceService->getRouteDetail($route);

        return $this->json([
            'route' => [
                'id'         => $route->getGtfsId(),
                'short_name' => $route->getShortName(),
                'long_name'  => $route->getLongName(),
                'color'      => $route->getColour(),
            ],
            'stats'     => $detail->stats,
            'timestamp' => time(),
        ], headers: [
            'Cache-Control' => 'public, max-age=600', // 10 min
        ]);
    }
}
