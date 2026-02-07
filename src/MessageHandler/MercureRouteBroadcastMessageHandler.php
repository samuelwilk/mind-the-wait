<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Repository\RouteRepository;
use App\Scheduler\MercureRouteBroadcastMessage;
use App\Service\Realtime\MercureRouteBroadcaster;
use App\Service\Realtime\RealtimeSnapshotService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function count;

/**
 * Handles scheduled Mercure route broadcasts (every 5 seconds).
 *
 * Broadcasts live updates for all routes with active vehicles.
 * Optimized to fetch data once and distribute to all routes.
 */
#[AsMessageHandler]
final readonly class MercureRouteBroadcastMessageHandler
{
    public function __construct(
        private RealtimeSnapshotService $snapshotService,
        private RouteRepository $routeRepo,
        private MercureRouteBroadcaster $broadcaster,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MercureRouteBroadcastMessage $message): void
    {
        // Get enriched snapshot once (includes status for all vehicles)
        $snapshot    = $this->snapshotService->snapshot();
        $allVehicles = $snapshot['vehicles'] ?? [];

        if (count($allVehicles) === 0) {
            $this->logger->debug('No vehicles to broadcast');

            return;
        }

        // Group vehicles by route for efficient processing
        $vehiclesByRoute = [];
        foreach ($allVehicles as $vehicle) {
            $routeId = $vehicle['route'] ?? null;
            if ($routeId !== null) {
                $vehiclesByRoute[$routeId][] = $vehicle;
            }
        }

        $broadcastCount = 0;

        // Pre-fetch all route entities in one query
        $routeIds = array_keys($vehiclesByRoute);
        $routes   = $this->routeRepo->findBy(['gtfsId' => $routeIds]);
        $routeMap = [];
        foreach ($routes as $route) {
            $routeMap[$route->getGtfsId()] = $route;
        }

        // Broadcast each route with its vehicles
        foreach ($vehiclesByRoute as $routeGtfsId => $routeVehicles) {
            $route = $routeMap[$routeGtfsId] ?? null;
            if ($route === null) {
                $this->logger->warning('Route not found for broadcast', ['route' => $routeGtfsId]);

                continue;
            }

            try {
                // Pass vehicles directly - no need to re-fetch
                $this->broadcaster->broadcastRouteWithVehicles($route, $routeVehicles);
                ++$broadcastCount;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to broadcast route', [
                    'route'     => $routeGtfsId,
                    'error'     => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        $this->logger->debug('Mercure broadcast complete', [
            'routes'   => $broadcastCount,
            'vehicles' => count($allVehicles),
        ]);
    }
}
