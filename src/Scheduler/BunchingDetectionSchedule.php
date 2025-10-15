<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules daily bunching detection.
 *
 * Runs at 1:00 AM daily to analyze previous day's bunching incidents.
 * This runs before insight cache warming (2:00 AM) to ensure fresh data is available.
 */
#[AsSchedule('bunching_detection')]
final readonly class BunchingDetectionSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron(
                    '0 1 * * *', // 1:00 AM daily
                    new BunchingDetectionMessage()
                )
            );
    }
}

/**
 * Message for daily bunching detection.
 */
final readonly class BunchingDetectionMessage
{
}
