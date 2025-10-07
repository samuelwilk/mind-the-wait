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

    public function snapshot(): array
    {
        $snapshot = $this->repository->snapshot();

        return $this->vehicleStatusService->enrichSnapshot($snapshot);
    }
}
