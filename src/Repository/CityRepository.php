<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * Find all active cities for iOS app city picker.
     *
     * @return list<City>
     */
    public function findActiveCities(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find city by slug (e.g., 'saskatoon').
     */
    public function findBySlug(string $slug): ?City
    {
        return $this->findOneBy(['slug' => $slug, 'active' => true]);
    }
}
