<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

use function sprintf;

#[AsCommand(
    name: 'app:mercure:test',
    description: 'Test Mercure broadcasting with simple messages',
)]
class MercureTestCommand extends Command
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting Mercure test broadcast (Ctrl+C to stop)...');

        $counter = 0;
        while (true) {
            ++$counter;
            $timestamp = time();

            // Build a simple Turbo Stream update
            $turboStream = sprintf(
                '<turbo-stream action="replace" target="mercure-test"><template><div id="mercure-test" class="p-4 bg-blue-100 rounded"><strong>Test %d</strong><br>Timestamp: %d<br>Time: %s</div></template></turbo-stream>',
                $counter,
                $timestamp,
                date('H:i:s', $timestamp)
            );

            // Publish to Mercure
            $update = new Update(
                topics: ['test'],
                data: $turboStream,
                private: false,
            );

            try {
                $this->hub->publish($update);
                $output->writeln(sprintf('[%s] Published test #%d (ts=%d)', date('H:i:s'), $counter, $timestamp));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Failed to publish: %s</error>', $e->getMessage()));
            }

            sleep(5);
        }

        return Command::SUCCESS;
    }
}
