<?php

namespace App\Repository;

use App\Dto\RealtimeSnapshotDto;
use App\Dto\RouteScoreDto;
use App\Dto\RouteScoresDto;
use App\Dto\VehicleDto;
use App\Dto\VehicleFeedbackDto;
use App\Dto\VehicleSnapshotDto;
use Predis\ClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function is_array;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

final readonly class RealtimeRepository
{
    private const CACHE_TTL = 5; // Cache for 5 seconds to avoid N+1 JSON decoding

    public function __construct(
        private ClientInterface $redis,
        private TripRepository $tripRepository,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Return a stable payload for the API.
     *
     * Cached for 5 seconds to prevent N+1 issues when called in loops
     * (e.g., ArrivalPredictor calls this for every vehicle/stop combination).
     *
     * @param string $citySlug City slug for Redis namespace (e.g., 'saskatoon', 'regina')
     *
     * @return array{ts:int, vehicles:array, trips:array, alerts:array}
     */
    public function snapshot(string $citySlug = 'saskatoon'): array
    {
        $cacheKey = "realtime_repo_snapshot_{$citySlug}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($citySlug) {
            $item->expiresAfter(self::CACHE_TTL);

            $veh = $this->redis->hgetall($this->getKey('vehicles', $citySlug)) ?: [];
            $tri = $this->redis->hgetall($this->getKey('trips', $citySlug)) ?: [];
            $alt = $this->redis->hgetall($this->getKey('alerts', $citySlug)) ?: [];

            $vehicles = $this->safeJsonDecode($veh['json'] ?? '[]');
            $trips    = $this->safeJsonDecode($tri['json'] ?? '[]');
            $alerts   = $this->safeJsonDecode($alt['json'] ?? '[]');

            $ts = max((int) ($veh['ts'] ?? 0), (int) ($tri['ts'] ?? 0), (int) ($alt['ts'] ?? 0));

            return [
                'ts'       => $ts,
                'vehicles' => is_array($vehicles) ? $vehicles : [],
                'trips'    => is_array($trips) ? $trips : [],
                'alerts'   => is_array($alerts) ? $alerts : [],
            ];
        });
    }

    /**
     * @param string $citySlug City slug for Redis namespace
     *
     * @return list<VehicleDto>
     */
    public function getVehicles(string $citySlug = 'saskatoon'): array
    {
        $h   = $this->redis->hgetall($this->getKey('vehicles', $citySlug));
        $arr = isset($h['json']) ? json_decode($h['json'], true) : [];
        if (!is_array($arr)) {
            return [];
        }

        // Build trip_id -> direction map once
        $dirMap = $this->tripRepository->directionMapByGtfsId();

        $out = [];
        foreach ($arr as $row) {
            if (!is_array($row)) {
                continue;
            }

            $dto = VehicleDto::fromArrayWithDirMap($row, $dirMap);
            if ($dto === null) {
                continue;
            }

            $out[] = $dto;
        }

        return $out;
    }

    /**
     * @param string $citySlug City slug for Redis namespace
     */
    public function getVehiclesTimestamps(string $citySlug = 'saskatoon'): int
    {
        $h = $this->redis->hgetall($this->getKey('vehicles', $citySlug));

        return (int) ($h['ts'] ?? 0);
    }

    /**
     * @param int                       $ts       Timestamp
     * @param list<array<string,mixed>> $rows     Score data
     * @param string                    $citySlug City slug for Redis namespace
     */
    public function saveScores(int $ts, array $rows, string $citySlug = 'saskatoon'): void
    {
        $key = $this->getKey('score', $citySlug);
        $this->redis->hset($key, 'ts', (string) $ts);
        $this->redis->hset($key, 'json', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param string $citySlug City slug for Redis namespace
     *
     * @return array{ts:int,items:list<array<string,mixed>>}
     *
     * @deprecated Use getScores() instead for typed DTO access
     */
    public function readScores(string $citySlug = 'saskatoon'): array
    {
        $h     = $this->redis->hgetall($this->getKey('score', $citySlug));
        $items = isset($h['json']) ? json_decode($h['json'], true) : [];
        if (!is_array($items)) {
            $items = [];
        }

        return ['ts' => (int) ($h['ts'] ?? 0), 'items' => $items];
    }

    /**
     * Get route scores as typed DTO.
     *
     * @param string $citySlug City slug for Redis namespace
     */
    public function getScores(string $citySlug = 'saskatoon'): RouteScoresDto
    {
        $h     = $this->redis->hgetall($this->getKey('score', $citySlug));
        $items = isset($h['json']) ? json_decode($h['json'], true) : [];
        if (!is_array($items)) {
            $items = [];
        }

        $dtos = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dtos[] = new RouteScoreDto(
                routeId: (string) ($item['route_id'] ?? ''),
                direction: (int) ($item['direction'] ?? 0),
                observedHeadwaySec: isset($item['observed_headway_sec']) ? (int) $item['observed_headway_sec'] : null,
                scheduledHeadwaySec: isset($item['scheduled_headway_sec']) ? (int) $item['scheduled_headway_sec'] : null,
                vehicles: (int) ($item['vehicles'] ?? 0),
                grade: (string) ($item['grade'] ?? 'N/A'),
                confidence: (string) ($item['confidence'] ?? 'low'),
                asOf: (int) ($item['as_of'] ?? 0),
            );
        }

        return new RouteScoresDto(
            ts: (int) ($h['ts'] ?? 0),
            items: $dtos,
        );
    }

    /**
     * Get realtime snapshot as typed DTO.
     *
     * @param string $citySlug City slug for Redis namespace
     */
    public function getSnapshot(string $citySlug = 'saskatoon'): RealtimeSnapshotDto
    {
        $veh = $this->redis->hgetall($this->getKey('vehicles', $citySlug)) ?: [];

        $vehicles = $this->safeJsonDecode($veh['json'] ?? '[]');
        $ts       = (int) ($veh['ts'] ?? 0);

        if (!is_array($vehicles)) {
            $vehicles = [];
        }

        $vehicleDtos = [];
        foreach ($vehicles as $vehicle) {
            if (!is_array($vehicle)) {
                continue;
            }

            $feedback = $vehicle['feedback'] ?? [];
            if (!is_array($feedback)) {
                $feedback = [];
            }

            $vehicleDtos[] = new VehicleSnapshotDto(
                id: (string) ($vehicle['id'] ?? ''),
                trip: (string) ($vehicle['trip'] ?? ''),
                route: (string) ($vehicle['route'] ?? ''),
                lat: (float) ($vehicle['lat'] ?? 0.0),
                lon: (float) ($vehicle['lon'] ?? 0.0),
                bearing: (int) ($vehicle['bearing'] ?? 0),
                speed: isset($vehicle['speed']) ? (float) $vehicle['speed'] : null,
                ts: (int) ($vehicle['ts'] ?? 0),
                feedback: new VehicleFeedbackDto(
                    ahead: (int) ($feedback['ahead'] ?? 0),
                    onTime: (int) ($feedback['on_time'] ?? 0),
                    late: (int) ($feedback['late'] ?? 0),
                    total: (int) ($feedback['total'] ?? 0),
                ),
            );
        }

        return new RealtimeSnapshotDto(
            ts: $ts,
            vehicles: $vehicleDtos,
        );
    }

    /** Quick health info for /healthz or logs */
    public function health(): array
    {
        try {
            $pong = $this->redis->ping();
        } catch (\Throwable $e) {
            return ['redis' => 'down', 'error' => $e->getMessage()];
        }

        // Check default city (Saskatoon) for backwards compatibility
        $vehTs = (int) ($this->redis->hget($this->getKey('vehicles', 'saskatoon'), 'ts') ?: 0);

        return ['redis' => (string) $pong, 'rtTs' => $vehTs];
    }

    /**
     * Build Redis key with city namespace.
     *
     * @param string $type     Key type (vehicles, trips, alerts, score)
     * @param string $citySlug City slug
     *
     * @return string Namespaced Redis key (e.g., 'mtw:saskatoon:vehicles')
     */
    private function getKey(string $type, string $citySlug): string
    {
        return sprintf('mtw:%s:%s', $citySlug, $type);
    }

    private function safeJsonDecode(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
    }
}
