<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for periodic arrival logging.
 *
 * Runs every 2 minutes to generate arrival predictions for active vehicles,
 * building historical data for performance analysis.
 *
 * Frequency: 720 runs/day, ~65K logs/day with 30 vehicles
 * Captures 2-5 samples per inter-stop journey for prediction evolution tracking.
 */
#[AsSchedule('arrival_logging')]
final readonly class ArrivalLoggingSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run arrival logging every 2 minutes
                RecurringMessage::cron('*/2 * * * *', new ArrivalLoggingMessage())
            );
    }
}

/**
 * Message for scheduled arrival logging.
 */
final readonly class ArrivalLoggingMessage
{
}
