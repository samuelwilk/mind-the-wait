<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\BunchingCandidateDto;
use App\Dto\RoutePerformanceHeatmapBucketDto;
use App\Dto\RoutePerformanceMetricsDto;
use App\Dto\StopReliabilityDto;
use App\Entity\ArrivalLog;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function round;
use function sprintf;

/**
 * @extends BaseRepository<ArrivalLog>
 */
final class ArrivalLogRepository extends BaseRepository
{
    private const ANALYTICS_CACHE_TTL = 3600;

    public function __construct(
        EntityManagerInterface $em,
        ManagerRegistry $registry,
        #[Autowire(service: 'doctrine.result_cache_pool')]
        private readonly CacheItemPoolInterface $resultCache,
    ) {
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
     * Find stop-level reliability data for a route.
     *
     * Returns average delay and on-time percentage for each stop on the route,
     * sorted by stop sequence, helping identify where delays accumulate.
     * Includes direction field for future direction-split implementation.
     *
     * @param int                $routeId Route entity ID
     * @param \DateTimeInterface $start   Start of date range
     * @param \DateTimeInterface $end     End of date range
     *
     * @return list<StopReliabilityDto>
     */
    public function findStopReliabilityData(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end): array
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
            INNER JOIN stop_time st ON st.stop_id = s.id
            INNER JOIN trip t ON t.id = st.trip_id AND t.route_id = a.route_id
            WHERE a.route_id = :route_id
              AND a.predicted_at >= :start_date
              AND a.predicted_at < :end_date
              AND a.delay_sec IS NOT NULL
            GROUP BY s.id, s.name, t.direction
            HAVING COUNT(*) >= 10
            ORDER BY MIN(st.stop_sequence) ASC, t.direction ASC
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

    // ================== Analytics Methods ==================

    /**
     * Find top performing vehicles by on-time percentage.
     *
     * @param \DateTimeInterface $start Start date
     * @param \DateTimeInterface $end   End date
     * @param int                $limit Maximum vehicles to return
     *
     * @return list<\App\Dto\Analytics\VehiclePerformanceDto>
     */
    public function findTopPerformingVehicles(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $limit = 10,
    ): array {
        return $this->findVehiclePerformance($start, $end, 'DESC', $limit);
    }

    /**
     * Find worst performing vehicles by on-time percentage.
     *
     * @param \DateTimeInterface $start Start date
     * @param \DateTimeInterface $end   End date
     * @param int                $limit Maximum vehicles to return
     *
     * @return list<\App\Dto\Analytics\VehiclePerformanceDto>
     */
    public function findWorstPerformingVehicles(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $limit = 10,
    ): array {
        return $this->findVehiclePerformance($start, $end, 'ASC', $limit);
    }

    /**
     * Find vehicle performance metrics ordered by on-time percentage.
     *
     * @return list<\App\Dto\Analytics\VehiclePerformanceDto>
     */
    private function findVehiclePerformance(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        string $order,
        int $limit,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        // Main query to get vehicle performance
        $sql = sprintf('
            WITH vehicle_stats AS (
                SELECT
                    a.vehicle_id,
                    COUNT(*) as total_predictions,
                    SUM(CASE WHEN a.delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time_count,
                    AVG(a.delay_sec) as avg_delay,
                    COUNT(DISTINCT a.route_id) as routes_served
                FROM arrival_log a
                WHERE a.predicted_at >= :start
                    AND a.predicted_at < :end
                    AND a.delay_sec IS NOT NULL
                GROUP BY a.vehicle_id
                HAVING COUNT(*) >= 50
            )
            SELECT
                vs.vehicle_id,
                vs.total_predictions,
                ROUND(((vs.on_time_count::numeric / vs.total_predictions) * 100)::numeric, 1) as on_time_pct,
                ROUND(vs.avg_delay::numeric) as avg_delay,
                vs.routes_served
            FROM vehicle_stats vs
            ORDER BY on_time_pct %s
            LIMIT :limit
        ', $order);

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ];

        $rows = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'vehicle_perf_'.md5(serialize($params).$order), $this->resultCache),
        )->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new \App\Dto\Analytics\VehiclePerformanceDto(
                vehicleId: (string) $row['vehicle_id'],
                totalPredictions: (int) $row['total_predictions'],
                onTimePercentage: (float) $row['on_time_pct'],
                avgDelaySec: (int) $row['avg_delay'],
                routesServed: (int) $row['routes_served'],
            );
        }

