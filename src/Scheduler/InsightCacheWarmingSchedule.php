<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules nightly cache warming for AI-generated insights.
 *
 * Runs at 2:00 AM daily to ensure fresh insights are ready for morning peak usage.
 */
#[AsSchedule('insight_cache_warming')]
final readonly class InsightCacheWarmingSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron(
                    '0 2 * * *', // 2:00 AM daily
                    new InsightCacheWarmingMessage()
                )
            );
    }
}

/**
 * Message for nightly insight cache warming.
 */
final readonly class InsightCacheWarmingMessage
{
}
