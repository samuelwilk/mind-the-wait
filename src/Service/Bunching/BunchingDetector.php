<?php

declare(strict_types=1);

namespace App\Service\Bunching;

use App\Entity\BunchingIncident;
use App\Repository\ArrivalLogRepository;
use App\Repository\BunchingIncidentRepository;
use App\Repository\RouteRepository;
use App\Repository\StopRepository;
use App\Repository\WeatherObservationRepository;
use Psr\Log\LoggerInterface;

use function count;

/**
 * Detects vehicle bunching incidents from arrival logs.
 *
 * Bunching occurs when 2+ vehicles on the same route arrive at a stop
 * within a short time window (default: 120 seconds / 2 minutes).
 *
 * This service orchestrates bunching detection by:
 * 1. Querying arrival logs for bunching candidates (delegated to repository)
 * 2. Enriching candidates with route, stop, and weather data
 * 3. Batch-persisting incidents for better performance
 */
final readonly class BunchingDetector
{
    private const int DEFAULT_TIME_WINDOW_SECONDS = 120; // 2 minutes

    public function __construct(
        private ArrivalLogRepository $arrivalLogRepo,
        private BunchingIncidentRepository $bunchingRepo,
        private RouteRepository $routeRepo,
        private StopRepository $stopRepo,
        private WeatherObservationRepository $weatherRepo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Detect bunching incidents for a given date.
     *
     * @return array{detected: int, skipped: int}
     */
    public function detectForDate(\DateTimeImmutable $date, int $timeWindowSeconds = self::DEFAULT_TIME_WINDOW_SECONDS): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay   = $date->setTime(23, 59, 59);

        $this->logger->info('Starting bunching detection', [
            'date'                => $date->format('Y-m-d'),
            'time_window_seconds' => $timeWindowSeconds,
        ]);

        // Delegate SQL query to repository - returns typed DTOs
        $candidates = $this->arrivalLogRepo->findBunchingCandidates($startOfDay, $endOfDay, $timeWindowSeconds);

        $incidents = [];
        $skipped   = 0;

        foreach ($candidates as $candidate) {
            try {
                // Find nearest weather observation
                $weather = $this->weatherRepo->findClosestTo($candidate->bunchingTime);

                // Get route and stop entities via repositories (not EntityManager)
                $route = $this->routeRepo->find($candidate->routeId);
                $stop  = $this->stopRepo->find($candidate->stopId);

                if ($route === null || $stop === null) {
                    ++$skipped;
                    $this->logger->warning('Route or stop not found for bunching candidate', [
                        'route_id' => $candidate->routeId,
                        'stop_id'  => $candidate->stopId,
                    ]);

                    continue;
                }

                // Build incident entity from DTO
                $incident = new BunchingIncident();
                $incident->setRoute($route);
                $incident->setStop($stop);
                $incident->setDetectedAt($candidate->bunchingTime);
                $incident->setVehicleCount($candidate->vehicleCount);
                $incident->setTimeWindowSeconds($timeWindowSeconds);
                $incident->setVehicleIds($candidate->vehicleIds);
                $incident->setWeatherObservation($weather);

                $incidents[] = $incident;
            } catch (\Exception $e) {
                ++$skipped;
                $this->logger->error('Failed to build bunching incident', [
                    'route_id'      => $candidate->routeId,
                    'stop_id'       => $candidate->stopId,
                    'bunching_time' => $candidate->bunchingTime->format('Y-m-d H:i:s'),
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        // Batch persist all incidents in a single transaction
        if (count($incidents) > 0) {
            $this->bunchingRepo->saveBatch($incidents);
        }

        $detected = count($incidents);

        $this->logger->info('Bunching detection completed', [
            'date'     => $date->format('Y-m-d'),
            'detected' => $detected,
            'skipped'  => $skipped,
        ]);

        return ['detected' => $detected, 'skipped' => $skipped];
    }
}
