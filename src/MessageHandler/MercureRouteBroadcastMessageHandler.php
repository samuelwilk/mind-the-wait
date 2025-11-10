<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Repository\RealtimeRepository;
use App\Repository\RouteRepository;
use App\Scheduler\MercureRouteBroadcastMessage;
use App\Service\Realtime\MercureRouteBroadcaster;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function array_unique;
use function count;

/**
 * Handles scheduled Mercure route broadcasts (every 5 seconds).
 *
 * Broadcasts live updates for all routes with active vehicles.
 */
#[AsMessageHandler]
final readonly class MercureRouteBroadcastMessageHandler
{
    public function __construct(
        private RealtimeRepository $rt,
        private RouteRepository $routeRepo,
        private MercureRouteBroadcaster $broadcaster,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(MercureRouteBroadcastMessage $message): void
    {
        // Get all vehicles from Redis
        $vehicles = $this->rt->getVehicles();

        if (count($vehicles) === 0) {
            $this->logger->debug('No vehicles to broadcast');

            return;
        }

        // Extract unique route IDs from VehicleDto objects
        $routeIds = array_unique(
            array_filter(
                array_map(fn ($v) => $v->routeId, $vehicles)
            )
        );

        $broadcastCount = 0;

        foreach ($routeIds as $routeGtfsId) {
            // Find route entity
            $route = $this->routeRepo->findOneBy(['gtfsId' => $routeGtfsId]);
            if ($route === null) {
                $this->logger->warning('Route not found for broadcast', ['route' => $routeGtfsId]);

                continue;
            }

            try {
                // Broadcast updates for this route
                $this->broadcaster->broadcastRoute($route);
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
            'vehicles' => count($vehicles),
        ]);
    }
}
