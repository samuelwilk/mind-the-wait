<?php

declare(strict_types=1);

namespace App\Tests\Service\Realtime;

use App\Dto\VehicleDto;
use App\Enum\DirectionEnum;
use App\Service\Realtime\HeuristicTrafficReasonProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeuristicTrafficReasonProvider::class)]
final class HeuristicTrafficReasonProviderTest extends TestCase
{
    public function testReturnsNullForMinorDelays(): void
    {
        $provider = new HeuristicTrafficReasonProvider();
        $vehicle  = new VehicleDto(
            routeId: '10',
            direction: DirectionEnum::Zero,
            timestamp: time()
        );

        // Under 2 minutes should return null
        self::assertNull($provider->reasonFor($vehicle, 90));
        self::assertNull($provider->reasonFor($vehicle, -90));
    }

    public function testReturnsSevereTrafficForLongDelays(): void
    {
        $provider = new HeuristicTrafficReasonProvider();
        $vehicle  = new VehicleDto(
            routeId: '15',
            direction: DirectionEnum::Zero,
            timestamp: time()
        );

        $reason = $provider->reasonFor($vehicle, 720); // 12 min delay

        // Could be either dad joke or standard message
        self::assertNotNull($reason);
        // Standard message format: "Severe traffic likely impacting 15 (delay 12 min)."
        // Dad joke format: various funny messages
        $reasonLower = strtolower($reason);
        $isDadJoke   = str_contains($reasonLower, 'pigeon')
            || str_contains($reasonLower, 'quantum')
            || str_contains($reasonLower, 'speedrun')
            || str_contains($reasonLower, 'wormhole')
            || str_contains($reasonLower, 'gremlins');

        if (!$isDadJoke) {
            self::assertStringContainsString('Severe traffic', $reason);
            self::assertStringContainsString('15', $reason);
        }
    }

    public function testReturnsModerateTrafficForMediumDelays(): void
    {
        $provider = new HeuristicTrafficReasonProvider();
        $vehicle  = new VehicleDto(
            routeId: '20',
            direction: DirectionEnum::One,
            timestamp: time()
        );

        $reason = $provider->reasonFor($vehicle, 240); // 4 min delay

        self::assertNotNull($reason);
        $reasonLower = strtolower($reason);
        $isDadJoke   = str_contains($reasonLower, 'pigeon')
            || str_contains($reasonLower, 'quantum')
            || str_contains($reasonLower, 'speedrun')
            || str_contains($reasonLower, 'wormhole')
            || str_contains($reasonLower, 'gremlins');

        if (!$isDadJoke) {
            self::assertStringContainsString('Moderate congestion', $reason);
            self::assertStringContainsString('20', $reason);
        }
    }

    public function testReturnsLightTrafficForEarlyVehicles(): void
    {
        $provider = new HeuristicTrafficReasonProvider();
        $vehicle  = new VehicleDto(
            routeId: '25',
            direction: DirectionEnum::Zero,
            timestamp: time()
        );

        $reason = $provider->reasonFor($vehicle, -400); // 6+ min early

        self::assertNotNull($reason);
        $reasonLower = strtolower($reason);
        $isDadJoke   = str_contains($reasonLower, 'pigeon')
            || str_contains($reasonLower, 'quantum')
            || str_contains($reasonLower, 'speedrun')
            || str_contains($reasonLower, 'wormhole')
            || str_contains($reasonLower, 'gremlins');

        if (!$isDadJoke) {
            self::assertStringContainsString('Light traffic', $reason);
            self::assertStringContainsString('25', $reason);
        }
    }

    public function testDadJokesCanBeReturned(): void
    {
        $provider = new HeuristicTrafficReasonProvider();
        $vehicle  = new VehicleDto(
            routeId: '30',
            direction: DirectionEnum::One,
            timestamp: time()
        );

        // Run 100 times to increase chance of getting a dad joke (10% probability)
        $gotJoke = false;
        for ($i = 0; $i < 100; ++$i) {
            $reason = $provider->reasonFor($vehicle, 300);
            if ($reason !== null && (
                str_contains($reason, 'pigeon')
                || str_contains($reason, 'quantum')
                || str_contains($reason, 'wormhole')
                || str_contains($reason, 'gremlins')
                || str_contains($reason, 'speedrun')
            )) {
                $gotJoke = true;
                break;
            }
        }

        self::assertTrue($gotJoke, 'Expected to receive at least one dad joke in 100 attempts');
    }
}
