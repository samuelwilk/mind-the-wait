<?php

declare(strict_types=1);

namespace App\Tests\Service\Prediction;

use App\Dto\ArrivalPredictionDto;
use App\Enum\PredictionConfidence;
use App\Enum\VehiclePunctualityLabel;
use App\Repository\VehicleFeedbackRepositoryInterface;
use App\Service\Headway\StopTimeProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function count;
use function time;

#[CoversClass(ArrivalPredictionDto::class)]
final class ArrivalPredictorTest extends TestCase
{
    /**
     * NOTE: Full integration tests for ArrivalPredictor would require extensive mocking
     * of Doctrine repositories (RealtimeRepository, StopRepository, TripRepository, etc).
     * Instead, we focus on testing the DTO logic and documenting expected behavior.
     *
     * For integration testing, use manual API endpoint tests:
     * curl https://localhost/api/stops/{stopId}/predictions
     */
    public function testArrivalPredictionDtoCalculatesCountdown(): void
    {
        $now        = time();
        $arrivalAt  = $now + 180; // 3 minutes from now
        $prediction = new ArrivalPredictionDto(
            vehicleId: 'veh-1',
            routeId: '10',
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: 'Test Stop',
            headsign: 'Downtown',
            arrivalAt: $arrivalAt,
            confidence: PredictionConfidence::HIGH
        );

        self::assertSame(180, $prediction->arrivalInSec($now));
        self::assertSame(0, $prediction->arrivalInSec($arrivalAt + 10)); // Already passed
    }

    public function testArrivalPredictionDtoToArrayFormat(): void
    {
        $now        = time();
        $prediction = new ArrivalPredictionDto(
            vehicleId: 'veh-1',
            routeId: '10',
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: 'Test Stop',
            headsign: 'University',
            arrivalAt: $now + 240,
            confidence: PredictionConfidence::MEDIUM,
            currentLocation: ['lat' => 52.1, 'lon' => -106.5, 'stops_away' => 3],
            feedbackSummary: ['ahead' => 1, 'on_time' => 5, 'late' => 2, 'total' => 8]
        );

        $array = $prediction->toArray($now);

        self::assertSame('veh-1', $array['vehicle_id']);
        self::assertSame('10', $array['route_id']);
        self::assertSame('STOP1', $array['stop_id']);
        self::assertSame('University', $array['headsign']);
        self::assertSame(240, $array['arrival_in_sec']);
        self::assertSame($now + 240, $array['arrival_at']);
        self::assertSame('medium', $array['confidence']);
        self::assertSame(3, $array['current_location']['stops_away']);
        self::assertSame(8, $array['feedback_summary']['total']);
    }

    private function createPredictor(
        ?StubRealtimeRepository $realtimeRepo = null,
        ?StubStopTimeProvider $realtimeStopTimes = null,
        ?StubStaticStopTimeRepository $staticStopTimeRepo = null,
    ): ArrivalPredictor {
        return new ArrivalPredictor(
            realtimeRepo: $realtimeRepo                  ?? new StubRealtimeRepository(['vehicles' => []]),
            realtimeStopTimeProvider: $realtimeStopTimes ?? new StubStopTimeProvider([]),
            staticStopTimeRepo: $staticStopTimeRepo      ?? new StubStaticStopTimeRepository([]),
            stopRepo: new StubStopRepository([]),
            tripRepo: new StubTripRepository([]),
            positionInterpolator: new StubPositionInterpolator(),
            statusService: new StubVehicleStatusService(),
            feedbackRepo: new StubFeedbackRepository([])
        );
    }

    private function createStop(string $gtfsId, string $name, float $lat, float $lon): Stop
    {
        $stop = new Stop();
        $stop->setGtfsId($gtfsId);
        $stop->setName($name);
        $stop->setLat($lat);
        $stop->setLong($lon);

        return $stop;
    }
}

// Stub implementations
final class StubRealtimeRepository
{
    public function __construct(private array $data)
    {
    }

    public function snapshot(): array
    {
        return $this->data;
    }

    public function getVehicles(array $dirMap = []): array
    {
        return [];
    }

    public function getScores(): array
    {
        return [];
    }
}

final class StubStopTimeProvider implements StopTimeProviderInterface
{
    public function __construct(private array $data)
    {
    }

    public function getStopTimesForTrip(string $tripId): ?array
    {
        return $this->data[$tripId] ?? null;
    }

    public function getTripDuration(string $tripId): ?array
    {
        $stopTimes = $this->getStopTimesForTrip($tripId);
        if ($stopTimes === null || count($stopTimes) < 2) {
            return null;
        }

        $first = $stopTimes[0];
        $last  = $stopTimes[count($stopTimes) - 1];

        return [
            'start' => $first['arr'] ?? $first['dep'] ?? null,
            'end'   => $last['arr']  ?? $last['dep'] ?? null,
        ];
    }
}

final class StubStaticStopTimeRepository
{
    public function __construct(private array $data)
    {
    }

    public function getStopTimesForTrip(string $gtfsTripId): ?array
    {
        return $this->data[$gtfsTripId] ?? null;
    }
}

final class StubStopRepository
{
    public function __construct(private array $data)
    {
    }

    public function findOneByGtfsId(string $gtfsId): ?Stop
    {
        return $this->data[$gtfsId] ?? null;
    }
}

final class StubTripRepository
{
    public function __construct(private array $data = [])
    {
    }

    public function findOneByGtfsId(string $gtfsId): ?Trip
    {
        return $this->data[$gtfsId] ?? null;
    }
}

final class StubFeedbackRepository implements VehicleFeedbackRepositoryInterface
{
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
            'ahead'   => 0,
            'on_time' => 0,
            'late'    => 0,
            'total'   => 0,
        ];
    }
}

final class StubPositionInterpolator
{
    public function findNearestStop(VehicleDto $vehicle): ?Stop
    {
        return null;
    }

    public function estimateProgress(VehicleDto $vehicle, ?array $stopTimes = null): ?float
    {
        return null;
    }

    public function estimateTimeAtProgress(VehicleDto $vehicle, float $referenceProgress = 0.5): ?int
    {
        return null;
    }
}

final class StubVehicleStatusService
{
    public function enrichSnapshot(array $snapshot): array
    {
        return $snapshot;
    }
}
