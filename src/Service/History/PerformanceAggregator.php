<?php

declare(strict_types=1);

namespace App\Service\History;

use App\Repository\ArrivalLogRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\Repository\RouteRepository;
use App\Repository\WeatherObservationRepository;
use Psr\Log\LoggerInterface;

use function count;
use function round;
use function sort;
use function sprintf;

/**
 * Aggregates arrival log data into daily performance metrics.
 *
 * Uses SQL aggregations in repository to avoid loading large entity collections into PHP.
 */
final readonly class PerformanceAggregator
{
    public function __construct(
        private ArrivalLogRepository $arrivalLogRepo,
        private RoutePerformanceDailyRepository $performanceRepo,
        private RouteRepository $routeRepo,
        private WeatherObservationRepository $weatherRepo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Aggregate performance metrics for all routes for a given date.
     *
     * @return array{success: int, failed: int}
     */
    public function aggregateDate(\DateTimeImmutable $date): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay   = $date->setTime(23, 59, 59);

        // Find representative weather for this day (around noon)
        $noonTime = $date->setTime(12, 0, 0);
        $weather  = $this->weatherRepo->findClosestTo($noonTime);

        $routes  = $this->routeRepo->findAll();
        $success = 0;
        $failed  = 0;

        foreach ($routes as $route) {
            try {
                // Delegate aggregation to repository (SQL aggregation, not PHP loops)
                $metrics = $this->arrivalLogRepo->aggregateMetricsForRoute(
                    $route->getId(),
                    $startOfDay,
                    $endOfDay
                );

                if ($metrics->totalPredictions === 0) {
                    continue; // Skip routes with no activity
                }

                // Calculate median in PHP (requires sorted delays array)
                $medianDelaySec = $this->calculateMedian($metrics->delays);

                // Find or create performance record
                $performance = $this->performanceRepo->findOrCreate($route->getId(), $date);
                $performance->setTotalPredictions($metrics->totalPredictions);
                $performance->setHighConfidenceCount($metrics->highConfidenceCount);
                $performance->setMediumConfidenceCount($metrics->mediumConfidenceCount);
                $performance->setLowConfidenceCount($metrics->lowConfidenceCount);
                $performance->setAvgDelaySec($metrics->avgDelaySec);
                $performance->setMedianDelaySec($medianDelaySec);
                $performance->setOnTimePercentage($metrics->onTimePercentage !== null ? (string) $metrics->onTimePercentage : null);
                $performance->setLatePercentage($metrics->latePercentage !== null ? (string) $metrics->latePercentage : null);
                $performance->setEarlyPercentage($metrics->earlyPercentage !== null ? (string) $metrics->earlyPercentage : null);

                // Calculate schedule realism (actual vs scheduled travel time)
                $scheduleRealismRatio = $this->arrivalLogRepo->calculateScheduleRealismRatio(
                    $route->getId(),
                    $startOfDay,
                    $endOfDay
                );
                $performance->setScheduleRealismRatio($scheduleRealismRatio);

                // Link weather observation for weather-correlated analysis
                $performance->setWeatherObservation($weather);

                $this->performanceRepo->save($performance, flush: true);
                ++$success;

                $this->logger->info('Aggregated performance for route', [
                    'route_id'          => $route->getId(),
                    'route_short_name'  => $route->getShortName(),
                    'date'              => $date->format('Y-m-d'),
                    'total_predictions' => $metrics->totalPredictions,
                    'weather'           => $weather ? sprintf('%s°C, %s (%s)',
                        $weather->getTemperatureCelsius(),
                        $weather->getWeatherCondition(),
                        $weather->getTransitImpact()->value
                    ) : 'none',
                ]);
            } catch (\Exception $e) {
                ++$failed;
                $this->logger->error('Failed to aggregate performance for route', [
                    'route_id' => $route->getId(),
                    'date'     => $date->format('Y-m-d'),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info(sprintf('Aggregated performance for %d routes (%d failed)', $success, $failed));

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Calculate median from an array of integers.
     *
     * @param list<int> $values
     */
    private function calculateMedian(array $values): ?int
    {
        if (count($values) === 0) {
            return null;
        }

        sort($values);
        $count = count($values);
        $mid   = (int) ($count / 2);

        if ($count % 2 === 0) {
            // Even count: average of two middle values
            return (int) round(($values[$mid - 1] + $values[$mid]) / 2);
        }

        // Odd count: middle value
        return $values[$mid];
    }
}
