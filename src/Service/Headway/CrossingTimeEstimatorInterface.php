<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\VehicleDto;

/**
 * Abstraction for estimating when a vehicle reaches a reference position along a route.
 *
 * @internal exposed for testability; production implementation is PositionInterpolator
 */
interface CrossingTimeEstimatorInterface
{
    /**
     * @return int|null estimated epoch when the vehicle reaches the specified progress point
     */
    public function estimateTimeAtProgress(VehicleDto $vehicle, float $referenceProgress = 0.5): ?int;
}
