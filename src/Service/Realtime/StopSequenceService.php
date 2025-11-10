<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\ArrivalPredictionDto;
use App\Dto\StopDTO;
use App\Entity\Route;
use App\Entity\Stop;
use App\Repository\StopTimeRepository;
use App\Service\Prediction\ArrivalPredictor;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function array_filter;
use function array_values;
use function usort;

/**
 * Resolves which vehicles are approaching each stop on a route.
 *
 * Builds a timeline of stops with their approaching vehicles.
 */
final readonly class StopSequenceService
{
    public function __construct(
        private StopTimeRepository $stopTimeRepo,
        private ArrivalPredictor $arrivalPredictor,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Build stop timeline with approaching vehicles.
     *
     * @param Route                      $route    Route entity
     * @param list<ArrivalPredictionDto> $arrivals All arrival predictions for this route
     *
     * @return list<StopDTO> Stops in sequence order with approaching vehicles
     */
    public function buildStopTimeline(Route $route, array $arrivals): array
    {
        // Get all unique stops for this route ordered by sequence
        // TODO: use a DTO here instead of array
        $stops = $this->getStopsForRoute($route);

        // Group arrivals by stop ID
        $arrivalsByStop = [];
        foreach ($arrivals as $arrival) {
            $stopId = $arrival->stopId;
            if (!isset($arrivalsByStop[$stopId])) {
                $arrivalsByStop[$stopId] = [];
            }
            $arrivalsByStop[$stopId][] = $arrival;
        }

        // Build StopDTOs
        $stopDtos = [];
        foreach ($stops as $stopData) {
            $stopId      = $stopData['stop_gtfs_id'];
            $approaching = $arrivalsByStop[$stopId] ?? [];

            // Keep vehicles that are:
            // 1. At stop or arriving imminently (within 30 seconds, including slightly past)
            // 2. Approaching (up to 5 minutes away)
            // Note: We only predict NEXT stops, so small negative ETAs mean vehicle is AT the stop
            $now         = time();
            $approaching = array_filter($approaching, function ($a) use ($now) {
                $actualEtaSec = $a->arrivalAt - $now; // Can be negative if at stop

                // Show if: at stop (up to 30 sec past) OR approaching (next 5 min)
                return $actualEtaSec > -30 && $actualEtaSec < 300;
            });

            // Sort approaching vehicles by ETA (soonest first)
            usort($approaching, fn ($a, $b) => $a->arrivalInSec() <=> $b->arrivalInSec());

            $stopDtos[] = new StopDTO(
                id: $stopId,
                name: $stopData['stop_name'],
                isTimepoint: $stopData['is_timepoint'],
                sequence: $stopData['sequence'],
                approachingVehicles: array_values($approaching) // Re-index after filter
            );
        }

        return $stopDtos;
    }

    /**
     * Get all stops for a route in sequence order.
     *
     * Cached for 24 hours since GTFS static data rarely changes.
     * Cache is invalidated when GTFS data is reloaded.
     *
     * @param Route $route Route entity
     *
     * @return list<array{stop_gtfs_id: string, stop_name: string, sequence: int, is_timepoint: bool}> Stop data
     */
    private function getStopsForRoute(Route $route): array
    {
        $cacheKey = 'stop_sequence_route_'.$route->getGtfsId();

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($route) {
            // Cache for 24 hours (GTFS static data rarely changes)
            $item->expiresAfter(86400);

            // Tag with route ID for selective invalidation on GTFS reload
            $item->tag(['route_stops', 'gtfs_static']);

            return $this->fetchStopsFromDatabase($route);
        });
    }

    /**
     * Fetch stops from database (called when cache misses).
     *
     * @param Route $route Route entity
     *
     * @return list<array{stop_gtfs_id: string, stop_name: string, sequence: int, is_timepoint: bool}> Stop data
     */
    private function fetchStopsFromDatabase(Route $route): array
    {
        // Pick ONE representative trip to get stop sequence
        // Strategy: Use the trip with the most stops (usually the complete route)
        // Direction doesn't matter - we just want the most complete stop list
        $conn = $this->stopTimeRepo->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            WITH representative_trip AS (
                -- Find the trip with the most stops (complete route)
                -- Use direction 0 only as tiebreaker when stop counts are equal
                SELECT t.id
                FROM trip t
                WHERE t.route_id = :route_id
                ORDER BY
                    (SELECT COUNT(*) FROM stop_time WHERE trip_id = t.id) DESC,  -- Most stops first
                    t.direction ASC,  -- Prefer direction 0 as tiebreaker
                    t.id
                LIMIT 1
            )
            SELECT
                s.gtfs_id as stop_gtfs_id,
                s.name as stop_name,
                st.stop_sequence as sequence,
                (st.arrival_time IS NOT NULL OR st.departure_time IS NOT NULL) as is_timepoint
            FROM stop_time st
            INNER JOIN representative_trip rt ON rt.id = st.trip_id
            INNER JOIN stop s ON s.id = st.stop_id
            ORDER BY st.stop_sequence
        SQL;

        $result = $conn->executeQuery($sql, ['route_id' => $route->getId()]);
        $rows   = $result->fetchAllAssociative();

        // Cast is_timepoint to bool (comes as string/int from DB)
        foreach ($rows as &$row) {
            $row['is_timepoint'] = (bool) $row['is_timepoint'];
            $row['sequence']     = (int) $row['sequence'];
        }

        return array_values($rows);
    }
}
