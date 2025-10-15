<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BunchingIncident;
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
}
