<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Scheduler\InsightCacheWarmingMessage;
use App\Service\Dashboard\OverviewService;
use App\Service\Dashboard\WeatherAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles scheduled insight cache warming.
 */
#[AsMessageHandler]
final readonly class InsightCacheWarmingMessageHandler
{
    public function __construct(
        private OverviewService $overviewService,
        private WeatherAnalysisService $weatherAnalysisService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(InsightCacheWarmingMessage $message): void
    {
        $this->logger->info('Starting scheduled insight cache warming');

        try {
            // Generate overview page insights (2 insights)
            $this->overviewService->getSystemMetrics();
            $this->logger->info('Dashboard insights cached');

            // Generate weather impact page insights (5 insights)
            $this->weatherAnalysisService->getWeatherImpactInsights();
            $this->logger->info('Weather insights cached');

            $this->logger->info('AI insight cache warmed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to warm insight cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
