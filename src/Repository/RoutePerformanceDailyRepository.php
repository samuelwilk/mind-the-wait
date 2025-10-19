<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\RoutePerformanceSummaryDto;
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
}
