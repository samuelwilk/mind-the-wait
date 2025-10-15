<?php

declare(strict_types=1);

namespace App\Service\Bunching;

use App\Entity\BunchingIncident;
use App\Repository\ArrivalLogRepository;
use App\Repository\BunchingIncidentRepository;
use App\Repository\WeatherObservationRepository;
use Psr\Log\LoggerInterface;

/**
 * Detects vehicle bunching incidents from arrival logs.
 *
 * Bunching occurs when 2+ vehicles on the same route arrive at a stop
 * within a short time window (default: 120 seconds / 2 minutes).
 */
final readonly class BunchingDetector
{
    private const int DEFAULT_TIME_WINDOW_SECONDS = 120; // 2 minutes

    public function __construct(
        private ArrivalLogRepository $arrivalLogRepo,
        private BunchingIncidentRepository $bunchingRepo,
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

        // Use native SQL for better performance with window functions
        $conn = $this->arrivalLogRepo->getEntityManager()->getConnection();

        $sql = "
            WITH vehicle_arrivals AS (
                SELECT
                    route_id,
                    stop_id,
                    vehicle_id,
                    predicted_arrival_at,
                    predicted_at,
                    -- Get the previous vehicle's arrival time for this route/stop
                    LAG(predicted_arrival_at) OVER (
                        PARTITION BY route_id, stop_id
                        ORDER BY predicted_arrival_at
                    ) as prev_arrival_at,
                    -- Get the previous vehicle ID
                    LAG(vehicle_id) OVER (
                        PARTITION BY route_id, stop_id
                        ORDER BY predicted_arrival_at
                    ) as prev_vehicle_id
                FROM arrival_log
                WHERE predicted_at >= :start_date
                    AND predicted_at < :end_date
                    AND predicted_arrival_at IS NOT NULL
            ),
            bunching_candidates AS (
                SELECT
                    route_id,
                    stop_id,
                    predicted_arrival_at as bunching_time,
                    vehicle_id,
                    prev_vehicle_id,
                    EXTRACT(EPOCH FROM (predicted_arrival_at - prev_arrival_at)) as time_gap_seconds
                FROM vehicle_arrivals
                WHERE prev_arrival_at IS NOT NULL
                    AND vehicle_id != prev_vehicle_id  -- Different vehicles
                    AND EXTRACT(EPOCH FROM (predicted_arrival_at - prev_arrival_at)) <= :time_window
                    AND EXTRACT(EPOCH FROM (predicted_arrival_at - prev_arrival_at)) > 0
            )
            SELECT
                route_id,
                stop_id,
                bunching_time,
                COUNT(*) + 1 as vehicle_count,  -- +1 to include the first vehicle
                STRING_AGG(DISTINCT vehicle_id || ',' || prev_vehicle_id, ';') as vehicle_ids
            FROM bunching_candidates
            GROUP BY route_id, stop_id, bunching_time
            ORDER BY bunching_time
        ";

        $results = $conn->executeQuery($sql, [
            'start_date'  => $startOfDay->format('Y-m-d H:i:s'),
            'end_date'    => $endOfDay->format('Y-m-d H:i:s'),
            'time_window' => $timeWindowSeconds,
        ])->fetchAllAssociative();

        $detected = 0;
        $skipped  = 0;

        foreach ($results as $row) {
            try {
                $bunchingTime = new \DateTimeImmutable($row['bunching_time']);

                // Find nearest weather observation
                $weather = $this->weatherRepo->findClosestTo($bunchingTime);

                // Get route and stop entities
                $route = $this->arrivalLogRepo->getEntityManager()->find(\App\Entity\Route::class, (int) $row['route_id']);
                $stop  = $this->arrivalLogRepo->getEntityManager()->find(\App\Entity\Stop::class, (int) $row['stop_id']);

                if ($route === null || $stop === null) {
                    ++$skipped;

                    continue;
                }

                $incident = new BunchingIncident();
                $incident->setRoute($route);
                $incident->setStop($stop);
                $incident->setDetectedAt($bunchingTime);
                $incident->setVehicleCount((int) $row['vehicle_count']);
                $incident->setTimeWindowSeconds($timeWindowSeconds);
                $incident->setVehicleIds($row['vehicle_ids']);
                $incident->setWeatherObservation($weather);

                $this->bunchingRepo->save($incident, flush: true);
                ++$detected;
            } catch (\Exception $e) {
                ++$skipped;
                $this->logger->error('Failed to save bunching incident', [
                    'route_id'      => $row['route_id'],
                    'stop_id'       => $row['stop_id'],
                    'bunching_time' => $row['bunching_time'],
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Bunching detection completed', [
            'date'     => $date->format('Y-m-d'),
            'detected' => $detected,
            'skipped'  => $skipped,
        ]);

        return ['detected' => $detected, 'skipped' => $skipped];
    }
}
