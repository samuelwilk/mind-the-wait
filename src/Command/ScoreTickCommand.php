<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RealtimeRepository;
use App\Service\Headway\HeadwayScorer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

#[AsCommand(name: 'app:score:tick', description: 'Compute scores per (route, direction)')]
final class ScoreTickCommand extends Command
{
    public function __construct(
        private readonly RealtimeRepository $rt,
        private readonly HeadwayScorer $scorer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $ts       = max($this->rt->getVehiclesTimestamps(), time());
        $vehicles = $this->rt->getVehicles();

        $scores = $this->scorer->compute($vehicles, $ts);

        // persist as plain arrays for JSON
        $rows = array_map(static fn ($s) => $s->toArray(), $scores);
        $this->rt->saveScores($ts, $rows);

        $io->success(sprintf('Score tick OK (%d groups @ %d)', count($rows), $ts));

        return Command::SUCCESS;
    }
}
