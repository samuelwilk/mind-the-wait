<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for daily performance aggregation.
 *
 * Runs once per day at 1:00 AM to aggregate previous day's arrival logs
 * into daily performance metrics.
 */
#[AsSchedule('performance_aggregation')]
final readonly class PerformanceAggregationSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run aggregation daily at 1:00 AM
                RecurringMessage::cron('0 1 * * *', new PerformanceAggregationMessage())
            );
    }
}

/**
 * Message for daily performance aggregation.
 */
final readonly class PerformanceAggregationMessage
{
}
