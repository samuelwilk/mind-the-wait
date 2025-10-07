<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\VehicleDto;

interface TrafficReasonProviderInterface
{
    public function reasonFor(VehicleDto $vehicle, int $delaySeconds): ?string;
}
