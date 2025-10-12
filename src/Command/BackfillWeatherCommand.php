<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Weather\WeatherService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Backfills historical weather data from Open-Meteo archive API.
 *
 * Run once to populate historical data for weather-based analysis.
 */
#[AsCommand(
    name: 'app:backfill:weather',
    description: 'Backfill historical weather data from Open-Meteo archive',
)]
final class BackfillWeatherCommand extends Command
{
    public function __construct(
        private readonly WeatherService $weatherService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_REQUIRED,
            'Number of days to backfill (default: 90)',
            90
        );

        $this->addOption(
            'start-date',
            's',
            InputOption::VALUE_REQUIRED,
            'Start date (YYYY-MM-DD). Overrides --days option.',
        );

        $this->addOption(
            'end-date',
            null,
            InputOption::VALUE_REQUIRED,
            'End date (YYYY-MM-DD). Defaults to today.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse date range
        $endDate = new \DateTimeImmutable($input->getOption('end-date') ?? 'today');

        if ($input->getOption('start-date')) {
            $startDate = new \DateTimeImmutable($input->getOption('start-date'));
        } else {
            $days      = (int) $input->getOption('days');
            $startDate = $endDate->modify(sprintf('-%d days', $days));
        }

        $io->title('Backfilling Historical Weather Data');
        $io->info(sprintf(
            'Fetching weather from %s to %s',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ));

        $io->note('This may take a while for large date ranges...');

        $result = $this->weatherService->backfillHistoricalWeather($startDate, $endDate);

        if ($result['failed'] > 0) {
            $io->warning(sprintf(
                'Backfilled %d observations successfully, %d failed. Check logs for details.',
                $result['success'],
                $result['failed']
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Successfully backfilled %d weather observations!',
            $result['success']
        ));

        return Command::SUCCESS;
    }
}
