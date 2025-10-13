<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Dashboard\OverviewService;
use App\Service\Dashboard\WeatherAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pre-warms the AI insight cache to ensure fast page loads for users.
 *
 * Should be run nightly (e.g., 2 AM) to generate fresh insights before peak usage.
 */
#[AsCommand(
    name: 'app:warm-insight-cache',
    description: 'Pre-warm AI-generated insight cache for fast page loads',
)]
final class WarmInsightCacheCommand extends Command
{
    public function __construct(
        private readonly OverviewService $overviewService,
        private readonly WeatherAnalysisService $weatherAnalysisService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Warming AI Insight Cache');
        $io->text('Generating fresh AI insights for dashboard pages...');

        try {
            // Generate overview page insights (2 insights)
            $io->section('Dashboard Overview');
            $io->text('Generating dashboard insight cards...');
            $this->overviewService->getSystemMetrics();
            $io->success('Dashboard insights cached');

            // Generate weather impact page insights (5 insights)
            $io->section('Weather Impact Analysis');
            $io->text('Generating weather analysis insights...');
            $this->weatherAnalysisService->getWeatherImpactInsights();
            $io->success('Weather insights cached');

            $io->newLine();
            $io->success([
                'All AI insights successfully cached!',
                'Users will experience instant page loads for the next 24 hours.',
            ]);

            $this->logger->info('AI insight cache warmed successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to warm insight cache: '.$e->getMessage());
            $this->logger->error('Failed to warm insight cache', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
