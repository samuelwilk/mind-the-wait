<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\RouteMetricDto;
use App\Service\Dashboard\OverviewService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live component that displays real-time route performance data.
 *
 * Auto-updates every 30 seconds to show current top performers and routes needing attention.
 */
#[AsLiveComponent]
final class LiveDashboardData
{
    use DefaultActionTrait;

    public function __construct(
        private readonly OverviewService $overviewService,
    ) {
    }

    /**
     * Get top performing routes (current snapshot).
     *
     * @return list<RouteMetricDto>
     */
    public function getTopPerformers(): array
    {
        $metrics = $this->overviewService->getSystemMetrics();

        return $metrics->topPerformers;
    }

    /**
     * Get routes needing attention (current snapshot).
     *
     * @return list<RouteMetricDto>
     */
    public function getNeedsAttention(): array
    {
        $metrics = $this->overviewService->getSystemMetrics();

        return $metrics->needsAttention;
    }

    /**
     * Get last update timestamp.
     */
    public function getLastUpdate(): string
    {
        $now = new \DateTime();

        return $now->format('g:i:s A');
    }

    /**
     * Check if GTFS-RT feeds are healthy (< 5 minutes old).
     */
    public function isFeedHealthy(): bool
    {
        $metrics = $this->overviewService->getSystemMetrics();

        return $metrics->isFeedHealthy;
    }

    /**
     * Get human-readable time since last feed update.
     */
    public function getFeedLastUpdated(): string
    {
        $metrics     = $this->overviewService->getSystemMetrics();
        $lastUpdated = new \DateTime('@'.$metrics->feedLastUpdated);
        $now         = new \DateTime();
        $interval    = $now->diff($lastUpdated);

        if ($interval->h > 0) {
            return $interval->h.'h '.$interval->i.'m ago';
        }
        if ($interval->i > 0) {
            return $interval->i.'m ago';
        }

        return 'just now';
    }
}
