<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Scheduler\WeatherCollectionMessage;
use App\Service\Weather\WeatherService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles scheduled weather collection.
 */
#[AsMessageHandler]
final readonly class WeatherCollectionMessageHandler
{
    public function __construct(
        private WeatherService $weatherService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WeatherCollectionMessage $message): void
    {
        $this->logger->info('Starting scheduled weather collection');

        $observation = $this->weatherService->fetchAndStoreCurrent();

        if ($observation === null) {
            $this->logger->error('Scheduled weather collection failed');

            return;
        }

        $this->logger->info('Scheduled weather collection completed', [
            'temperature' => $observation->getTemperatureCelsius(),
            'condition'   => $observation->getWeatherCondition(),
            'impact'      => $observation->getTransitImpact()->value,
        ]);
    }
}
