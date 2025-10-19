<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\BunchingRateDto;
use App\Entity\BunchingIncident;
use App\Enum\WeatherCondition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BunchingIncident>
 */
final class BunchingIncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BunchingIncident::class);
    }

    public function save(BunchingIncident $incident, bool $flush = false): void
    {
        $this->getEntityManager()->persist($incident);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find bunching incidents within a date range.
     *
     * @return list<BunchingIncident>
     */
    public function findByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.detectedAt >= :start')
            ->andWhere('b.detectedAt < :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('b.detectedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get bunching incidents grouped by weather condition.
     *
     * @return list<\App\Dto\BunchingCountDto>
     */
    public function countByWeatherCondition(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('w.weatherCondition', 'COUNT(b.id) as incidentCount')
            ->leftJoin('b.weatherObservation', 'w')
            ->where('b.detectedAt >= :start')
            ->andWhere('b.detectedAt < :end')
            ->andWhere('w.weatherCondition IS NOT NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('w.weatherCondition')
            ->orderBy('incidentCount', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn (array $row) => new \App\Dto\BunchingCountDto(
                weatherCondition: $row['weatherCondition'],
                incidentCount: (int) $row['incidentCount'],
            ),
            $results
        );
    }

    /**
     * Count bunching incidents by weather condition with exposure hours.
     *
     * Returns incidents per hour for better comparison across weather conditions.
     *
     * @return list<BunchingRateDto>
     */
    public function countByWeatherConditionNormalized(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            WITH weather_durations AS (
                -- Calculate hours spent in each weather condition
                SELECT
                    weather_condition,
                    COUNT(DISTINCT DATE_TRUNC('hour', observed_at)) as exposure_hours
                FROM weather_observation
                WHERE observed_at >= :start_date
                  AND observed_at < :end_date
                  AND weather_condition IS NOT NULL
                GROUP BY weather_condition
            ),
            incident_counts AS (
                -- Count bunching incidents by weather condition
                SELECT
                    w.weather_condition,
                    COUNT(bi.id) as incident_count
                FROM bunching_incident bi
                LEFT JOIN weather_observation w ON bi.weather_observation_id = w.id
                WHERE bi.detected_at >= :start_date
                  AND bi.detected_at < :end_date
                  AND w.weather_condition IS NOT NULL
                GROUP BY w.weather_condition
            )
            SELECT
                COALESCE(wd.weather_condition, ic.weather_condition) as weather_condition,
                COALESCE(ic.incident_count, 0) as incident_count,
                COALESCE(wd.exposure_hours, 0) as exposure_hours,
                CASE
                    WHEN COALESCE(wd.exposure_hours, 0) > 0
                    THEN COALESCE(ic.incident_count, 0)::float / wd.exposure_hours
                    ELSE 0
                END as incidents_per_hour
            FROM weather_durations wd
            FULL OUTER JOIN incident_counts ic ON wd.weather_condition = ic.weather_condition
            WHERE COALESCE(wd.exposure_hours, 0) > 0  -- Exclude conditions with no exposure
            ORDER BY incidents_per_hour DESC
        SQL;

        $results = $conn->executeQuery($sql, [
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date'   => $endDate->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        return array_map(
            fn (array $row) => new BunchingRateDto(
                weatherCondition: WeatherCondition::fromString($row['weather_condition']),
                incidentCount: (int) $row['incident_count'],
                exposureHours: (float) $row['exposure_hours'],
                incidentsPerHour: round((float) $row['incidents_per_hour'], 2),
            ),
            $results
        );
    }
}
