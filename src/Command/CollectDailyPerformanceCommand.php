<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\History\PerformanceAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Aggregates arrival logs into daily performance metrics.
 *
 * Run daily to compute route performance statistics for dashboards.
 */
#[AsCommand(
    name: 'app:collect:daily-performance',
    description: 'Aggregate arrival logs into daily performance metrics',
)]
final class CollectDailyPerformanceCommand extends Command
{
    public function __construct(
        private readonly PerformanceAggregator $aggregator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            'd',
            InputOption::VALUE_REQUIRED,
            'Date to aggregate (format: YYYY-MM-DD). Defaults to yesterday.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Parse date option or default to yesterday
        $dateString = $input->getOption('date');
        if ($dateString === null) {
            $date = new \DateTimeImmutable('yesterday');
        } else {
            try {
                $date = new \DateTimeImmutable($dateString);
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid date format: %s. Use YYYY-MM-DD.', $dateString));

                return Command::FAILURE;
            }
        }

        $io->info(sprintf('Aggregating performance metrics for date: %s', $date->format('Y-m-d')));

        // Run aggregation
        $result = $this->aggregator->aggregateDate($date);

        if ($result['failed'] > 0) {
            $io->warning(sprintf(
                'Aggregated %d routes successfully, %d failed. Check logs for details.',
                $result['success'],
                $result['failed']
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf('Successfully aggregated performance for %d routes.', $result['success']));

        return Command::SUCCESS;
    }
}
