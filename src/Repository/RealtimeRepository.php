<?php

namespace App\Repository;

use Predis\ClientInterface;

use function is_array;

use const JSON_THROW_ON_ERROR;

final readonly class RealtimeRepository
{
    public function __construct(private ClientInterface $redis)
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
