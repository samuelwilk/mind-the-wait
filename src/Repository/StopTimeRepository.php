<?php

namespace App\Repository;

use App\Entity\StopTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<StopTime>
 */
class StopTimeRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, StopTime::class);
    }

    public function connection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * Bulk insert stop_times (trip_id, stop_id, stop_sequence, arrival_time, departure_time).
     * @param list<array{trip:int,stop:int,seq:int,arr:?int,dep:?int}> $rows
     * @throws Exception
     */
    public function bulkInsert(array $rows): void
    {
        if (!$rows) return;

        $sql = 'INSERT INTO stop_times (trip_id, stop_id, stop_sequence, arrival_time, departure_time)
                VALUES (:trip, :stop, :seq, :arr, :dep)
                ON CONFLICT (trip_id, stop_sequence) DO UPDATE
                SET stop_id = EXCLUDED.stop_id,
                    arrival_time = EXCLUDED.arrival_time,
                    departure_time = EXCLUDED.departure_time';

        $stmt = $this->connection()->prepare($sql);

        foreach ($rows as $r) {
            $stmt->executeStatement($r);
        }
    }
}
