<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RouteRepository;
use App\Service\Dashboard\RoutePerformanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * and active vehicle count. Cached for 5 minutes.
     */
    #[Route('/routes', name: 'routes_list', methods: ['GET'])]
    public function listRoutes(): JsonResponse
    {
        $routes = $this->performanceService->getRouteListWithMetrics();

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
