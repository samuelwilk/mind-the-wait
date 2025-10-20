<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Repository\RealtimeRepository;

final readonly class RealtimeSnapshotService
{
    public function __construct(
        private RealtimeRepository $repository,
        private VehicleStatusService $vehicleStatusService,
    ) {
    }

    /**
     * @param string $citySlug City slug for Redis namespace (e.g., 'saskatoon', 'regina')
     */
    public function snapshot(string $citySlug = 'saskatoon'): array
    {
        $snapshot = $this->repository->snapshot($citySlug);

        return $this->vehicleStatusService->enrichSnapshot($snapshot);
    }
}
