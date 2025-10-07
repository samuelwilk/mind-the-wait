<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\VehiclePunctualityLabel;

interface VehicleFeedbackRepositoryInterface
{
    public function recordVote(string $vehicleId, VehiclePunctualityLabel $label): array;

    public function getSummary(string $vehicleId): array;
}
