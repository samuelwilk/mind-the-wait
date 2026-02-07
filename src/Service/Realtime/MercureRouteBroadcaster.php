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
        private RouteTrackingService $trackingService,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Broadcast live updates for a specific route.
     *
     * @param Route $route Route to broadcast updates for
     *
     * @throws \Throwable If rendering or publishing fails
     */
    public function broadcastRoute(Route $route): void
    {
        try {
            // Get fresh snapshot
            $snapshot = $this->trackingService->snapshot($route->getGtfsId());

            // Render vehicle indicators component
            $indicatorsHtml = $this->twig->render('components/VehicleIndicators.html.twig', [
                'routeId'    => $route->getGtfsId(),
                'vehicles'   => $snapshot->vehicles,
                'mercureUrl' => '', // Not needed for Mercure updates (already connected)
            ]);

            // Publish Turbo Stream update to Mercure
            $this->publishTurboStream($route, 'vehicle-indicators', $indicatorsHtml);

            $this->logger->info('Broadcasted route updates via Mercure', [
                'route'    => $route->getGtfsId(),
                'vehicles' => count($snapshot->vehicles),
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
