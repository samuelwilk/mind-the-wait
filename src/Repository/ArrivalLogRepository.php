<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\BunchingCandidateDto;
use App\Dto\RoutePerformanceHeatmapBucketDto;
use App\Dto\RoutePerformanceMetricsDto;
use App\Dto\StopReliabilityDto;
use App\Entity\ArrivalLog;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use function round;
use function sprintf;

/**
 * @extends BaseRepository<ArrivalLog>
 */
final class ArrivalLogRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, ArrivalLog::class);
    }

    /**
     * Find arrival logs for a specific route within a date range.
     *
     * @return list<ArrivalLog>
     */
    public function findByRouteAndDateRange(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.route = :routeId')
            ->andWhere('a.predictedAt >= :start')
            ->andWhere('a.predictedAt < :end')
            ->setParameter('routeId', $routeId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.predictedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total arrivals logged today.
     */
    public function countToday(): int
    {
        $today    = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :today')
            ->andWhere('a.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete arrival logs older than the specified number of days.
     *
     * @return int Number of deleted rows
     */
    public function deleteOlderThan(int $days): int
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Fetch aggregated arrival performance grouped by day of week and hour bucket.
     *
     * @return list<RoutePerformanceHeatmapBucketDto>
     */
    public function findHeatmapBuckets(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $sql = <<<'SQL'
            SELECT
                EXTRACT(DOW FROM predicted_at) AS day_of_week,
                CASE
                    WHEN EXTRACT(HOUR FROM predicted_at) < 6  THEN 0
                    WHEN EXTRACT(HOUR FROM predicted_at) < 9  THEN 1
                    WHEN EXTRACT(HOUR FROM predicted_at) < 12 THEN 2
                    WHEN EXTRACT(HOUR FROM predicted_at) < 15 THEN 3
                    WHEN EXTRACT(HOUR FROM predicted_at) < 18 THEN 4
                    WHEN EXTRACT(HOUR FROM predicted_at) < 21 THEN 5
                    ELSE 6
                END AS hour_bucket,
                COUNT(*) AS total,
                SUM(
                    CASE
                        WHEN delay_sec IS NOT NULL AND delay_sec BETWEEN -180 AND 180 THEN 1
                        ELSE 0
                    END
                ) AS on_time,
                AVG(
                    CASE
                        WHEN delay_sec IS NOT NULL THEN delay_sec
                        ELSE NULL
                    END
                ) AS avg_delay
            FROM arrival_log
            WHERE route_id = :route_id
              AND predicted_at >= :start_date
              AND predicted_at < :end_date
              AND delay_sec IS NOT NULL
            GROUP BY day_of_week, hour_bucket
            ORDER BY day_of_week, hour_bucket
        SQL;

        $connection = $this->getEntityManager()->getConnection();
        $rows       = $connection->executeQuery(
            $sql,
            [
                'route_id'   => $routeId,
                'start_date' => $start->format('Y-m-d H:i:s'),
                'end_date'   => $end->format('Y-m-d H:i:s'),
            ],
            [
                'route_id'   => Types::INTEGER,
                'start_date' => Types::STRING,
                'end_date'   => Types::STRING,
            ],
        )->fetchAllAssociative();

        $buckets = [];
        foreach ($rows as $row) {
            $dow       = (int) ($row['day_of_week'] ?? 0);
            $dayIndex  = $dow === 0 ? 6 : $dow - 1;
            $hourIndex = (int) $row['hour_bucket'];

            $total  = (int) $row['total'];
            $onTime = (int) $row['on_time'];

            $percentage = $total > 0 ? round(($onTime / $total) * 100, 1) : 0.0;

            $buckets[] = new RoutePerformanceHeatmapBucketDto(
                dayIndex: $dayIndex,
                hourIndex: $hourIndex,
                onTimePercentage: $percentage,
            );
        }

        return $buckets;
    }

    /**
     * Find bunching incident candidates for a date range.
     *
     * Uses window functions to detect when vehicles arrive too close together.
     *
     * @return list<BunchingCandidateDto>
     */
    public function findBunchingCandidates(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $timeWindowSeconds,
    ): array {
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

        $connection = $this->getEntityManager()->getConnection();
        $rows       = $connection->executeQuery(
            $sql,
            [
                'start_date'  => $startDate->format('Y-m-d H:i:s'),
                'end_date'    => $endDate->format('Y-m-d H:i:s'),
                'time_window' => $timeWindowSeconds,
            ],
        )->fetchAllAssociative();

        $candidates = [];
        foreach ($rows as $row) {
            $candidates[] = new BunchingCandidateDto(
                routeId: (int) $row['route_id'],
                stopId: (int) $row['stop_id'],
                bunchingTime: new \DateTimeImmutable($row['bunching_time']),
                vehicleCount: (int) $row['vehicle_count'],
                vehicleIds: (string) $row['vehicle_ids'],
            );
        }

        return $candidates;
    }

    /**
     * Calculate schedule realism ratio for a route.
     *
     * Compares actual travel time vs scheduled travel time across all trips.
     * Returns ratio where:
     * - < 1.0 = buses finish faster than scheduled (over-scheduled)
     * - 1.0 = buses match schedule perfectly
     * - > 1.0 = buses take longer than scheduled (under-scheduled)
     *
     * Returns null if insufficient data (< 5 unique trip instances).
     *
     * @param int                $routeId Route entity ID
     * @param \DateTimeInterface $start   Start of date range
     * @param \DateTimeInterface $end     End of date range
     *
     * @return float|null Average ratio (actual_time / scheduled_time), or null if insufficient data
     */
    public function calculateScheduleRealismRatio(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end): ?float
    {
        $sql = <<<'SQL'
            WITH trip_durations AS (
                SELECT
                    trip_id,
                    EXTRACT(EPOCH FROM (MAX(predicted_arrival_at) - MIN(predicted_arrival_at))) as actual_duration_sec,
                    EXTRACT(EPOCH FROM (MAX(scheduled_arrival_at) - MIN(scheduled_arrival_at))) as scheduled_duration_sec
                FROM arrival_log
                WHERE route_id = :route_id
                  AND predicted_at >= :start_date
                  AND predicted_at < :end_date
                  AND predicted_arrival_at IS NOT NULL
                  AND scheduled_arrival_at IS NOT NULL
                GROUP BY trip_id
                HAVING COUNT(DISTINCT stop_id) >= 3
                   AND EXTRACT(EPOCH FROM (MAX(scheduled_arrival_at) - MIN(scheduled_arrival_at))) > 0
            )
            SELECT
                AVG(actual_duration_sec / scheduled_duration_sec) as avg_ratio,
                COUNT(*) as trip_count
            FROM trip_durations
            WHERE scheduled_duration_sec > 0
        SQL;

        $connection = $this->getEntityManager()->getConnection();
        $row        = $connection->executeQuery(
            $sql,
            [
                'route_id'   => $routeId,
                'start_date' => $start->format('Y-m-d H:i:s'),
                'end_date'   => $end->format('Y-m-d H:i:s'),
            ],
            [
                'route_id'   => Types::INTEGER,
                'start_date' => Types::STRING,
                'end_date'   => Types::STRING,
            ],
        )->fetchAssociative();

        if ($row === false || $row['avg_ratio'] === null) {
            return null;
        }

        $tripCount = (int) $row['trip_count'];

        // Require minimum 5 trip instances for reliable ratio
        if ($tripCount < 5) {
            return null;
        }

        return round((float) $row['avg_ratio'], 3);
    }

    /**
     * Find stop-level reliability data for a route and direction.
     *
     * Returns average delay and on-time percentage for each stop on the route,
     * sorted by stop sequence, helping identify where delays accumulate.
     *
     * @param int                $routeId   Route entity ID
     * @param \DateTimeInterface $start     Start of date range
     * @param \DateTimeInterface $end       End of date range
     * @param int                $direction Direction (0 or 1)
     *
     * @return list<StopReliabilityDto>
     */
    public function findStopReliabilityData(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end, int $direction): array
    {
        $sql = <<<'SQL'
            SELECT
                s.id as stop_id,
                s.name as stop_name,
                AVG(a.delay_sec) as avg_delay_sec,
                COUNT(*) as sample_size,
                SUM(CASE WHEN a.delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time_count,
                MIN(st.stop_sequence) as stop_sequence,
                t.direction as direction
            FROM arrival_log a
            INNER JOIN stop s ON s.id = a.stop_id
            INNER JOIN stop_time st ON st.stop_id = s.id AND st.route_id = a.route_id
            INNER JOIN trip t ON t.id = st.trip_id
            WHERE a.route_id = :route_id
              AND a.predicted_at >= :start_date
              AND a.predicted_at < :end_date
              AND a.delay_sec IS NOT NULL
              AND t.direction = :direction
            GROUP BY s.id, s.name, t.direction
            HAVING COUNT(*) >= 10
            ORDER BY MIN(st.stop_sequence) ASC
        SQL;

        $connection = $this->getEntityManager()->getConnection();
        $rows       = $connection->executeQuery(
            $sql,
            [
                'route_id'   => $routeId,
                'start_date' => $start->format('Y-m-d H:i:s'),
                'end_date'   => $end->format('Y-m-d H:i:s'),
                'direction'  => $direction,
            ],
            [
                'route_id'   => Types::INTEGER,
                'start_date' => Types::STRING,
                'end_date'   => Types::STRING,
                'direction'  => Types::INTEGER,
            ],
        )->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $sampleSize       = (int) $row['sample_size'];
            $onTimeCount      = (int) $row['on_time_count'];
            $onTimePercentage = $sampleSize > 0 ? round(($onTimeCount / $sampleSize) * 100, 1) : 0.0;

            // Determine confidence level based on sample size
            $confidenceLevel = match (true) {
                $sampleSize >= 50 => 'high',
                $sampleSize >= 20 => 'medium',
                default           => 'low',
            };

            $results[] = new StopReliabilityDto(
                stopId: (int) $row['stop_id'],
                stopName: (string) $row['stop_name'],
                avgDelaySec: (int) round((float) $row['avg_delay_sec']),
                onTimePercentage: $onTimePercentage,
                sampleSize: $sampleSize,
                confidenceLevel: $confidenceLevel,
                stopSequence: (int) $row['stop_sequence'],
                direction: (int) $row['direction'],
            );
        }

        return $results;
    }

    /**
     * Aggregate performance metrics for a route on a specific date.
     *
     * Uses SQL aggregation to avoid loading entity collections into PHP.
     */
    public function aggregateMetricsForRoute(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end): RoutePerformanceMetricsDto
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*) as total_predictions,
                SUM(CASE WHEN confidence = 'high' THEN 1 ELSE 0 END) as high_confidence_count,
                SUM(CASE WHEN confidence = 'medium' THEN 1 ELSE 0 END) as medium_confidence_count,
                SUM(CASE WHEN confidence = 'low' THEN 1 ELSE 0 END) as low_confidence_count,
                AVG(CASE WHEN delay_sec IS NOT NULL THEN delay_sec ELSE NULL END) as avg_delay_sec,
                SUM(CASE WHEN delay_sec IS NOT NULL AND delay_sec > 180 THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN delay_sec IS NOT NULL AND delay_sec < -180 THEN 1 ELSE 0 END) as early_count,
                SUM(CASE WHEN delay_sec IS NOT NULL AND delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time_count
            FROM arrival_log
            WHERE route_id = :route_id
              AND predicted_at >= :start_date
              AND predicted_at < :end_date
        SQL;

        $connection = $this->getEntityManager()->getConnection();
        $row        = $connection->executeQuery(
            $sql,
            [
                'route_id'   => $routeId,
                'start_date' => $start->format('Y-m-d H:i:s'),
                'end_date'   => $end->format('Y-m-d H:i:s'),
            ],
            [
                'route_id'   => Types::INTEGER,
                'start_date' => Types::STRING,
                'end_date'   => Types::STRING,
            ],
        )->fetchAssociative();

        if ($row === false) {
            // No data for this route
            return new RoutePerformanceMetricsDto(
                totalPredictions: 0,
                highConfidenceCount: 0,
                mediumConfidenceCount: 0,
                lowConfidenceCount: 0,
                avgDelaySec: null,
                delays: [],
                onTimeCount: 0,
                lateCount: 0,
                earlyCount: 0,
                onTimePercentage: null,
                latePercentage: null,
                earlyPercentage: null,
            );
        }

        $totalPredictions      = (int) $row['total_predictions'];
        $highConfidenceCount   = (int) $row['high_confidence_count'];
        $mediumConfidenceCount = (int) $row['medium_confidence_count'];
        $lowConfidenceCount    = (int) $row['low_confidence_count'];
        $avgDelaySec           = $row['avg_delay_sec'] !== null ? (int) round((float) $row['avg_delay_sec']) : null;
        $lateCount             = (int) $row['late_count'];
        $earlyCount            = (int) $row['early_count'];
        $onTimeCount           = (int) $row['on_time_count'];

        // Fetch all delays for median calculation (cannot easily do in SQL without complex queries)
        $delaysSql = 'SELECT delay_sec FROM arrival_log WHERE route_id = :route_id AND predicted_at >= :start_date AND predicted_at < :end_date AND delay_sec IS NOT NULL ORDER BY delay_sec';
        $delays    = $connection->executeQuery(
            $delaysSql,
            [
                'route_id'   => $routeId,
                'start_date' => $start->format('Y-m-d H:i:s'),
                'end_date'   => $end->format('Y-m-d H:i:s'),
            ],
        )->fetchFirstColumn();

        $delaysInt = array_map('intval', $delays);

        // Calculate percentages
        $totalWithDelay   = $onTimeCount + $lateCount + $earlyCount;
        $onTimePercentage = $totalWithDelay > 0 ? round(($onTimeCount / $totalWithDelay) * 100, 2) : null;
        $latePercentage   = $totalWithDelay > 0 ? round(($lateCount / $totalWithDelay) * 100, 2) : null;
        $earlyPercentage  = $totalWithDelay > 0 ? round(($earlyCount / $totalWithDelay) * 100, 2) : null;

        return new RoutePerformanceMetricsDto(
            totalPredictions: $totalPredictions,
            highConfidenceCount: $highConfidenceCount,
            mediumConfidenceCount: $mediumConfidenceCount,
            lowConfidenceCount: $lowConfidenceCount,
            avgDelaySec: $avgDelaySec,
            delays: $delaysInt,
            onTimeCount: $onTimeCount,
            lateCount: $lateCount,
            earlyCount: $earlyCount,
            onTimePercentage: $onTimePercentage,
            latePercentage: $latePercentage,
            earlyPercentage: $earlyPercentage,
        );
    }
}
