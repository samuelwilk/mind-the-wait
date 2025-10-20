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
     * Find all stops served by a specific route, ordered by sequence.
     *
     * Returns stops in the order they appear along the route path (for direction 0).
     * Uses minimum stop_sequence across all trips to determine order.
     * This provides a proper sequential path for route visualization.
     *
     * @param string $routeGtfsId Route GTFS ID
     *
     * @return list<Stop>
     */
    public function findByRoute(string $routeGtfsId): array
    {
        // Get stops ordered by their sequence on the route (direction 0 only for clean path)
        $conn = $this->getEntityManager()->getConnection();
        $sql  = '
            SELECT DISTINCT s.id, s.gtfs_id, s.name, s.lat, s.long,
                   MIN(st.stop_sequence) as min_seq
            FROM stop s
            JOIN stop_time st ON st.stop_id = s.id
            JOIN trip t ON t.id = st.trip_id
            JOIN route r ON r.id = t.route_id
            WHERE r.gtfs_id = :routeId
              AND t.direction = 0
            GROUP BY s.id, s.gtfs_id, s.name, s.lat, s.long
            ORDER BY min_seq ASC
        ';

        $result = $conn->executeQuery($sql, ['routeId' => $routeGtfsId])->fetchAllAssociative();

        // Map results to Stop entities
        $stops = [];
        foreach ($result as $row) {
            $stop = $this->find($row['id']);
            if ($stop) {
                $stops[] = $stop;
            }
        }

        return $stops;
    }
}
