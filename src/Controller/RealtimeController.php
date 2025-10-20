<?php

namespace App\Controller;

use App\Service\Realtime\RealtimeSnapshotService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

use function array_filter;
use function array_values;
use function function_exists;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_UNESCAPED_UNICODE;

final class RealtimeController extends AbstractController
{
    public function __construct(private readonly RealtimeSnapshotService $snapshotService)
    {
    }

    /**
     * Get realtime vehicle positions, optionally filtered by route.
     *
     * Query parameters:
     * - route_id: Filter vehicles by route GTFS ID (optional)
     * - city: City slug (optional, defaults to 'saskatoon')
     *
     * @param Request $request HTTP request
     */
    #[Route('/api/realtime', name: 'api_realtime', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get realtime transit data',
        description: 'Returns current vehicle positions, trip updates, and service alerts',
        tags: ['Realtime']
    )]
    #[OA\Parameter(
        name: 'route_id',
        description: 'Filter vehicles by route GTFS ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'city',
        description: 'City slug (default: saskatoon)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', default: 'saskatoon')
    )]
    #[OA\Response(
        response: 200,
        description: 'Realtime snapshot',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'ts', type: 'integer', description: 'Unix timestamp', example: 1760940344),
                new OA\Property(
                    property: 'vehicles',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: 'veh-123'),
                            new OA\Property(property: 'route', type: 'string', example: '14514'),
                            new OA\Property(property: 'lat', type: 'number', format: 'float', example: 52.124263),
                            new OA\Property(property: 'lon', type: 'number', format: 'float', example: -106.666571),
                            new OA\Property(property: 'bearing', type: 'number', format: 'float', example: 180.5),
                            new OA\Property(property: 'speed', type: 'number', format: 'float', example: 35.5),
                            new OA\Property(property: 'delay_sec', type: 'integer', example: 120),
                            new OA\Property(property: 'status', type: 'string', enum: ['on_time', 'late', 'early'], example: 'late'),
                            new OA\Property(property: 'ts', type: 'integer', example: 1760940344),
                        ]
                    )
                ),
                new OA\Property(
                    property: 'trips',
                    type: 'array',
                    items: new OA\Items(type: 'object')
                ),
                new OA\Property(
                    property: 'alerts',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'cause', type: 'integer', example: 10),
                            new OA\Property(property: 'effect', type: 'integer', example: 9),
                        ]
                    )
                ),
            ]
        )
    )]
    public function realtime(Request $request): JsonResponse
    {
        $citySlug = $request->query->get('city', 'saskatoon');
        $snapshot = $this->snapshotService->snapshot($citySlug);
        $routeId  = $request->query->get('route_id');

        // Filter vehicles if route_id provided
        if ($routeId !== null && isset($snapshot['vehicles'])) {
            $snapshot['vehicles'] = array_values(array_filter(
                $snapshot['vehicles'],
                fn ($v) => ($v['route'] ?? null) === $routeId
            ));
        }

        return $this->json(
            $snapshot,
            200,
            ['Content-Type'        => 'application/json'],
            ['json_encode_options' => JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE]
        );
    }

    /**
     * Server-Sent Events stream for realtime updates.
     *
     * Query parameters:
     * - city: City slug (optional, defaults to 'saskatoon')
     *
     * @param Request $request HTTP request
     */
    #[Route('/events', name: 'api_realtime_events', methods: ['GET'])]
    public function events(Request $request): StreamedResponse
    {
        $citySlug = $request->query->get('city', 'saskatoon');

        $response = new StreamedResponse(function () use ($citySlug): void {
            // The response headers are set outside the callback—don't call header() here.
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $last = 0;
            while (true) {
                // Client disconnected?
                if (function_exists('connection_aborted') && connection_aborted()) {
                    break;
                }

                $snap = $this->snapshotService->snapshot($citySlug);
                if ($snap['ts'] > $last) {
                    echo "event: snapshot\n";
                    echo 'data: '.json_encode($snap, JSON_UNESCAPED_UNICODE)."\n\n";
                    @ob_flush();
                    @flush();
                    $last = $snap['ts'];
                }

                usleep(900_000); // ~0.9s
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
