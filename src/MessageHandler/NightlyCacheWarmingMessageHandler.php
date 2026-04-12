<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Dto\Analytics\DateRangeDto;
use App\Scheduler\NightlyCacheWarmingMessage;
use App\Service\Dashboard\AnalyticsService;
use App\Service\Dashboard\OverviewService;
use App\Service\Dashboard\WeatherAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles scheduled cache warming (insights + analytics).
 *
 * Runs nightly at 2 AM via NightlyCacheWarmingSchedule.
 */
#[AsMessageHandler]
final readonly class NightlyCacheWarmingMessageHandler
{
    public function __construct(
        private OverviewService $overviewService,
        private WeatherAnalysisService $weatherAnalysisService,
        private AnalyticsService $analyticsService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NightlyCacheWarmingMessage $message): void
    {
        $this->logger->info('Starting scheduled cache warming');

        try {
            // AI insights
            $this->overviewService->getSystemMetrics();
            $this->logger->info('Dashboard insights cached');

            $this->weatherAnalysisService->getWeatherImpactInsights();
            $this->logger->info('Weather insights cached');

            // Analytics presets (24-hour cache)
            $presets = [
                DateRangeDto::PRESET_LAST_30_DAYS,
                DateRangeDto::PRESET_LAST_60_DAYS,
                DateRangeDto::PRESET_LAST_90_DAYS,
                DateRangeDto::PRESET_ALL_TIME,
            ];
            foreach ($presets as $preset) {
                $dateRange = DateRangeDto::fromPreset($preset);
                $this->analyticsService->getAnalyticsPageData($dateRange);
                $this->analyticsService->getAvailableRoutes($dateRange);
            }
            $this->logger->info('Analytics cache warmed for all presets');

            $this->logger->info('All caches warmed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to warm caches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
