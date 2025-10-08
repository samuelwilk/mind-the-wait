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
     * Find all stop_times for a trip by GTFS trip_id, ordered by stop_sequence.
     *
     * @return list<StopTime>
     */
    public function findByTripGtfsId(string $gtfsTripId): array
    {
        return $this->createQueryBuilder('st')
            ->join('st.trip', 't')
            ->join('st.stop', 's')
            ->where('t.gtfsId = :tripId')
            ->setParameter('tripId', $gtfsTripId)
            ->orderBy('st.stopSequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get stop times for a trip in array format (for arrival prediction).
     *
     * @return list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}>|null
     */
    public function getStopTimesForTrip(string $gtfsTripId): ?array
    {
        $stopTimes = $this->findByTripGtfsId($gtfsTripId);
        if (empty($stopTimes)) {
            return null;
        }

        $result = [];
        foreach ($stopTimes as $st) {
            $result[] = [
                'stop_id' => $st->getStop()->getGtfsId(),
                'seq'     => $st->getStopSequence(),
                'arr'     => $st->getArrivalTime(),
                'dep'     => $st->getDepartureTime(),
            ];
        }

        return $result;
    }

    /**
     * Bulk insert stop_time (trip_id, stop_id, stop_sequence, arrival_time, departure_time).
     *
     * @param list<array{trip:int,stop:int,seq:int,arr:?int,dep:?int}> $rows
     *
     * @throws Exception
     */
    public function bulkInsert(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $em   = $this->getEntityManager();
        $conn = $em->getConnection();
        $plat = $conn->getDatabasePlatform();

        $table   = $plat->quoteIdentifier($this->getTableName());
        $colTrip = $plat->quoteIdentifier('trip_id');
        $colStop = $plat->quoteIdentifier('stop_id');
        $colSeq  = $plat->quoteIdentifier('stop_sequence');
        $colArr  = $plat->quoteIdentifier('arrival_time');
        $colDep  = $plat->quoteIdentifier('departure_time');

        $sql = <<<SQL
            INSERT INTO {$table} ({$colTrip}, {$colStop}, {$colSeq}, {$colArr}, {$colDep})
            VALUES (:trip, :stop, :seq, :arr, :dep)
            ON CONFLICT ({$colTrip}, {$colSeq}) DO UPDATE
            SET {$colStop} = EXCLUDED.{$colStop},
                {$colArr}  = EXCLUDED.{$colArr},
                {$colDep}  = EXCLUDED.{$colDep}
        SQL;

        $stmt = $conn->prepare($sql);

        foreach ($rows as $r) {
            // Cast to safe scalar types; DBAL will handle nulls.
            $params = [
                'trip' => (int) $r['trip'],
                'stop' => (int) $r['stop'],
                'seq'  => (int) $r['seq'],
                'arr'  => isset($r['arr']) ? (int) $r['arr'] : null,
                'dep'  => isset($r['dep']) ? (int) $r['dep'] : null,
            ];
            $stmt->executeStatement($params);
        }
    }
}
