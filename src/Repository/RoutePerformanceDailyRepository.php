<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\RoutePerformanceSummaryDto;
use App\Dto\SystemComparisonDto;
use App\Dto\TemperatureBucketDto;
use App\Dto\TemperatureThresholdDto;
use App\Dto\WeatherImpactMatrixDto;
use App\Dto\WeatherPerformanceDto;
use App\Entity\Route;
use App\Entity\RoutePerformanceDaily;
use App\Enum\WeatherCondition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use function array_slice;
use function count;
use function sprintf;

/**
 * @extends BaseRepository<RoutePerformanceDaily>
 */
final class RoutePerformanceDailyRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, RoutePerformanceDaily::class);
    }

    /**
     * Find performance records for a route within a date range.
     *
     * @return list<RoutePerformanceDaily>
     */
    public function findByRouteAndDateRange(int $routeId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.route = :routeId')
            ->andWhere('p.date >= :start')
            ->andWhere('p.date < :end')
            ->setParameter('routeId', $routeId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find or create a performance record for a route and date.
     */
    public function findOrCreate(int $routeId, \DateTimeImmutable $date): RoutePerformanceDaily
    {
        $existing = $this->createQueryBuilder('p')
            ->where('p.route = :routeId')
            ->andWhere('p.date = :date')
            ->setParameter('routeId', $routeId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing !== null) {
            return $existing;
        }

        $route = $this->getEntityManager()->getReference(Route::class, $routeId);

        $performance = new RoutePerformanceDaily();
        $performance->setRoute($route);
        $performance->setDate($date);

        return $performance;
    }

    /**
     * Get average on-time percentage for a route over the last N days.
     */
    public function getAverageOnTimePercentage(int $routeId, int $days): ?float
    {
        $startDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.onTimePercentage) as avg_on_time')
            ->where('p.route = :routeId')
            ->andWhere('p.date >= :startDate')
            ->setParameter('routeId', $routeId)
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : null;
    }

    /**
     * Delete performance records older than the specified number of days.
     *
     * @return int Number of deleted rows
     */
    public function deleteOlderThan(int $days): int
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.date < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Find routes with winter performance comparison (clear vs snow).
     *
     * Returns routes where both clear and snow data exists,
     * ordered by performance drop (biggest impact first).
     *
     * @param int $minDays Minimum days of data required for each condition
     * @param int $limit   Maximum number of routes to return
     *
     * @return list<RoutePerformanceSummaryDto>
     */
    public function findWinterPerformanceComparison(int $minDays = 3, int $limit = 10): array
    {
        // Query 1: Get clear weather performance
        $qb           = $this->createQueryBuilder('p');
        $clearResults = $qb->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition = :clear')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('clear', 'clear')
            ->groupBy('r.id', 'r.shortName', 'r.longName')
            ->having('COUNT(p.id) >= :minDays')
            ->setParameter('minDays', $minDays)
            ->getQuery()
            ->getResult();

        // Query 2: Get snow weather performance
        $qb2         = $this->createQueryBuilder('p');
        $snowResults = $qb2->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition = :snow')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('snow', 'snow')
            ->groupBy('r.id', 'r.shortName', 'r.longName')
            ->having('COUNT(p.id) >= :minDays')
            ->setParameter('minDays', $minDays)
            ->getQuery()
            ->getResult();

        // Combine results
        $clearByRoute = [];
        foreach ($clearResults as $row) {
            $clearByRoute[(int) $row['id']] = [
                'shortName' => $row['shortName'],
                'longName'  => $row['longName'],
                'perf'      => (float) $row['avgPerf'],
                'days'      => (int) $row['days'],
            ];
        }

        $snowByRoute = [];
        foreach ($snowResults as $row) {
            $snowByRoute[(int) $row['id']] = [
                'perf' => (float) $row['avgPerf'],
                'days' => (int) $row['days'],
            ];
        }

        // Find routes that exist in both and create DTOs
        $combined = [];
        foreach ($clearByRoute as $routeId => $clearData) {
            if (isset($snowByRoute[$routeId])) {
                $clearPerf       = $clearData['perf'];
                $snowPerf        = $snowByRoute[$routeId]['perf'];
                $performanceDrop = $clearPerf - $snowPerf;

                $combined[] = [
                    'dto' => new RoutePerformanceSummaryDto(
                        routeId: (string) $routeId,
                        shortName: $clearData['shortName'],
                        longName: $clearData['longName'],
                        clearPerformance: $clearPerf,
                        snowPerformance: $snowPerf,
                        performanceDrop: $performanceDrop,
                        daysAnalyzed: min($clearData['days'], $snowByRoute[$routeId]['days']),
                    ),
                    'delta' => $performanceDrop,
                ];
            }
        }

        // Sort by delta (biggest impact first)
        usort($combined, fn ($a, $b) => $b['delta'] <=> $a['delta']);

        // Extract DTOs and limit
        $results = array_map(fn ($item) => $item['dto'], $combined);

        return array_slice($results, 0, $limit);
    }

    /**
     * Find performance data grouped by temperature bucket.
     *
     * Groups temperatures into 5°C buckets (-35, -30, -25, ..., 30, 35).
     *
     * @return list<TemperatureBucketDto>
     */
    public function findPerformanceByTemperatureBucket(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                FLOOR(w.temperature_celsius / 5) * 5 as temp_bucket,
                AVG(p.on_time_percentage) as avg_performance,
                COUNT(p.id) as observation_count
            FROM route_performance_daily p
            LEFT JOIN weather_observation w ON p.weather_observation_id = w.id
            WHERE w.temperature_celsius IS NOT NULL
                AND p.on_time_percentage IS NOT NULL
            GROUP BY temp_bucket
            ORDER BY temp_bucket ASC
        ';

        $results = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(
            fn (array $row) => new TemperatureBucketDto(
                temperatureBucket: (int) $row['temp_bucket'],
                avgPerformance: round((float) $row['avg_performance'], 1),
                observationCount: (int) $row['observation_count'],
            ),
            $results
        );
    }

    /**
     * Find performance comparison above/below temperature threshold.
     *
     * @param float $threshold Temperature threshold in Celsius (e.g., -20)
     *
     * @return array{above: TemperatureThresholdDto, below: TemperatureThresholdDto}
     */
    public function findPerformanceByTemperatureThreshold(float $threshold = -20.0): array
    {
        // Query 1: Performance above threshold
        $qb          = $this->createQueryBuilder('p');
        $aboveResult = $qb->select('AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.temperatureCelsius >= :threshold')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleResult();

        // Query 2: Performance below threshold
        $qb2         = $this->createQueryBuilder('p');
        $belowResult = $qb2->select('AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.temperatureCelsius < :threshold')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleResult();

        return [
            'above' => new TemperatureThresholdDto(
                avgPerformance: round((float) ($aboveResult['avgPerf'] ?? 0.0), 1),
                dayCount: (int) ($aboveResult['days'] ?? 0),
            ),
            'below' => new TemperatureThresholdDto(
                avgPerformance: round((float) ($belowResult['avgPerf'] ?? 0.0), 1),
                dayCount: (int) ($belowResult['days'] ?? 0),
            ),
        ];
    }

    /**
     * Find weather impact matrix (all routes × all weather conditions).
     *
     * @return list<WeatherImpactMatrixDto>
     */
    public function findWeatherImpactMatrix(): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select(
            'r.shortName',
            'w.weatherCondition',
            'AVG(p.onTimePercentage) as avgPerformance'
        )
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition IS NOT NULL')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->groupBy('r.id', 'r.shortName', 'w.weatherCondition')
            ->orderBy('r.shortName', 'ASC')
            ->addOrderBy('w.weatherCondition', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_map(
            fn (array $row) => new WeatherImpactMatrixDto(
                routeShortName: $row['shortName'],
                weatherCondition: WeatherCondition::fromString($row['weatherCondition']),
                avgPerformance: round((float) $row['avgPerformance'], 1),
            ),
            $results
        );
    }

    /**
     * Find worst performing weather condition overall.
     */
    public function findWorstPerformingWeatherCondition(): ?WeatherPerformanceDto
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select(
            'w.weatherCondition',
            'AVG(p.onTimePercentage) as avgPerformance',
            'COUNT(p.id) as dayCount',
            'AVG(w.temperatureCelsius) as avgTemperature'
        )
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition IS NOT NULL')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->groupBy('w.weatherCondition')
            ->orderBy('avgPerformance', 'ASC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result === null) {
            return null;
        }

        return new WeatherPerformanceDto(
            weatherCondition: WeatherCondition::fromString($result['weatherCondition']),
            avgPerformance: round((float) $result['avgPerformance'], 1),
            dayCount: (int) $result['dayCount'],
            avgTemperature: round((float) ($result['avgTemperature'] ?? 0.0), 1),
        );
    }

    /**
     * Find historical top performers based on average on-time percentage.
     *
     * @param int $days    Number of days to analyze
     * @param int $minDays Minimum days of data required per route
     * @param int $limit   Maximum number of routes to return
     *
     * @return list<HistoricalPerformerDto>
     */
    public function findHistoricalTopPerformers(int $days = 30, int $minDays = 3, int $limit = 5): array
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify(sprintf('-%d days', $days));

        $qb = $this->createQueryBuilder('p');
        $qb->select(
            'r.gtfsId',
            'r.shortName',
            'r.longName',
            'r.colour',
            'AVG(p.onTimePercentage) as avgOnTime',
            'COUNT(p.id) as daysCount'
        )
            ->join('p.route', 'r')
            ->where('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('r.id', 'r.gtfsId', 'r.shortName', 'r.longName', 'r.colour')
            ->having('COUNT(p.id) >= :minDays')
            ->setParameter('minDays', $minDays)
            ->orderBy('avgOnTime', 'DESC')
            ->setMaxResults($limit);

        $results = $qb->getQuery()->getResult();

        return array_map(
            fn (array $row) => new \App\Dto\HistoricalPerformerDto(
                gtfsId: $row['gtfsId'],
                shortName: $row['shortName'],
                longName: $row['longName'],
                avgOnTimePercent: round((float) $row['avgOnTime'], 1),
                daysCount: (int) $row['daysCount'],
                grade: $this->onTimePercentageToGrade((float) $row['avgOnTime']),
                colour: $row['colour'],
            ),
            $results
        );
    }

    /**
     * Find historical worst performers based on average on-time percentage.
     *
     * @param int $days    Number of days to analyze
     * @param int $minDays Minimum days of data required per route
     * @param int $limit   Maximum number of routes to return
     *
     * @return list<HistoricalPerformerDto>
     */
    public function findHistoricalWorstPerformers(int $days = 30, int $minDays = 3, int $limit = 5): array
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify(sprintf('-%d days', $days));

        $qb = $this->createQueryBuilder('p');
        $qb->select(
            'r.gtfsId',
            'r.shortName',
            'r.longName',
            'r.colour',
            'AVG(p.onTimePercentage) as avgOnTime',
            'COUNT(p.id) as daysCount'
        )
            ->join('p.route', 'r')
            ->where('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('r.id', 'r.gtfsId', 'r.shortName', 'r.longName', 'r.colour')
            ->having('COUNT(p.id) >= :minDays')
            ->setParameter('minDays', $minDays)
            ->orderBy('avgOnTime', 'ASC')  // Worst first
            ->setMaxResults($limit);

        $results = $qb->getQuery()->getResult();

        return array_map(
            fn (array $row) => new \App\Dto\HistoricalPerformerDto(
                gtfsId: $row['gtfsId'],
                shortName: $row['shortName'],
                longName: $row['longName'],
                avgOnTimePercent: round((float) $row['avgOnTime'], 1),
                daysCount: (int) $row['daysCount'],
                grade: $this->onTimePercentageToGrade((float) $row['avgOnTime']),
                colour: $row['colour'],
            ),
            $results
        );
    }

    /**
     * Find weather impact for a specific route.
     *
     * @param int                $routeId   Route ID
     * @param \DateTimeImmutable $startDate Start date
     * @param \DateTimeImmutable $endDate   End date
     *
     * @return list<RouteWeatherPerformanceDto>
     */
    public function findWeatherImpactByRoute(
        int $routeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): array {
        $qb = $this->createQueryBuilder('p');
        $qb->select(
            'w.weatherCondition',
            'AVG(p.onTimePercentage) as avgPerformance',
            'COUNT(p.id) as dayCount'
        )
            ->leftJoin('p.weatherObservation', 'w')
            ->where('p.route = :routeId')
            ->andWhere('p.date >= :startDate')
            ->andWhere('p.date < :endDate')
            ->andWhere('w.weatherCondition IS NOT NULL')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('routeId', $routeId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('w.weatherCondition')
            ->orderBy('avgPerformance', 'DESC');

        $results = $qb->getQuery()->getResult();

        return array_map(
            fn (array $row) => new \App\Dto\RouteWeatherPerformanceDto(
                weatherCondition: WeatherCondition::fromString($row['weatherCondition']),
                avgPerformance: round((float) $row['avgPerformance'], 1),
                dayCount: (int) $row['dayCount'],
            ),
            $results
        );
    }

    /**
     * Get system-wide median on-time percentage for a date range.
     *
     * Used for Bayesian adjustment of low-sample-size routes.
     */
    public function getSystemMedianPerformance(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): float {
        $qb = $this->createQueryBuilder('p');

        $results = $qb
            ->select('p.onTimePercentage')
            ->where('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getScalarResult();

        if (count($results) === 0) {
            return 75.0; // Default system baseline if no data
        }

        // Extract values and sort for median calculation
        $values = array_map(fn (array $r) => (float) $r['onTimePercentage'], $results);
        sort($values);

        $count = count($values);
        $mid   = (int) floor($count / 2);

        // Calculate median
        return $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }

    /**
     * Get route performance ranking relative to all other routes in the system.
     *
     * Returns system comparison data showing how this route ranks among all routes.
     *
     * @param int                $routeId   Route entity ID
     * @param \DateTimeImmutable $startDate Start of date range
     * @param \DateTimeImmutable $endDate   End of date range
     *
     * @return SystemComparisonDto|null Null if route has no data
     */
    public function getRoutePerformanceRanking(
        int $routeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): ?SystemComparisonDto {
        // Get average performance for all routes in date range
        $qb = $this->createQueryBuilder('p');

        $results = $qb
            ->select('IDENTITY(p.route) as route_id, AVG(p.onTimePercentage) as avg_performance')
            ->where('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('p.route')
            ->having('COUNT(p.id) >= 5') // Require minimum 5 days of data
            ->orderBy('avg_performance', 'DESC')
            ->getQuery()
            ->getScalarResult();

        if (count($results) === 0) {
            return null;
        }

        // Find this route's performance and rank
        $routePerformance = null;
        $routeRank        = null;

        foreach ($results as $index => $result) {
            if ((int) $result['route_id'] === $routeId) {
                $routePerformance = (float) $result['avg_performance'];
                $routeRank        = $index + 1; // 1-indexed rank
                break;
            }
        }

        // Route not found in results (no data or insufficient days)
        if ($routeRank === null || $routePerformance === null) {
            return null;
        }

        // Get system median
        $systemMedian = $this->getSystemMedianPerformance($startDate, $endDate);

        return new SystemComparisonDto(
            routeRank: $routeRank,
            totalRoutes: count($results),
            routePerformance: $routePerformance,
            systemMedianPerformance: $systemMedian,
        );
    }

    /**
     * Calculate system-wide trend comparing today vs yesterday in a single query.
     *
     * @return float Percentage point change (positive = improvement, negative = decline)
     */
    public function calculateSystemTrendVsYesterday(): float
    {
        $today     = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $tomorrow  = $today->modify('+1 day');

        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                AVG(CASE WHEN p.date >= :today AND p.date < :tomorrow THEN p.on_time_percentage END) as today_avg,
                AVG(CASE WHEN p.date >= :yesterday AND p.date < :today THEN p.on_time_percentage END) as yesterday_avg
            FROM route_performance_daily p
            WHERE p.on_time_percentage IS NOT NULL
                AND p.date >= :yesterday
                AND p.date < :tomorrow
        ';

        $result = $conn->executeQuery($sql, [
            'today'     => $today->format('Y-m-d'),
            'tomorrow'  => $tomorrow->format('Y-m-d'),
            'yesterday' => $yesterday->format('Y-m-d'),
        ])->fetchAssociative();

        if ($result === false || $result['today_avg'] === null || $result['yesterday_avg'] === null) {
            return 0.0;
        }

        return round((float) $result['today_avg'] - (float) $result['yesterday_avg'], 1);
    }

    /**
     * Convert on-time percentage to letter grade.
     */
    private function onTimePercentageToGrade(float $onTimePercentage): string
    {
        return match (true) {
            $onTimePercentage >= 90 => 'A',
            $onTimePercentage >= 80 => 'B',
            $onTimePercentage >= 70 => 'C',
            $onTimePercentage >= 60 => 'D',
            default                 => 'F',
        };
    }

    // ================== Analytics Methods ==================

    /**
     * Find route comparison data for selected routes within a date range.
     *
     * @param list<int>          $routeIds Route entity IDs to compare
     * @param \DateTimeImmutable $start    Start date
     * @param \DateTimeImmutable $end      End date
     *
     * @return list<\App\Dto\Analytics\RouteComparisonDto>
     */
    public function findRouteComparisonData(
        array $routeIds,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        if (count($routeIds) === 0) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                r.id as route_id,
                r.short_name,
                r.long_name,
                r.colour,
                AVG(p.on_time_percentage) as avg_on_time,
                AVG(p.avg_delay_sec) as avg_delay,
                SUM(p.total_predictions) as total_predictions,
                COUNT(p.id) as days_with_data,
                MIN(CASE WHEN p.on_time_percentage IS NOT NULL THEN p.date END) as first_date,
                MAX(CASE WHEN p.on_time_percentage = (
                    SELECT MAX(p2.on_time_percentage)
                    FROM route_performance_daily p2
                    WHERE p2.route_id = r.id AND p2.date >= :start AND p2.date < :end
                ) THEN p.date END) as best_day,
                MAX(p.on_time_percentage) as best_day_pct,
                MIN(CASE WHEN p.on_time_percentage = (
                    SELECT MIN(p3.on_time_percentage)
                    FROM route_performance_daily p3
                    WHERE p3.route_id = r.id AND p3.date >= :start AND p3.date < :end AND p3.on_time_percentage IS NOT NULL
                ) THEN p.date END) as worst_day,
                MIN(NULLIF(p.on_time_percentage, NULL)) as worst_day_pct
            FROM route r
            INNER JOIN route_performance_daily p ON p.route_id = r.id
            WHERE r.id IN (:route_ids)
                AND p.date >= :start
                AND p.date < :end
                AND p.on_time_percentage IS NOT NULL
            GROUP BY r.id, r.short_name, r.long_name, r.colour
            ORDER BY avg_on_time DESC
        ';

        // Handle IN clause manually for DBAL
        $placeholders = [];
        $params       = ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        foreach ($routeIds as $i => $id) {
            $placeholders[]          = ":route_id_{$i}";
            $params["route_id_{$i}"] = $id;
        }
        $sql = str_replace(':route_ids', implode(',', $placeholders), $sql);

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new \App\Dto\Analytics\RouteComparisonDto(
                routeId: (int) $row['route_id'],
                shortName: $row['short_name'],
                longName: $row['long_name'],
                colour: $row['colour'],
                avgOnTimePercentage: round((float) $row['avg_on_time'], 1),
                avgDelaySec: (int) round((float) ($row['avg_delay'] ?? 0)),
                totalPredictions: (int) $row['total_predictions'],
                daysWithData: (int) $row['days_with_data'],
                bestDay: $row['best_day']                 !== null ? new \DateTimeImmutable($row['best_day']) : null,
                bestDayPercentage: $row['best_day_pct']   !== null ? round((float) $row['best_day_pct'], 1) : null,
                worstDay: $row['worst_day']               !== null ? new \DateTimeImmutable($row['worst_day']) : null,
                worstDayPercentage: $row['worst_day_pct'] !== null ? round((float) $row['worst_day_pct'], 1) : null,
            );
        }

        return $results;
    }

    /**
     * Find all routes with performance data in the date range.
     *
     * @return list<array{id: int, short_name: string, long_name: string}>
     */
    public function findRoutesWithData(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $qb = $this->createQueryBuilder('p');
        $qb->select('DISTINCT r.id, r.shortName as short_name, r.longName as long_name')
            ->join('p.route', 'r')
            ->where('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.shortName', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Find day-of-week performance trends.
     *
     * @return list<\App\Dto\Analytics\DayOfWeekTrendDto>
     */
    public function findDayOfWeekTrends(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                EXTRACT(DOW FROM p.date) as day_of_week,
                AVG(p.on_time_percentage) as avg_on_time,
                AVG(p.avg_delay_sec) as avg_delay,
                COUNT(*) as sample_count
            FROM route_performance_daily p
            WHERE p.date >= :start
                AND p.date < :end
                AND p.on_time_percentage IS NOT NULL
            GROUP BY day_of_week
            ORDER BY day_of_week
        ';

        $rows = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
        ])->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new \App\Dto\Analytics\DayOfWeekTrendDto(
                dayOfWeek: (int) $row['day_of_week'],
                avgOnTimePercentage: round((float) $row['avg_on_time'], 1),
                avgDelaySec: (int) round((float) ($row['avg_delay'] ?? 0)),
                sampleCount: (int) $row['sample_count'],
            );
        }

        return $results;
    }

    /**
     * Find monthly performance trends.
     *
     * @return list<\App\Dto\Analytics\MonthlyTrendDto>
     */
    public function findMonthlyTrends(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                EXTRACT(YEAR FROM p.date) as year,
                EXTRACT(MONTH FROM p.date) as month,
                AVG(p.on_time_percentage) as avg_on_time,
                AVG(p.avg_delay_sec) as avg_delay,
                COUNT(DISTINCT p.date) as days_with_data,
                SUM(p.total_predictions) as total_predictions
            FROM route_performance_daily p
            WHERE p.date >= :start
                AND p.date < :end
                AND p.on_time_percentage IS NOT NULL
            GROUP BY year, month
            ORDER BY year, month
        ';

        $rows = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
        ])->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new \App\Dto\Analytics\MonthlyTrendDto(
                year: (int) $row['year'],
                month: (int) $row['month'],
                avgOnTimePercentage: round((float) $row['avg_on_time'], 1),
                avgDelaySec: (int) round((float) ($row['avg_delay'] ?? 0)),
                daysWithData: (int) $row['days_with_data'],
                totalPredictions: (int) $row['total_predictions'],
            );
        }

        return $results;
    }

    /**
     * Get analytics summary for a date range.
     *
     * @return array{total_predictions: int, avg_on_time: float, avg_delay: int, days_with_data: int, routes_tracked: int}
     */
    public function getAnalyticsSummary(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                COALESCE(SUM(p.total_predictions), 0) as total_predictions,
                COALESCE(AVG(p.on_time_percentage), 0) as avg_on_time,
                COALESCE(AVG(p.avg_delay_sec), 0) as avg_delay,
                COUNT(DISTINCT p.date) as days_with_data,
                COUNT(DISTINCT p.route_id) as routes_tracked
            FROM route_performance_daily p
            WHERE p.date >= :start
                AND p.date < :end
                AND p.on_time_percentage IS NOT NULL
        ';

        $row = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
        ])->fetchAssociative();

        return [
            'total_predictions' => (int) ($row['total_predictions'] ?? 0),
            'avg_on_time'       => round((float) ($row['avg_on_time'] ?? 0), 1),
            'avg_delay'         => (int) round((float) ($row['avg_delay'] ?? 0)),
            'days_with_data'    => (int) ($row['days_with_data'] ?? 0),
            'routes_tracked'    => (int) ($row['routes_tracked'] ?? 0),
        ];
    }
}
