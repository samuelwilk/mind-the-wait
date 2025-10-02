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
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchAllAssociative('SELECT id, gtfs_id FROM stops');
        $map  = [];

        foreach ($rows as $r) {
            $map[$r['gtfs_id']] = (int) $r['id'];
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
}
