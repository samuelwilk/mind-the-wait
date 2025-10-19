<?php

namespace App\Repository;

use App\Entity\Stop;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Stop>
 */
class StopRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, Stop::class);
    }

    public function findOneByGtfsId(string $gtfsId): ?Stop
    {
        return $this->findOneBy(['gtfsId' => $gtfsId]);
    }

    /**
     * @return array<string,int> map gtfsId => internal id
     *
     * @throws Exception
     */
    public function idMapByGtfsId(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.id AS id, s.gtfsId AS gtfs_id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['gtfs_id']] = (int) $r['id'];
        }

        return $map;
    }

    public function upsert(string $gtfsId, string $name, float $lat, float $long): Stop
    {
        $stop = $this->findOneByGtfsId($gtfsId) ?? new Stop();

        $stop->setGtfsId($gtfsId);
        $stop->setName($name);
        $stop->setLat($lat);
        $stop->setLong($long);
        $this->save($stop, false);

        return $stop;
    }

    /**
     * Find all stops served by a specific route.
     *
     * Queries stops through the stop_times → trips → route relationship.
     * Returns distinct stops (no duplicates if stop is visited multiple times).
     *
     * @param string $routeGtfsId Route GTFS ID
     *
     * @return list<Stop>
     */
    public function findByRoute(string $routeGtfsId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.stopTimes', 'st')
            ->join('st.trip', 't')
            ->join('t.route', 'r')
            ->where('r.gtfsId = :routeId')
            ->setParameter('routeId', $routeGtfsId)
            ->distinct()
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
