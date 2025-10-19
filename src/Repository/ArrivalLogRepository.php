<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\RoutePerformanceHeatmapBucketDto;
use App\Entity\ArrivalLog;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

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
}
