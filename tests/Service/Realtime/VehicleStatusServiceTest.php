<?php

declare(strict_types=1);

namespace App\Tests\Service\Realtime;

use App\Enum\VehiclePunctualityLabel;
use App\Enum\VehicleStatusColor;
use App\Repository\VehicleFeedbackRepositoryInterface;
use App\Service\Headway\StopTimeProviderInterface;
use App\Service\Realtime\TrafficReasonProviderInterface;
use App\Service\Realtime\VehicleStatusService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VehicleStatusService::class)]
final class VehicleStatusServiceTest extends TestCase
{
    public function testEnrichSnapshotAddsStatusAndFeedback(): void
    {
        $now = time();

        $stopProvider = new StubStopTimeProvider([
            'trip-1' => [
                ['stop_id' => 'STOP1', 'seq' => 10, 'arr' => $now + 240, 'dep' => null, 'delay' => 420],
            ],
        ]);
        $trafficProvider  = new StubTrafficReasonProvider('Severe congestion reported.');
        $feedbackProvider = new StubFeedbackRepository([
            'veh-1' => [
                VehiclePunctualityLabel::AHEAD->value   => 1,
                VehiclePunctualityLabel::ON_TIME->value => 2,
                VehiclePunctualityLabel::LATE->value    => 3,
                'total'                                 => 6,
            ],
        ]);

        $service = new VehicleStatusService($stopProvider, $trafficProvider, $feedbackProvider);

        $snapshot = [
            'ts'       => $now,
            'vehicles' => [[
                'id'    => 'veh-1',
                'route' => '10',
                'trip'  => 'trip-1',
                'ts'    => $now - 30,
            ]],
        ];

        $result = $service->enrichSnapshot($snapshot);

        $vehicle = $result['vehicles'][0];
        self::assertArrayHasKey('status', $vehicle);
        self::assertSame(VehicleStatusColor::RED->value, $vehicle['status']['color']);
        self::assertSame(VehiclePunctualityLabel::LATE->value, $vehicle['status']['label']);
        self::assertSame('critical', $vehicle['status']['severity']);
        self::assertSame(420, $vehicle['status']['deviation_sec']);
        self::assertSame('Severe congestion reported.', $vehicle['status']['reason']);
        self::assertSame(6, $vehicle['status']['feedback']['total']);
    }

    public function testAheadOfScheduleProducesYellowStatus(): void
    {
        $now = time();

        $service = new VehicleStatusService(
            new StubStopTimeProvider([
                'trip-2' => [
                    ['stop_id' => 'STOP2', 'seq' => 5, 'arr' => $now + 120, 'dep' => null, 'delay' => -150],
                ],
            ]),
            new StubTrafficReasonProvider(null),
            new StubFeedbackRepository([])
        );

        $snapshot = [
            'ts'       => $now,
            'vehicles' => [[
                'id'    => 'veh-2',
                'route' => '20',
                'trip'  => 'trip-2',
                'ts'    => $now - 60,
            ]],
        ];

        $result = $service->enrichSnapshot($snapshot);

        $status = $result['vehicles'][0]['status'];
        self::assertSame(VehiclePunctualityLabel::AHEAD->value, $status['label']);
        self::assertSame(VehicleStatusColor::YELLOW->value, $status['color']);
        self::assertSame(-150, $status['deviation_sec']);
        self::assertNull($status['reason']);
    }
}

final class StubStopTimeProvider implements StopTimeProviderInterface
{
    /** @param array<string, list<array{stop_id: string, seq: int, arr: ?int, dep: ?int, delay: ?int}>> $map */
    public function __construct(private array $map)
    {
    }

    public function getStopTimesForTrip(string $tripId): ?array
    {
        return $this->map[$tripId] ?? null;
    }

    public function getTripDuration(string $tripId): ?array
    {
        return null;
    }
}

final class StubTrafficReasonProvider implements TrafficReasonProviderInterface
{
    public function __construct(private ?string $reason)
    {
    }

    public function reasonFor(\App\Dto\VehicleDto $vehicle, int $delaySeconds): ?string
    {
        return $this->reason;
    }
}

final class StubFeedbackRepository implements VehicleFeedbackRepositoryInterface
{
    /** @param array<string,array<string,int>> $data */
    public function __construct(private array $data)
    {
    }

    public function recordVote(string $vehicleId, VehiclePunctualityLabel $label): array
    {
        return $this->getSummary($vehicleId);
    }

    public function getSummary(string $vehicleId): array
    {
        return $this->data[$vehicleId] ?? [
            VehiclePunctualityLabel::AHEAD->value   => 0,
            VehiclePunctualityLabel::ON_TIME->value => 0,
            VehiclePunctualityLabel::LATE->value    => 0,
            'total'                                 => 0,
        ];
    }
}
