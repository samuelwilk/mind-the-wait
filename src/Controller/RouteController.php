<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RouteRepository;
use App\Repository\TripRepository;
use App\Service\Dashboard\RoutePerformanceService;
use App\Service\Realtime\RouteTrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function count;

/**
 * Route list and detail pages.
 */
#[Route('/routes', name: 'app_routes_')]
final class RouteController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepo,
        private readonly TripRepository $tripRepo,
        private readonly RoutePerformanceService $performanceService,
        private readonly RouteTrackingService $trackingService,
    ) {
    }

    /**
     * Route list page with search/filter/sort.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        // Live Component will handle search and sort
        return $this->render('dashboard/route_list.html.twig', [
            'search' => $request->query->get('search', ''),
            'sort'   => $request->query->get('sort', 'name'),
        ]);
    }

    /**
     * Route detail page with performance charts.
     */
    #[Route('/{gtfsId}', name: 'show', methods: ['GET'])]
    public function show(string $gtfsId): Response
    {
        // Get route entity
        $route = $this->routeRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($route === null) {
            throw $this->createNotFoundException('Route not found');
        }

        // Get comprehensive route performance data
        $routeDetail = $this->performanceService->getRouteDetail($route);

        return $this->render('dashboard/route_detail.html.twig', [
            'route'       => $route,
            'routeDetail' => $routeDetail,
        ]);
    }

    /**
     * Live tracking page for route.
     */
    #[Route('/{gtfsId}/live', name: 'live', methods: ['GET'])]
    public function live(string $gtfsId): Response
    {
        // Get route entity
        $route = $this->routeRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($route === null) {
            throw $this->createNotFoundException('Route not found');
        }

        // Get real-time tracking snapshot
        $snapshot = $this->trackingService->snapshot($gtfsId);

        // Get route direction headsigns (e.g., "University (Hub) ⇔ Depot")
        $headsigns      = $this->tripRepo->getRouteDirectionHeadsigns($gtfsId);
        $routeDirection = null;
        if ($headsigns && count($headsigns) === 2) {
            $routeDirection = implode(' ⇔ ', $headsigns);
        }

        return $this->render('dashboard/route_live.html.twig', [
            'route'          => $route,
            'snapshot'       => $snapshot,
            'routeDirection' => $routeDirection,
        ]);
    }
}
