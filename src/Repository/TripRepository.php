<?php

namespace App\Repository;

use App\Entity\Route;
use App\Entity\Trip;
use App\Enum\DirectionEnum;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Trip>
 */
class TripRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, Trip::class);
    }

    public function findOneByGtfsId(string $gtfsId): ?Trip
    {
        return $this->findOneBy(['gtfsId' => $gtfsId]);
    }

    public function upsert(string $gtfsId, Route $route, ?string $serviceId, DirectionEnum $direction, ?string $headsign): Trip
    {
        $trip = $this->findOneByGtfsId($gtfsId) ?? new Trip();

        $trip->setGtfsId($gtfsId);
        $trip->setRoute($route);
        $trip->setServiceId($serviceId);
        $trip->setDirection($direction);
        $trip->setHeadsign($headsign);

        $this->save($trip, false);

        return $trip;
    }

    /**
     * @return array<string,int> map trip gtfsId => internal id
     *
     * @throws Exception
     */
    public function idMapByGtfsId(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.id AS id, t.gtfsId AS gtfs_id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['gtfs_id']] = (int) $r['id'];
        }

        return $map;
    }

    public function directionMapByGtfsId(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.gtfsId, t.direction');

        $results = $qb->getQuery()->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            $map[(string) $row['gtfsId']] = (int) $row['direction']->value;
        }

        return $map;
    }

    /**
     * Get headsigns for both directions of a route.
     *
     * Returns array like: [0 => 'University (Hub)', 1 => 'Depot']
     * or null if no trips found.
     *
     * @return array<int, string>|null
     */
    public function getRouteDirectionHeadsigns(string $routeGtfsId): ?array
    {
        $results = $this->createQueryBuilder('t')
            ->select('t.direction, t.headsign')
            ->innerJoin('t.route', 'r')
            ->where('r.gtfsId = :routeGtfsId')
            ->andWhere('t.headsign IS NOT NULL')
            ->setParameter('routeGtfsId', $routeGtfsId)
            ->groupBy('t.direction, t.headsign')
            ->orderBy('t.direction', 'ASC')
            ->setMaxResults(2)
            ->getQuery()
            ->getArrayResult();

        if (empty($results)) {
            return null;
        }

        $headsigns = [];
        foreach ($results as $row) {
            $direction             = $row['direction']->value;
            $headsigns[$direction] = $row['headsign'];
        }

        return $headsigns;
    }
}
