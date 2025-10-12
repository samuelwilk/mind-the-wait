<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Scheduler\PerformanceAggregationMessage;
use App\Service\History\PerformanceAggregator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles scheduled performance aggregation.
 */
#[AsMessageHandler]
final readonly class PerformanceAggregationMessageHandler
{
    public function __construct(
        private PerformanceAggregator $aggregator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PerformanceAggregationMessage $message): void
    {
        // Aggregate previous day's data
        $date = new \DateTimeImmutable('yesterday');

        $this->logger->info('Starting scheduled performance aggregation', [
            'date' => $date->format('Y-m-d'),
        ]);

        $result = $this->aggregator->aggregateDate($date);

        $this->logger->info('Scheduled performance aggregation completed', [
            'date'    => $date->format('Y-m-d'),
            'success' => $result['success'],
            'failed'  => $result['failed'],
        ]);
    }
}
