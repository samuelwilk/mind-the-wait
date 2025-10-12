<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArrivalLog;
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
}
