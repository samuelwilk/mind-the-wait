<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Entity\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

use function count;
use function preg_replace;
use function sprintf;
use function trim;

/**
 * Broadcasts route tracking updates via Mercure for live UI updates.
 *
 * Publishes Turbo Stream updates to Mercure hub for real-time vehicle positions,
 * stop timeline, and headway data.
 */
final readonly class MercureRouteBroadcaster
{
    public function __construct(
        private HubInterface $hub,
        private VehicleEnricherService $vehicleEnricher,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Broadcast live updates for a specific route with pre-filtered vehicles.
     *
     * @param Route $route    Route to broadcast updates for
     * @param array $vehicles Raw vehicle data already filtered for this route
     */
    public function broadcastRouteWithVehicles(Route $route, array $vehicles): void
    {
        try {
            // Enrich vehicles with arrival predictions (lightweight operation)
            $enrichedVehicles = $this->vehicleEnricher->enrichVehicles($vehicles);

            // Render just the pills container
            $pillsHtml = $this->twig->render('components/_vehicle_pills.html.twig', [
                'routeId'  => $route->getGtfsId(),
                'vehicles' => $enrichedVehicles,
            ]);

            // Publish Turbo Stream update
            $this->publishTurboStream($route, sprintf('vehicle-pills-%s', $route->getGtfsId()), $pillsHtml);

            $this->logger->info('Broadcasted route updates via Mercure', [
                'route'    => $route->getGtfsId(),
                'vehicles' => count($enrichedVehicles),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to broadcast route updates', [
                'route'     => $route->getGtfsId(),
                'error'     => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Publish a Turbo Stream update to Mercure.
     *
     * @param Route  $route  Route being updated
     * @param string $target DOM target ID
     * @param string $html   Rendered HTML content
     */
    private function publishTurboStream(Route $route, string $target, string $html): void
    {
        // Minify HTML to single line
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);

        // Build Turbo Stream
        $turboStream = sprintf(
            '<turbo-stream action="replace" target="%s"><template>%s</template></turbo-stream>',
            $target,
            $html
        );

        // Publish to Mercure with route-specific topic
        $update = new Update(
            topics: [sprintf('route/%s', $route->getGtfsId())],
            data: $turboStream,
            private: false, // Public updates (no auth required)
        );

        $this->hub->publish($update);
    }
}
