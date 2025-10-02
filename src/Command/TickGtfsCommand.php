<?php

namespace App\Command;

use App\Enum\GtfsKindEnum;
use App\Message\PollGtfs;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsCommand(name: 'app:gtfs:tick', description: 'Enqueue GTFS-RT polls')]
final class TickGtfsCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $delays = [
            GtfsKindEnum::Vehicles->value => 0,     // ms
            GtfsKindEnum::Trips->value    => 4000,
            GtfsKindEnum::Alerts->value   => 15000,
        ];

        foreach ($delays as $kind => $ms) {
            $this->bus->dispatch(new PollGtfs(GtfsKindEnum::from($kind)), [new DelayStamp($ms)]);
        }

        return Command::SUCCESS;
    }
}
