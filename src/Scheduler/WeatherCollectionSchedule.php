<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for hourly weather collection.
 *
 * Runs every hour to collect current weather conditions from Open-Meteo API.
 */
#[AsSchedule('weather_collection')]
final readonly class WeatherCollectionSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run weather collection every hour at minute 0
                RecurringMessage::cron('0 * * * *', new WeatherCollectionMessage())
            );
    }
}

/**
 * Message for hourly weather collection.
 */
final readonly class WeatherCollectionMessage
{
}
