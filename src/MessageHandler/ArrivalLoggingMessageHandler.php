<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Command\CollectArrivalLogsCommand;
use App\Scheduler\ArrivalLoggingMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function preg_match;

/**
 * Handles scheduled arrival logging.
 *
 * Executes the app:collect:arrival-logs command to generate predictions
 * for all active vehicles and populate the arrival_log table.
 */
#[AsMessageHandler]
final readonly class ArrivalLoggingMessageHandler
{
    public function __construct(
        private CollectArrivalLogsCommand $command,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ArrivalLoggingMessage $message): void
    {
        $this->logger->info('Starting scheduled arrival logging');

        $input  = new ArrayInput([]);
        $output = new BufferedOutput();

        try {
            $exitCode = $this->command->run($input, $output);

            if ($exitCode !== 0) {
                $this->logger->error('Scheduled arrival logging failed', [
                    'exit_code' => $exitCode,
                    'output'    => $output->fetch(),
                ]);

                return;
            }

            // Parse output for metrics
            $outputText = $output->fetch();
            if (preg_match('/Collected (\d+) arrival predictions/', $outputText, $matches)) {
                $count = (int) $matches[1];
                $this->logger->info('Scheduled arrival logging completed', [
                    'predictions_logged' => $count,
                ]);
            } else {
                $this->logger->info('Scheduled arrival logging completed');
            }
        } catch (\Exception $e) {
            $this->logger->error('Scheduled arrival logging exception', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
