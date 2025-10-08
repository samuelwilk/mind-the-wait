<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\VehicleDto;

use function array_rand;
use function random_int;
use function sprintf;

final readonly class HeuristicTrafficReasonProvider implements TrafficReasonProviderInterface
{
    private const DAD_JOKES = [
        'Driver heard there was a sale at the mall.',
        'Bus stopped to argue with a pigeon.',
        'Driver practicing speedruns. Current PB: 3 minutes early.',
        'Time traveler drove this route.',
        'Driver forgot to reset clock after daylight savings.',
        'Vehicle achieved quantum entanglement with schedule.',
        'Gremlins in the GPS again.',
        'Driver took the scenic route (for science).',
        'Bus entered a wormhole near 3rd and Main.',
        'Schedule machine needs more coffee.',
    ];

    public function reasonFor(VehicleDto $vehicle, int $delaySeconds): ?string
    {
        $absDelay = abs($delaySeconds);

        if ($absDelay < 120) {
            return null;
        }

        // 10% chance for dad joke
        if (random_int(1, 10) === 1) {
            return self::DAD_JOKES[array_rand(self::DAD_JOKES)];
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
