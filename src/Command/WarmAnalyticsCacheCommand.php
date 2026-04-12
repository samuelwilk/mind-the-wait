<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\Analytics\DateRangeDto;
use App\Service\Dashboard\AnalyticsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

/**
 * Pre-warms the analytics cache for all presets so the first user hit is fast.
 *
 * Run after deploy and nightly alongside app:warm-insight-cache.
 */
#[AsCommand(
    name: 'app:warm-analytics-cache',
    description: 'Pre-warm analytics dashboard cache for all date presets',
)]
final class WarmAnalyticsCacheCommand extends Command
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Warming Analytics Cache');

        $presets = [
            DateRangeDto::PRESET_LAST_30_DAYS,
            DateRangeDto::PRESET_LAST_60_DAYS,
            DateRangeDto::PRESET_LAST_90_DAYS,
            DateRangeDto::PRESET_ALL_TIME,
        ];

        $warmed = 0;
        foreach ($presets as $preset) {
            $label = DateRangeDto::getPresets()[$preset] ?? $preset;

            try {
                $io->text("Warming: {$label}...");
                $dateRange = DateRangeDto::fromPreset($preset);
                $this->analyticsService->getAnalyticsPageData($dateRange);
                $this->analyticsService->getAvailableRoutes($dateRange);
                ++$warmed;
            } catch (\Exception $e) {
                $io->warning("Failed to warm {$label}: {$e->getMessage()}");
                $this->logger->warning('Analytics cache warm failed for preset', [
                    'preset' => $preset,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        $total = count($presets);
        $io->success("Warmed {$warmed}/{$total} presets.");

        $this->logger->info('Analytics cache warmed', ['presets' => $warmed]);

        return Command::SUCCESS;
    }
}
