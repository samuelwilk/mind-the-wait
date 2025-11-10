<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Repository\RealtimeRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RealtimeSnapshotService
{
    private const CACHE_TTL = 5; // Cache for 5 seconds (realtime feed updates every ~30s)

    public function __construct(
        private RealtimeRepository $repository,
        private VehicleStatusService $vehicleStatusService,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @param string $citySlug City slug for Redis namespace (e.g., 'saskatoon', 'regina')
     */
    public function snapshot(string $citySlug = 'saskatoon'): array
    {
        // Cache the snapshot to avoid expensive JSON decoding on every request
        $cacheKey = "realtime_snapshot_{$citySlug}";

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($citySlug) {
            $item->expiresAfter(self::CACHE_TTL);

            $snapshot = $this->repository->snapshot($citySlug);

            return $this->vehicleStatusService->enrichSnapshot($snapshot);
        });
    }
}
