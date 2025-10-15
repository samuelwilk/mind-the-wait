<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\RoutePerformanceDaily;
use App\Entity\WeatherObservation;
use App\Repository\RouteRepository;
use App\Repository\WeatherObservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;

/**
 * Seeds synthetic performance data for development and demo purposes.
 *
 * ⚠️ WARNING: This generates FAKE data, not real transit performance data.
 *
 * ## What This Command Does
 *
 * Creates realistic-looking sample data for route performance analysis:
 * - RoutePerformanceDaily records (30 days per route by default)
 * - WeatherObservation records (one per day)
 * - Synthetic predictions, delays, and on-time percentages
 *
 * ## Data Generation Strategy
 *
 * Each route gets a baseline performance (65-90%) with realistic variations:
 *
 * - **Weather Impact**:
 *   - Clear: +2% to +8%
 *   - Partly Cloudy: -2% to +5%
 *   - Cloudy: -5% to +2%
 *   - Rain: -15% to -5%
 *   - Snow: -25% to -10%
 *
 * - **Day-of-Week Patterns**:
 *   - Weekends: +3% to +8% (better)
 *   - Mon/Fri: -2% to -6% (worse)
 *
 * - **Daily Variance**: ±5% random chaos
 *
 * ## When to Use This
 *
 * ✅ Development/testing when you need dashboard visualizations
 * ✅ Demo environments to show what the system looks like with data
 * ✅ Initial setup before real data collection begins
 *
 * ❌ DO NOT use in production with real users
 * ❌ DO NOT mix seeded data with real collected data
 *
 * ## Real Data Collection
 *
 * In production, use these commands instead:
 * 1. `app:collect:arrival-logs` - Collects real arrival predictions over time
 * 2. `app:collect:daily-performance` - Aggregates real arrivals into daily metrics
 * 3. `app:weather:fetch` - Fetches actual weather observations
 *
 * ## Usage Examples
 *
 * ```bash
 * # Seed 30 days of data
 * php bin/console app:seed:performance-data
 *
 * # Seed 60 days of data
 * php bin/console app:seed:performance-data --days=60
 *
 * # Clear existing data and reseed
 * php bin/console app:seed:performance-data --clear
 * ```
 */
