<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check endpoint for ALB and monitoring.
 *
 * Provides a simple status endpoint to verify the application is running.
 */
final class HealthController extends AbstractController
{
    /**
     * Health check endpoint.
     *
     * Returns a simple JSON response indicating the application is operational.
     * Used by AWS Application Load Balancer health checks and monitoring systems.
     */
    #[Route('/api/healthz', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status'    => 'ok',
            'timestamp' => time(),
        ]);
    }
}
