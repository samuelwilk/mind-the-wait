<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for broadcasting route updates via Mercure.
 *
 * Runs every 5 seconds to publish live vehicle positions to subscribed clients.
 */
#[AsSchedule('mercure_route_broadcast')]
final readonly class MercureRouteBroadcastSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Broadcast route updates every 5 seconds
                RecurringMessage::every('5 seconds', new MercureRouteBroadcastMessage())
            );
    }
}

/**
 * Message for Mercure route broadcast.
 */
final readonly class MercureRouteBroadcastMessage
{
}
