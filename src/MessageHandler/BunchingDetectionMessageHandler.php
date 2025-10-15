<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Scheduler\BunchingDetectionMessage;
use App\Service\Bunching\BunchingDetector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles scheduled bunching detection.
 *
 * Runs daily at 1:00 AM to analyze previous day's bunching incidents.
 */
#[AsMessageHandler]
final readonly class BunchingDetectionMessageHandler
{
    public function __construct(
        private BunchingDetector $detector,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BunchingDetectionMessage $message): void
    {
        $this->logger->info('Starting scheduled bunching detection');

        try {
            $yesterday = new \DateTimeImmutable('yesterday');
            $result    = $this->detector->detectForDate($yesterday);

            $this->logger->info('Bunching detection completed', [
                'date'     => $yesterday->format('Y-m-d'),
                'detected' => $result['detected'],
                'skipped'  => $result['skipped'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to detect bunching incidents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
