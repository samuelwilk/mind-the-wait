<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Weather\WeatherService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Fetches current weather from Open-Meteo and stores in database.
 *
 * Run hourly via cron to build historical weather database.
 */
#[AsCommand(
    name: 'app:collect:weather',
    description: 'Fetch and store current weather conditions for Saskatoon',
)]
final class CollectWeatherCommand extends Command
{
    public function __construct(
        private readonly WeatherService $weatherService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Fetching current weather for Saskatoon...');

        $observation = $this->weatherService->fetchAndStoreCurrent();

        if ($observation === null) {
            $io->error('Failed to fetch weather. Check logs for details.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Weather collected: %s°C, %s (Transit Impact: %s)',
            $observation->getTemperatureCelsius(),
            $observation->getWeatherCondition(),
            $observation->getTransitImpact()->getLabel()
        ));

        $io->table(
            ['Field', 'Value'],
            [
                ['Observed At', $observation->getObservedAt()->format('Y-m-d H:i:s T')],
                ['Temperature', $observation->getTemperatureCelsius().'°C'],
                ['Feels Like', $observation->getFeelsLikeCelsius() ? $observation->getFeelsLikeCelsius().'°C' : 'N/A'],
                ['Condition', $observation->getWeatherCondition()],
                ['Precipitation', $observation->getPrecipitationMm() ? $observation->getPrecipitationMm().' mm' : '0 mm'],
                ['Snowfall', $observation->getSnowfallCm() ? $observation->getSnowfallCm().' cm' : '0 cm'],
                ['Snow Depth', $observation->getSnowDepthCm() ? $observation->getSnowDepthCm().' cm' : 'N/A'],
                ['Visibility', $observation->getVisibilityKm() ? $observation->getVisibilityKm().' km' : 'N/A'],
                ['Wind Speed', $observation->getWindSpeedKmh() ? $observation->getWindSpeedKmh().' km/h' : 'N/A'],
                ['Transit Impact', $observation->getTransitImpact()->getLabel()],
                ['Impact Description', $observation->getTransitImpact()->getDescription()],
            ]
        );

        return Command::SUCCESS;
    }
}
