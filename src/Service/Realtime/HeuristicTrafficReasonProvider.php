<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\VehicleDto;

use function sprintf;

final readonly class HeuristicTrafficReasonProvider implements TrafficReasonProviderInterface
{
    public function reasonFor(VehicleDto $vehicle, int $delaySeconds): ?string
    {
        $absDelay = abs($delaySeconds);

        if ($absDelay < 120) {
            return null;
        }

        if ($delaySeconds > 0) {
            if ($absDelay >= 600) {
                return sprintf('Severe traffic likely impacting %s (delay %d min).', $vehicle->routeId, (int) round($absDelay / 60));
            }

            return sprintf('Moderate congestion detected along route %s.', $vehicle->routeId);
        }

        // Running ahead of schedule
        if ($absDelay >= 300) {
            return sprintf('Light traffic allowing vehicles on route %s to run ahead.', $vehicle->routeId);
        }

        return sprintf('Lower-than-normal demand on route %s.', $vehicle->routeId);
    }
}