        return $results;
    }

    /**
     * Find hourly performance trends across all routes.
     *
     * @return list<\App\Dto\Analytics\HourlyTrendDto>
     */
    public function findHourlyTrends(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                EXTRACT(HOUR FROM a.predicted_at) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN a.delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time_count,
                AVG(a.delay_sec) as avg_delay
            FROM arrival_log a
            WHERE a.predicted_at >= :start
                AND a.predicted_at < :end
                AND a.delay_sec IS NOT NULL
            GROUP BY hour
            ORDER BY hour
        ';

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];

        $rows = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'hourly_trends_'.md5(serialize($params)), $this->resultCache),
        )->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $total       = (int) $row['total'];
            $onTimeCount = (int) $row['on_time_count'];
            $onTimePct   = $total > 0 ? round(($onTimeCount / $total) * 100, 1) : 0.0;

            $results[] = new \App\Dto\Analytics\HourlyTrendDto(
                hour: (int) $row['hour'],
                avgOnTimePercentage: $onTimePct,
                avgDelaySec: (int) round((float) ($row['avg_delay'] ?? 0)),
                sampleCount: $total,
            );
        }

        return $results;
    }

    /**
     * Count distinct vehicles in a date range.
     */
    public function countDistinctVehicles(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): int {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT COUNT(DISTINCT vehicle_id) as count
            FROM arrival_log
            WHERE predicted_at >= :start
                AND predicted_at < :end
        ';

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];

        $result = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'distinct_vehicles_'.md5(serialize($params)), $this->resultCache),
        )->fetchAssociative();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Calculate overall prediction accuracy summary.
     *
     * @return array{mae: float, bias: float, sample_size: int, within_1_min: float, within_2_min: float, within_3_min: float, within_5_min: float}|null
     */
    public function findPredictionAccuracySummary(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): ?array {
        $conn = $this->getEntityManager()->getConnection();

        // Use actual_arrival_at (true accuracy) when available, fall back to delay_sec (schedule adherence)
        $sql = '
            SELECT
                AVG(ABS(error_sec))::numeric AS mae,
                AVG(error_sec)::numeric AS bias,
                COUNT(*) AS sample_size,
                ROUND(SUM(CASE WHEN ABS(error_sec) <= 60  THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS within_1_min,
                ROUND(SUM(CASE WHEN ABS(error_sec) <= 120 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS within_2_min,
                ROUND(SUM(CASE WHEN ABS(error_sec) <= 180 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS within_3_min,
                ROUND(SUM(CASE WHEN ABS(error_sec) <= 300 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS within_5_min
            FROM (
                SELECT
                    CASE
                        WHEN actual_arrival_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (predicted_arrival_at - actual_arrival_at))
                        ELSE delay_sec
                    END AS error_sec
                FROM arrival_log
                WHERE predicted_at >= :start
                  AND predicted_at < :end
                  AND (actual_arrival_at IS NOT NULL OR delay_sec IS NOT NULL)
            ) sub
        ';

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];

        $row = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'pred_accuracy_summary_'.md5(serialize($params)), $this->resultCache),
        )->fetchAssociative();

        if ($row === false || (int) $row['sample_size'] === 0) {
            return null;
        }

        return [
            'mae'          => round((float) $row['mae'], 1),
            'bias'         => round((float) $row['bias'], 1),
            'sample_size'  => (int) $row['sample_size'],
            'within_1_min' => (float) $row['within_1_min'],
            'within_2_min' => (float) $row['within_2_min'],
            'within_3_min' => (float) $row['within_3_min'],
            'within_5_min' => (float) $row['within_5_min'],
        ];
    }

    /**
     * Calculate prediction accuracy broken down by confidence level.
     *
     * @return list<\App\Dto\Analytics\ConfidenceAccuracyDto>
     */
    public function findPredictionAccuracyByConfidence(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT
                confidence,
                AVG(ABS(error_sec))::numeric AS mae,
                AVG(error_sec)::numeric AS bias,
                COUNT(*) AS sample_size,
                ROUND(SUM(CASE WHEN ABS(error_sec) <= 180 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS within_3_min
            FROM (
                SELECT
                    confidence,
                    CASE
                        WHEN actual_arrival_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (predicted_arrival_at - actual_arrival_at))
                        ELSE delay_sec
                    END AS error_sec
                FROM arrival_log
                WHERE predicted_at >= :start
                  AND predicted_at < :end
                  AND (actual_arrival_at IS NOT NULL OR delay_sec IS NOT NULL)
            ) sub
            GROUP BY confidence
            ORDER BY CASE confidence WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END
        ";

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];

        $rows = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'pred_accuracy_confidence_'.md5(serialize($params)), $this->resultCache),
        )->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new \App\Dto\Analytics\ConfidenceAccuracyDto(
                confidence: (string) $row['confidence'],
                maeSeconds: round((float) $row['mae'], 1),
                biasSeconds: round((float) $row['bias'], 1),
                within3Min: (float) $row['within_3_min'],
                sampleSize: (int) $row['sample_size'],
            );
        }

        return $results;
    }

    /**
     * Calculate prediction accuracy broken down by hour of day.
     *
     * @return list<\App\Dto\Analytics\HourlyAccuracyDto>
     */
    public function findPredictionAccuracyByHour(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                hour,
                AVG(ABS(error_sec))::numeric AS mae,
                ROUND(SUM(CASE WHEN ABS(error_sec) <= 180 THEN 1 ELSE 0 END)::numeric / NULLIF(COUNT(*), 0) * 100, 1) AS within_3_min,
                COUNT(*) AS sample_size
            FROM (
                SELECT
                    EXTRACT(HOUR FROM predicted_at) AS hour,
                    CASE
                        WHEN actual_arrival_at IS NOT NULL
                        THEN EXTRACT(EPOCH FROM (predicted_arrival_at - actual_arrival_at))
                        ELSE delay_sec
                    END AS error_sec
                FROM arrival_log
                WHERE predicted_at >= :start
                  AND predicted_at < :end
                  AND (actual_arrival_at IS NOT NULL OR delay_sec IS NOT NULL)
            ) sub
            GROUP BY hour
            ORDER BY hour
        ';

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];

        $rows = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'pred_accuracy_hourly_'.md5(serialize($params)), $this->resultCache),
        )->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new \App\Dto\Analytics\HourlyAccuracyDto(
                hour: (int) $row['hour'],
                maeSeconds: round((float) $row['mae'], 1),
                within3Min: (float) $row['within_3_min'],
                sampleSize: (int) $row['sample_size'],
            );
        }

        return $results;
    }

    /**
     * Calculate prediction accuracy grouped by stops_away.
     * Only meaningful when actual_arrival_at is populated.
     *
     * @return list<array{stops_away: int, mae: float, within_3_min: float, sample_size: int}>
     */
    public function findAccuracyByStopsAway(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                stops_away,
                AVG(ABS(EXTRACT(EPOCH FROM (predicted_arrival_at - actual_arrival_at))))::numeric AS mae,
                ROUND(
                    SUM(CASE WHEN ABS(EXTRACT(EPOCH FROM (predicted_arrival_at - actual_arrival_at))) <= 180 THEN 1 ELSE 0 END)::numeric
                    / NULLIF(COUNT(*), 0) * 100, 1
                ) AS within_3_min,
                COUNT(*) AS sample_size
            FROM arrival_log
            WHERE predicted_at >= :start
              AND predicted_at < :end
              AND actual_arrival_at IS NOT NULL
              AND stops_away IS NOT NULL
              AND stops_away BETWEEN 1 AND 15
            GROUP BY stops_away
            ORDER BY stops_away
        ';

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];

        $rows = $conn->executeQuery(
            $sql,
            $params,
            [],
            new QueryCacheProfile(self::ANALYTICS_CACHE_TTL, 'pred_accuracy_stops_away_'.md5(serialize($params)), $this->resultCache),
        )->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'stops_away'   => (int) $row['stops_away'],
                'mae'          => round((float) $row['mae'], 1),
                'within_3_min' => (float) $row['within_3_min'],
                'sample_size'  => (int) $row['sample_size'],
            ];
        }

        return $results;
    }
}
