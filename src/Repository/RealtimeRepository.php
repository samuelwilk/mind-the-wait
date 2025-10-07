<?php

namespace App\Repository;

use App\Dto\VehicleDto;
use Predis\ClientInterface;

use function is_array;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

final readonly class RealtimeRepository
{
    public function __construct(private ClientInterface $redis, private readonly TripRepository $tripRepository)
    {
    }

    /**
     * Return a stable payload for the API.
     *
     * @return array{ts:int, vehicles:array, trips:array, alerts:array}
     */
    public function snapshot(): array
    {
        $veh = $this->redis->hgetall('mtw:vehicles') ?: [];
        $tri = $this->redis->hgetall('mtw:trips') ?: [];
        $alt = $this->redis->hgetall('mtw:alerts') ?: [];

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
    }

    /** @return list<VehicleDto> */
    public function getVehicles(): array
    {
        $h   = $this->redis->hgetall('mtw:vehicles');
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

    public function getVehiclesTimestamps(): int
    {
        $h = $this->redis->hgetall('mtw:vehicles');

        return (int) ($h['ts'] ?? 0);
    }

    /** @param list<array<string,mixed>> $rows */
    public function saveScores(int $ts, array $rows): void
    {
        $this->redis->hset('mtw:score', 'ts', (string) $ts);
        $this->redis->hset('mtw:score', 'json', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /** @return array{ts:int,items:list<array<string,mixed>>} */
    public function readScores(): array
    {
        $h     = $this->redis->hgetall('mtw:score');
        $items = isset($h['json']) ? json_decode($h['json'], true) : [];
        if (!is_array($items)) {
            $items = [];
        }

        return ['ts' => (int) ($h['ts'] ?? 0), 'items' => $items];
    }

    /** Quick health info for /healthz or logs */
    public function health(): array
    {
        try {
            $pong = $this->redis->ping();
        } catch (\Throwable $e) {
            return ['redis' => 'down', 'error' => $e->getMessage()];
        }

        $vehTs = (int) ($this->redis->hget('mtw:vehicles', 'ts') ?: 0);

        return ['redis' => (string) $pong, 'rtTs' => $vehTs];
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
