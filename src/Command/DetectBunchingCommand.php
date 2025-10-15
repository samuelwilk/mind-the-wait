<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bunching\BunchingDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Detects vehicle bunching incidents from arrival logs.
 *
 * Run daily to analyze previous day's arrival patterns and identify
 * bunching events (when 2+ vehicles arrive within 2 minutes).
 */
#[AsCommand(
    name: 'app:detect:bunching',
    description: 'Detect vehicle bunching incidents from arrival logs',
)]
final class DetectBunchingCommand extends Command
{
    public function __construct(
        private readonly BunchingDetector $bunchingDetector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            'd',
            InputOption::VALUE_REQUIRED,
            'Date to analyze (format: YYYY-MM-DD). Defaults to yesterday.',
        );

        $this->addOption(
            'time-window',
            't',
            InputOption::VALUE_REQUIRED,
            'Time window in seconds to detect bunching (default: 120)',
            '120',
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

        $timeWindow = (int) $input->getOption('time-window');

        if ($timeWindow <= 0 || $timeWindow > 600) {
            $io->error('Time window must be between 1 and 600 seconds.');

            return Command::FAILURE;
        }

        $io->info(sprintf(
            'Detecting bunching incidents for %s (time window: %d seconds)',
            $date->format('Y-m-d'),
            $timeWindow
        ));

        // Run detection
        $result = $this->bunchingDetector->detectForDate($date, $timeWindow);

        if ($result['detected'] === 0 && $result['skipped'] === 0) {
            $io->info('No bunching incidents detected (no arrival log data for this date).');

            return Command::SUCCESS;
        }

        if ($result['skipped'] > 0) {
            $io->warning(sprintf(
                'Detected %d bunching incidents, %d skipped. Check logs for details.',
                $result['detected'],
                $result['skipped']
            ));

            return $result['detected'] > 0 ? Command::SUCCESS : Command::FAILURE;
        }

        $io->success(sprintf('Successfully detected %d bunching incidents.', $result['detected']));

        return Command::SUCCESS;
    }
}
