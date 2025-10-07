<?php

namespace App\Controller;

use App\Service\Realtime\RealtimeSnapshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

use function function_exists;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_UNESCAPED_UNICODE;

final class RealtimeController extends AbstractController
{
    public function __construct(private readonly RealtimeSnapshotService $snapshotService)
    {
    }

    #[Route('/api/realtime', name: 'api_realtime', methods: ['GET'])]
    public function realtime(): JsonResponse
    {
        return $this->json(
            $this->snapshotService->snapshot(),
            200,
            ['Content-Type'        => 'application/json'],
            ['json_encode_options' => JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE]
        );
    }

    #[Route('/events', name: 'api_realtime_events', methods: ['GET'])]
    public function events(): StreamedResponse
    {
        $response = new StreamedResponse(function (): void {
            // The response headers are set outside the callback—don’t call header() here.
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $last = 0;
            while (true) {
                // Client disconnected?
                if (function_exists('connection_aborted') && connection_aborted()) {
                    break;
                }

                $snap = $this->snapshotService->snapshot();
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
