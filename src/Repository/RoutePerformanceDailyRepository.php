<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RoutePerformanceDaily;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

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
}
