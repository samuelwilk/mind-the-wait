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
     * @throws Exception
     */
    public function idMapByGtfsId(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchAllAssociative('SELECT id, gtfs_id FROM trips');
        $map = [];

        foreach ($rows as $r) {
            $map[$r['gtfs_id']] = (int)$r['id'];
        }

        return $map;
    }
}
