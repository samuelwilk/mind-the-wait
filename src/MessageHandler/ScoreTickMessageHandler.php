<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Repository\RealtimeRepository;
use App\Scheduler\ScoreTickMessage;
use App\Service\Headway\HeadwayScorer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function count;
use function max;
use function sprintf;

/**
 * Handles scheduled score tick (every 30 seconds).
 */
#[AsMessageHandler]
final readonly class ScoreTickMessageHandler
{
    public function __construct(
        private RealtimeRepository $rt,
        private HeadwayScorer $scorer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScoreTickMessage $message): void
    {
        $ts       = max($this->rt->getVehiclesTimestamps(), time());
        $vehicles = $this->rt->getVehicles();

        $scores = $this->scorer->compute($vehicles, $ts);

        // persist as plain arrays for JSON
        $rows = array_map(static fn ($s) => $s->toArray(), $scores);
        $this->rt->saveScores($ts, $rows);

        $this->logger->debug(sprintf('Score tick OK (%d groups @ %d)', count($rows), $ts));
    }
}
