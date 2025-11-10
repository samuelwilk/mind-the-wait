<?php

namespace App\Repository;

use App\Entity\StopTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @extends BaseRepository<StopTime>
 */
class StopTimeRepository extends BaseRepository
{
    private const CACHE_TTL = 3600; // Cache for 1 hour (static schedule data)

    public function __construct(
        EntityManagerInterface $em,
        ManagerRegistry $registry,
        private readonly CacheItemPoolInterface $cache,
    ) {
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
     * Cached to prevent N+1 queries when called in loops (e.g., RouteTrackingService).
     * Static schedule data changes rarely (only on GTFS reload), so 1-hour cache is safe.
     *
     * @return list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}>|null
     */
    public function getStopTimesForTrip(string $gtfsTripId): ?array
    {
        $cacheKey = "stop_times_trip_{$gtfsTripId}";
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $stopTimes = $this->findByTripGtfsId($gtfsTripId);
        if (empty($stopTimes)) {
            // Cache null results with shorter TTL to avoid hammering DB for non-existent trips
            $item->set(null);
            $item->expiresAfter(60); // 1 minute for null results
            $this->cache->save($item);

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

        $item->set($result);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

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
