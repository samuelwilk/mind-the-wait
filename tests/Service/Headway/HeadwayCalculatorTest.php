<?php

declare(strict_types=1);

namespace App\Tests\Service\Headway;

use App\Dto\VehicleDto;
use App\Service\Headway\CrossingTimeEstimatorInterface;
use App\Service\Headway\HeadwayCalculator;
use App\Service\Headway\StopTimeProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeadwayCalculator::class)]
#[UsesClass(VehicleDto::class)]
final class HeadwayCalculatorTest extends TestCase
{
    public function testObservedHeadwayUsesPredictedArrivalsWhenSharedUpcomingStopExists(): void
    {
        $now = time() + 1_000;

        $vehicles = [
            new VehicleDto(routeId: '100', direction: null, timestamp: $now - 5, tripId: 'trip-1'),
            new VehicleDto(routeId: '100', direction: null, timestamp: $now - 15, tripId: 'trip-2'),
            new VehicleDto(routeId: '100', direction: null, timestamp: $now - 20, tripId: 'trip-3'),
        ];

        $stopMap = [
            'trip-1' => [
                ['stop_id' => 'past', 'seq' => 1, 'arr' => $now - 600, 'dep' => $now - 590],
                ['stop_id' => 'shared', 'seq' => 2, 'arr' => $now + 120, 'dep' => $now + 130],
                ['stop_id' => 'future', 'seq' => 3, 'arr' => $now + 420, 'dep' => $now + 430],
            ],
            'trip-2' => [
                ['stop_id' => 'shared', 'seq' => 5, 'arr' => $now + 300, 'dep' => $now + 310],
                ['stop_id' => 'future', 'seq' => 6, 'arr' => $now + 600, 'dep' => $now + 610],
            ],
            'trip-3' => [
                ['stop_id' => 'shared', 'seq' => 8, 'arr' => $now + 480, 'dep' => $now + 490],
            ],
        ];

        $calculator = new HeadwayCalculator(
            new StubCrossingTimeEstimator(),
            new StubStopTimeProvider($stopMap)
        );

        $headway = $calculator->observedHeadwaySec($vehicles);

        // Shared stop arrival times => [now+120, now+300, now+480]
        // Deltas => [180, 180]; median = 180.
        self::assertSame(180, $headway);
    }

    public function testFallsBackToInterpolatedCrossingTimesWhenPredictionsLackSharedStop(): void
    {
        $now = time() + 2_000;

        $vehicles = [
            new VehicleDto(routeId: '200', direction: null, timestamp: $now - 30, tripId: 'trip-a'),
            new VehicleDto(routeId: '200', direction: null, timestamp: $now - 20, tripId: 'trip-b'),
            new VehicleDto(routeId: '200', direction: null, timestamp: $now - 10, tripId: 'trip-c'),
        ];

        $stopMap = [
            'trip-a' => [
                ['stop_id' => 'one', 'seq' => 1, 'arr' => $now + 100, 'dep' => $now + 110],
            ],
            'trip-b' => [
                ['stop_id' => 'two', 'seq' => 1, 'arr' => $now + 200, 'dep' => $now + 210],
            ],
            'trip-c' => [
                ['stop_id' => 'three', 'seq' => 1, 'arr' => $now + 300, 'dep' => $now + 310],
            ],
        ];

        $crossingTimes = [
            'trip-a' => $now + 600,
            'trip-b' => $now + 780,
            'trip-c' => $now + 1_020,
        ];

        $calculator = new HeadwayCalculator(
            new StubCrossingTimeEstimator($crossingTimes),
            new StubStopTimeProvider($stopMap)
        );

        $headway = $calculator->observedHeadwaySec($vehicles);

        // Crossing times sorted => [now+600, now+780, now+1020]
        // Deltas => [180, 240]; median = 210 (average of 180 and 240).
        self::assertSame(210, $headway);
    }

    public function testFallsBackToRawTimestampsWhenNoPredictionsOrInterpolationsExist(): void
    {
        $now = time() + 3_000;

        $vehicles = [
            new VehicleDto(routeId: '300', direction: null, timestamp: $now - 900),
            new VehicleDto(routeId: '300', direction: null, timestamp: $now - 450),
            new VehicleDto(routeId: '300', direction: null, timestamp: $now - 180),
        ];

        $calculator = new HeadwayCalculator(
            new StubCrossingTimeEstimator(),
            new StubStopTimeProvider()
        );

        $headway = $calculator->observedHeadwaySec($vehicles);

        // Raw timestamps sorted => [now-900, now-450, now-180]
        // Deltas => [450, 270]; median = 360 (average of 450 and 270).
        self::assertSame(360, $headway);
    }
}

/**
 * @internal lightweight stub
 */
final class StubCrossingTimeEstimator implements CrossingTimeEstimatorInterface
{
    /** @var array<string,int|null> */
    private array $times;

    /**
     * @param array<string,int|null> $times per-trip predicted crossing timestamp
     */
    public function __construct(array $times = [])
    {
        $this->times = $times;
    }

    public function estimateTimeAtProgress(VehicleDto $vehicle, float $referenceProgress = 0.5): ?int
    {
        $tripId = $vehicle->tripId ?? '';

        return $this->times[$tripId] ?? null;
    }
}

/**
 * @internal lightweight stub
 */
final class StubStopTimeProvider implements StopTimeProviderInterface
{
    /** @param array<string,list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}>> $stopMap */
    public function __construct(
        private array $stopMap = [],
        private array $durations = [],
    ) {
    }

    public function getStopTimesForTrip(string $tripId): ?array
    {
        return $this->stopMap[$tripId] ?? null;
    }

    public function getTripDuration(string $tripId): ?array
    {
        return $this->durations[$tripId] ?? null;
    }
}
