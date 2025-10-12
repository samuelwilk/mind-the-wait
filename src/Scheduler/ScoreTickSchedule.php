<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for realtime score calculation.
 *
 * Runs every 30 seconds to compute headway scores from vehicle positions.
 */
#[AsSchedule('score_tick')]
final readonly class ScoreTickSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run score calculation every 30 seconds
                RecurringMessage::every('30 seconds', new ScoreTickMessage())
            );
    }
}

/**
 * Message for realtime score calculation.
 */
final readonly class ScoreTickMessage
{
}
