<?php

declare(strict_types=1);

namespace App\Service\History;

use App\Repository\RealtimeRepository;
use App\Repository\TripRepository;
use App\Service\Geometry\RouteSnapper;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Detects when vehicles arrive at stops by snapping GPS positions
 * to route geometry and tracking progress along the shape.
 *
 * When a vehicle's route-distance passes a stop's shape_dist_traveled,
 * the vehicle has arrived at that stop. The vehicle's GPS timestamp
 * is recorded as the actual arrival time.
 */
final readonly class ArrivalDetector
{
    private const OFF_ROUTE_THRESHOLD_KM = 0.5;
    private const REDIS_PREFIX           = 'mtw:vehicle_dist:';
    private const REDIS_TTL              = 7200; // 2 hours

    public function __construct(
        private RealtimeRepository $realtimeRepo,
        private TripRepository $tripRepo,
        private RouteSnapper $routeSnapper,
        private EntityManagerInterface $em,
        private ClientInterface $redis,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Scan all active vehicles and detect stop arrivals.
     *
     * @return array{arrivals: int, vehicles: int}
     */
    public function detectArrivals(): array
    {
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicles = $snapshot['vehicles'] ?? [];
        $conn     = $this->em->getConnection();

        $totalArrivals   = 0;
        $vehiclesChecked = 0;

        foreach ($vehicles as $v) {
            $tripId = $v['trip'] ?? null;
            $lat    = $v['lat']  ?? null;
            $lon    = $v['lon']  ?? null;
            $ts     = $v['ts']   ?? null;

            if ($tripId === null || $lat === null || $lon === null || $ts === null) {
                continue;
            }

            $arrivals = $this->processVehicle((string) $tripId, (float) $lat, (float) $lon, (int) $ts, $conn);
            $totalArrivals += $arrivals;
            ++$vehiclesChecked;
        }

        return ['arrivals' => $totalArrivals, 'vehicles' => $vehiclesChecked];
    }

    private function processVehicle(string $tripId, float $lat, float $lon, int $gpsTimestamp, Connection $conn): int
    {
        // Get the trip's shape_id
        $trip = $this->tripRepo->findOneByGtfsId($tripId);
        if ($trip === null || $trip->getShapeId() === null) {
            return 0;
        }

        $shapeId = $trip->getShapeId();

        // Snap GPS to route polyline
        $snap = $this->routeSnapper->snap($shapeId, $lat, $lon);
        if ($snap === null || $snap['perp_distance_km'] > self::OFF_ROUTE_THRESHOLD_KM) {
            return 0; // Off-route or can't snap
        }

        $currentDist = $snap['dist_traveled'];

        // Get last known distance from Redis
        $redisKey = self::REDIS_PREFIX.$tripId;
        $lastDist = $this->redis->get($redisKey);
        $lastDist = $lastDist !== null ? (float) $lastDist : null;

        // Update Redis with current distance
        $this->redis->setex($redisKey, self::REDIS_TTL, (string) $currentDist);

        // First observation for this trip — no comparison possible
        if ($lastDist === null) {
            return 0;
        }

        // Vehicle hasn't progressed (or went backwards — ignore)
        if ($currentDist <= $lastDist) {
            return 0;
        }

        // Find stops between lastDist and currentDist
        return $this->recordPassedStops($trip->getId(), $tripId, $lastDist, $currentDist, $gpsTimestamp, $conn);
    }

    /**
     * Find stops whose shape_dist_traveled falls between lastDist and currentDist,
     * and record actual_arrival_at on matching arrival_log rows.
     */
    private function recordPassedStops(
        int $tripDbId,
        string $tripGtfsId,
        float $lastDist,
        float $currentDist,
        int $gpsTimestamp,
        Connection $conn,
    ): int {
        // Find stops the vehicle passed through
        $stops = $conn->executeQuery(
            'SELECT st.stop_id, s.gtfs_id as stop_gtfs_id, st.shape_dist_traveled
             FROM stop_time st
             JOIN stop s ON s.id = st.stop_id
             WHERE st.trip_id = :trip_id
               AND st.shape_dist_traveled > :last_dist
               AND st.shape_dist_traveled <= :current_dist
             ORDER BY st.shape_dist_traveled',
            [
                'trip_id'      => $tripDbId,
                'last_dist'    => $lastDist,
                'current_dist' => $currentDist,
            ],
        )->fetchAllAssociative();

        if (empty($stops)) {
            return 0;
        }

        $arrivalTime = (new \DateTimeImmutable())->setTimestamp($gpsTimestamp)->format('Y-m-d H:i:s');
        $today       = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow    = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $arrivals    = 0;

        foreach ($stops as $stop) {
            // Backfill actual_arrival_at on all predictions for this trip+stop today
            $updated = $conn->executeStatement(
                'UPDATE arrival_log
                 SET actual_arrival_at = :arrival_time
                 WHERE trip_id = :trip_id
                   AND stop_id = :stop_id
                   AND predicted_at >= :today
                   AND predicted_at < :tomorrow
                   AND actual_arrival_at IS NULL',
                [
                    'arrival_time' => $arrivalTime,
                    'trip_id'      => $tripGtfsId,
                    'stop_id'      => (int) $stop['stop_id'],
                    'today'        => $today,
                    'tomorrow'     => $tomorrow,
                ],
            );

            if ($updated > 0) {
                ++$arrivals;
                $this->logger->debug('Arrival detected', [
                    'trip'    => $tripGtfsId,
                    'stop'    => $stop['stop_gtfs_id'],
                    'time'    => $arrivalTime,
                    'updated' => $updated,
                ]);
            }
        }

        return $arrivals;
    }
}
