<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules nightly cache warming for insights and analytics.
 *
 * Runs at 2:00 AM daily to ensure all caches are warm for morning peak usage.
 */
#[AsSchedule('nightly_cache_warming')]
final readonly class NightlyCacheWarmingSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron(
                    '0 2 * * *', // 2:00 AM daily
                    new NightlyCacheWarmingMessage()
                )
            );
    }
}

/**
 * Message for nightly cache warming (insights + analytics).
 */
final readonly class NightlyCacheWarmingMessage
{
}