#[AsCommand(
    name: 'app:seed:performance-data',
    description: 'Seed synthetic performance data for development/demo (NOT real data)',
)]
final class SeedPerformanceDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteRepository $routeRepo,
        private readonly WeatherObservationRepository $weatherRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to seed', 30);
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing performance data first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $days  = (int) $input->getOption('days');
        $clear = $input->getOption('clear');

        if ($clear) {
            $io->warning('Clearing existing performance data...');
            $this->em->createQuery('DELETE FROM App\Entity\RoutePerformanceDaily')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\WeatherObservation')->execute();
            $io->success('Cleared existing data.');
        }

        $routes = $this->routeRepo->findAll();
        if (count($routes) === 0) {
            $io->error('No routes found. Please run app:gtfs:load first.');

            return Command::FAILURE;
        }

        $io->section("Seeding $days days of performance data for ".count($routes).' routes...');

        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify("-$days days");

        $weatherConditions = ['clear', 'partly_cloudy', 'cloudy', 'rain', 'snow'];
        $totalRecords      = 0;

        // Create weather observations first
        $weatherObservations = [];
        for ($date = $startDate; $date <= $endDate; $date = $date->modify('+1 day')) {
            // Create one weather observation per day (at noon)
            $weather    = new WeatherObservation();
            $observedAt = $date->setTime(12, 0, 0);
            $weather->setObservedAt($observedAt);

            // Weight conditions (more clear/cloudy days than snow)
            $rand = mt_rand(1, 100);
            if ($rand <= 35) {
                $condition = 'clear';
                $temp      = mt_rand(15, 28);
                $impact    = \App\Enum\TransitImpact::NONE;
            } elseif ($rand <= 65) {
                $condition = 'partly_cloudy';
                $temp      = mt_rand(10, 22);
                $impact    = \App\Enum\TransitImpact::NONE;
            } elseif ($rand <= 80) {
                $condition = 'cloudy';
                $temp      = mt_rand(5, 18);
                $impact    = \App\Enum\TransitImpact::MINOR;
            } elseif ($rand <= 90) {
                $condition = 'rain';
                $temp      = mt_rand(8, 16);
                $impact    = \App\Enum\TransitImpact::MODERATE;
            } else {
                $condition = 'snow';
                $temp      = mt_rand(-10, 2);
                $impact    = \App\Enum\TransitImpact::SEVERE;
            }

            $weather->setWeatherCondition($condition);
            $weather->setTemperatureCelsius((string) $temp);
            $weather->setPrecipitationMm($condition === 'rain' ? (string) mt_rand(1, 15) : ($condition === 'snow' ? '0' : '0'));
            $weather->setSnowfallCm($condition === 'snow' ? (string) (mt_rand(2, 20) / 10) : null);
            $weather->setWindSpeedKmh((string) mt_rand(5, 40));
            $weather->setTransitImpact($impact);
            $weather->setDataSource('seed');

            $this->em->persist($weather);
            $weatherObservations[$date->format('Y-m-d')] = $weather;
        }

        $this->em->flush();
        $io->info('Created '.count($weatherObservations).' weather observations.');

        // Store weather IDs for later retrieval
        $weatherIds = [];
        foreach ($weatherObservations as $dateStr => $weather) {
            $weatherIds[$dateStr] = $weather->getId();
        }

        // Create performance data for each route/day
        $progressBar = $io->createProgressBar(count($routes) * $days);
        $progressBar->start();

        foreach ($routes as $route) {
            // Each route has a baseline performance (some routes are better than others)
            $baselinePerformance = mt_rand(65, 90);
            $routeId             = $route->getId();

            for ($date = $startDate; $date <= $endDate; $date = $date->modify('+1 day')) {
                // Re-fetch route if it was cleared
                if (!$this->em->contains($route)) {
                    $route = $this->routeRepo->find($routeId);
                }

                // Re-fetch weather observation if needed
                $dateStr = $date->format('Y-m-d');
                $weather = $weatherObservations[$dateStr] ?? null;
                if ($weather === null || !$this->em->contains($weather)) {
                    $weather                       = $this->weatherRepo->find($weatherIds[$dateStr]);
                    $weatherObservations[$dateStr] = $weather;
                }

                // Calculate performance with various factors
                $performance = $baselinePerformance;

                // Weather impact
                $weatherImpact = match ($weather->getWeatherCondition()) {
                    'clear'         => mt_rand(2, 8),
                    'partly_cloudy' => mt_rand(-2, 5),
                    'cloudy'        => mt_rand(-5, 2),
                    'rain'          => mt_rand(-15, -5),
                    'snow'          => mt_rand(-25, -10),
                    default         => 0,
                };
                $performance += $weatherImpact;

                // Day of week impact (weekends are typically better)
                $dayOfWeek = (int) $date->format('N'); // 1=Monday, 7=Sunday
                if ($dayOfWeek === 6 || $dayOfWeek === 7) {
                    $performance += mt_rand(3, 8); // Weekend bonus
                } elseif ($dayOfWeek === 1 || $dayOfWeek === 5) {
                    $performance -= mt_rand(2, 6); // Monday/Friday penalty
                }

                // Random variance (daily chaos)
                $performance += mt_rand(-5, 5);

                // Clamp to valid range
                $performance = max(30.0, min(100.0, (float) $performance));

                // Generate realistic metrics based on predictions
                $totalPredictions = mt_rand(80, 200);
                $highConfidence   = (int) round($totalPredictions * mt_rand(40, 60) / 100);
                $mediumConfidence = (int) round($totalPredictions * mt_rand(20, 30) / 100);
                $lowConfidence    = $totalPredictions - $highConfidence - $mediumConfidence;

                // Calculate percentages
                $latePercent  = 100 - $performance - mt_rand(5, 15); // early percent
                $earlyPercent = 100 - $performance - $latePercent;

                // Delay calculations (in seconds)
                $avgDelay    = $performance < 70 ? mt_rand(180, 900) : mt_rand(-60, 180); // 3-15 min late, or slightly early
                $medianDelay = $avgDelay + mt_rand(-60, 60);

                $perf = new RoutePerformanceDaily();
                $perf->setRoute($route);
                $perf->setDate($date);
                $perf->setWeatherObservation($weather);
                $perf->setOnTimePercentage((string) round($performance, 2));
                $perf->setLatePercentage((string) round(max(0, $latePercent), 2));
                $perf->setEarlyPercentage((string) round(max(0, $earlyPercent), 2));
                $perf->setTotalPredictions($totalPredictions);
                $perf->setHighConfidenceCount($highConfidence);
                $perf->setMediumConfidenceCount($mediumConfidence);
                $perf->setLowConfidenceCount($lowConfidence);
                $perf->setAvgDelaySec($avgDelay);
                $perf->setMedianDelaySec($medianDelay);
                $perf->setBunchingIncidents(mt_rand(0, 3));

                $this->em->persist($perf);
                ++$totalRecords;
                $progressBar->advance();

                // Flush every 100 records to avoid memory issues
                if ($totalRecords % 100 === 0) {
                    $this->em->flush();
                    $this->em->clear(RoutePerformanceDaily::class);
                }
            }
        }

        $this->em->flush();
        $progressBar->finish();
        $io->newLine(2);

        $io->success("Seeded $totalRecords performance records for ".count($routes)." routes over $days days.");

        return Command::SUCCESS;
    }
}
