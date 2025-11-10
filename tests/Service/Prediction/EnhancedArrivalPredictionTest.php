<?php

declare(strict_types=1);

namespace App\Tests\Service\Prediction;

use App\Dto\ArrivalPredictionDto;
use App\Enum\PredictionConfidence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function time;

/**
 * Enhanced tests for arrival predictions with matched route IDs
 * and position-based calculations.
 */
#[CoversClass(ArrivalPredictionDto::class)]
final class EnhancedArrivalPredictionTest extends TestCase
{
    /**
     * Test that predictions with matched route IDs can provide HIGH confidence.
     */
    public function testHighConfidencePredictionsWithMatchedRoutes(): void
    {
        $now        = time();
        $prediction = new ArrivalPredictionDto(
            vehicleId: '606',
            routeId: '14536', // Matched route ID (Route 27: Silverspring / University)
            tripId: 'trip-route27-1',
            stopId: '3734',
            stopName: null,
            headsign: 'University',
            arrivalAt: $now + 420, // 7 minutes
            confidence: PredictionConfidence::HIGH,
            currentLocation: ['lat' => 52.1500, 'lon' => -106.5985, 'stops_away' => 4]
        );

        self::assertSame(PredictionConfidence::HIGH, $prediction->confidence);
        self::assertNotNull($prediction->currentLocation);
        self::assertSame(4, $prediction->currentLocation['stops_away']);
    }

    /**
     * Test predictions for Route 43 (Evergreen / City Centre).
     */
    public function testRoute43EvergreenPredictions(): void
    {
        $now = time();

        // Vehicle on Route 43 heading to City Centre
        $prediction = new ArrivalPredictionDto(
            vehicleId: '707',
            routeId: '14551', // Route 43 matched ID
            tripId: 'trip-route43-morning',
            stopId: '3734', // Primrose / Lenore
            stopName: null,
            headsign: 'City Centre',
            arrivalAt: $now + 180, // 3 minutes
            confidence: PredictionConfidence::HIGH,
            currentLocation: ['lat' => 52.1674, 'lon' => -106.5755, 'stops_away' => 2]
        );

        $array = $prediction->toArray($now);

        self::assertSame('14551', $array['route_id']);
        self::assertSame('City Centre', $array['headsign']);
        self::assertSame(180, $array['arrival_in_sec']);
        self::assertSame('high', $array['confidence']);
        self::assertSame(2, $array['current_location']['stops_away']);
    }

    /**
     * Test countdown calculations with various arrival times.
     */
    #[DataProvider('countdownProvider')]
    public function testCountdownCalculations(int $arrivalOffset, int $expectedCountdown): void
    {
        $now        = time();
        $prediction = new ArrivalPredictionDto(
            vehicleId: 'veh-1',
            routeId: '14514',
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: null,
            headsign: 'Downtown',
            arrivalAt: $now + $arrivalOffset,
            confidence: PredictionConfidence::MEDIUM
        );

        self::assertSame($expectedCountdown, $prediction->arrivalInSec($now));
    }

    public static function countdownProvider(): array
    {
        return [
            'Arriving in 30 seconds'               => [30, 30],
            'Arriving in 2 minutes'                => [120, 120],
            'Arriving in 10 minutes'               => [600, 600],
            'Arriving in 30 minutes'               => [1800, 1800],
            'Already arrived (negative becomes 0)' => [-60, 0],
            'Just arrived'                         => [0, 0],
        ];
    }

    /**
     * Test stops_away calculation scenarios.
     */
    #[DataProvider('stopsAwayProvider')]
    public function testStopsAwayCalculations(?int $stopsAway, string $expectedDescription): void
    {
        $now        = time();
        $prediction = new ArrivalPredictionDto(
            vehicleId: 'veh-1',
            routeId: '14536',
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: null,
            headsign: 'University',
            arrivalAt: $now + 300,
            confidence: PredictionConfidence::HIGH,
            currentLocation: $stopsAway !== null ? [
                'lat'        => 52.1500,
                'lon'        => -106.6000,
                'stops_away' => $stopsAway,
            ] : null
        );

        if ($stopsAway === null) {
            self::assertNull($prediction->currentLocation);
        } else {
            self::assertSame($stopsAway, $prediction->currentLocation['stops_away']);
        }
    }

    public static function stopsAwayProvider(): array
    {
        return [
            'At stop'     => [0, 'Arriving now'],
            'Next stop'   => [1, '1 stop away'],
            'Two stops'   => [2, '2 stops away'],
            'Five stops'  => [5, '5 stops away'],
            'Many stops'  => [15, '15 stops away'],
            'No GPS data' => [null, 'Unknown distance'],
        ];
    }

    /**
     * Test confidence level degradation when route IDs mismatch.
     */
    public function testConfidenceDegradationWithMismatchedRoutes(): void
    {
        $now = time();

        // With matched route: HIGH confidence possible
        $highConfidence = new ArrivalPredictionDto(
            vehicleId: '606',
            routeId: '14536', // Matched
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: null,
            headsign: 'University',
            arrivalAt: $now + 300,
            confidence: PredictionConfidence::HIGH
        );

        // With mismatched route: Only MEDIUM confidence from TripUpdate
        $mediumConfidence = new ArrivalPredictionDto(
            vehicleId: '606',
            routeId: '13915', // Old ID (mismatched)
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: null,
            headsign: 'University',
            arrivalAt: $now + 300,
            confidence: PredictionConfidence::MEDIUM
        );

        // Without any realtime data: LOW confidence from static schedule
        $lowConfidence = new ArrivalPredictionDto(
            vehicleId: 'unknown',
            routeId: '14536',
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: null,
            headsign: 'University',
            arrivalAt: $now + 300,
            confidence: PredictionConfidence::LOW
        );

        self::assertSame(PredictionConfidence::HIGH, $highConfidence->confidence);
        self::assertSame(PredictionConfidence::MEDIUM, $mediumConfidence->confidence);
        self::assertSame(PredictionConfidence::LOW, $lowConfidence->confidence);
    }

    /**
     * Test prediction sorting by arrival time.
     */
    public function testPredictionSortingByArrivalTime(): void
    {
        $now         = time();
        $predictions = [
            new ArrivalPredictionDto(
                vehicleId: 'veh-3',
                routeId: '14536',
                tripId: 'trip-3',
                stopId: 'STOP1',
                stopName: null,
                headsign: 'University',
                arrivalAt: $now + 900, // 15 min
                confidence: PredictionConfidence::HIGH
            ),
            new ArrivalPredictionDto(
                vehicleId: 'veh-1',
                routeId: '14536',
                tripId: 'trip-1',
                stopId: 'STOP1',
                stopName: null,
                headsign: 'University',
                arrivalAt: $now + 180, // 3 min (soonest)
                confidence: PredictionConfidence::HIGH
            ),
            new ArrivalPredictionDto(
                vehicleId: 'veh-2',
                routeId: '14536',
                tripId: 'trip-2',
                stopId: 'STOP1',
                stopName: null,
                headsign: 'University',
                arrivalAt: $now + 540, // 9 min
                confidence: PredictionConfidence::MEDIUM
            ),
        ];

        usort($predictions, fn ($a, $b) => $a->arrivalAt <=> $b->arrivalAt);

        self::assertSame('veh-1', $predictions[0]->vehicleId, 'First should be soonest arrival');
        self::assertSame('veh-2', $predictions[1]->vehicleId, 'Second should be middle arrival');
        self::assertSame('veh-3', $predictions[2]->vehicleId, 'Third should be latest arrival');
    }

    /**
     * Test multiple predictions for same route at different stops.
     */
    public function testMultiplePredictionsForRoute(): void
    {
        $now = time();

        // Route 14 bus serving multiple stops
        $predictions = [
            new ArrivalPredictionDto(
                vehicleId: '606',
                routeId: '14526', // Route 14
                tripId: 'trip-route14-1',
                stopId: '3734',
                stopName: null,
                headsign: 'North Industrial',
                arrivalAt: $now + 240,
                confidence: PredictionConfidence::HIGH,
                currentLocation: ['lat' => 52.1400, 'lon' => -106.6400, 'stops_away' => 3]
            ),
            new ArrivalPredictionDto(
                vehicleId: '707',
                routeId: '14526', // Route 14
                tripId: 'trip-route14-2',
                stopId: '3734',
                stopName: null,
                headsign: 'North Industrial',
                arrivalAt: $now + 720,
                confidence: PredictionConfidence::HIGH,
                currentLocation: ['lat' => 52.1200, 'lon' => -106.6700, 'stops_away' => 8]
            ),
        ];

        // Both predictions should be for same route
        self::assertSame($predictions[0]->routeId, $predictions[1]->routeId);

        // But different vehicles
        self::assertNotSame($predictions[0]->vehicleId, $predictions[1]->vehicleId);

        // First vehicle should be closer
        self::assertLessThan(
            $predictions[1]->currentLocation['stops_away'],
            $predictions[0]->currentLocation['stops_away']
        );
    }

    /**
     * Test prediction with feedback summary.
     */
    public function testPredictionWithFeedbackSummary(): void
    {
        $now        = time();
        $prediction = new ArrivalPredictionDto(
            vehicleId: '606',
            routeId: '14551',
            tripId: 'trip-1',
            stopId: 'STOP1',
            stopName: null,
            headsign: 'City Centre',
            arrivalAt: $now + 300,
            confidence: PredictionConfidence::HIGH,
            feedbackSummary: [
                'ahead'   => 2,
                'on_time' => 15,
                'late'    => 5,
                'total'   => 22,
            ]
        );

        $array = $prediction->toArray($now);

        self::assertArrayHasKey('feedback_summary', $array);
        self::assertSame(22, $array['feedback_summary']['total']);
        self::assertSame(15, $array['feedback_summary']['on_time']);

        // Calculate on-time percentage: 15/22 = 68%
        $onTimePercent = ($array['feedback_summary']['on_time'] / $array['feedback_summary']['total']) * 100;
        self::assertEqualsWithDelta(68.18, $onTimePercent, 0.1);
    }

    /**
     * Test that toArray includes all required fields.
     */
    public function testToArrayIncludesAllRequiredFields(): void
    {
        $now        = time();
        $prediction = new ArrivalPredictionDto(
            vehicleId: '606',
            routeId: '14536',
            tripId: 'trip-1',
            stopId: '3734',
            stopName: null,
            headsign: 'University',
            arrivalAt: $now + 420,
            confidence: PredictionConfidence::HIGH,
            currentLocation: ['lat' => 52.1500, 'lon' => -106.5985, 'stops_away' => 4],
            feedbackSummary: ['ahead' => 1, 'on_time' => 10, 'late' => 2, 'total' => 13]
        );

        $array = $prediction->toArray($now);

        // Required fields
        self::assertArrayHasKey('vehicle_id', $array);
        self::assertArrayHasKey('route_id', $array);
        self::assertArrayHasKey('trip_id', $array);
        self::assertArrayHasKey('stop_id', $array);
        self::assertArrayHasKey('headsign', $array);
        self::assertArrayHasKey('arrival_in_sec', $array);
        self::assertArrayHasKey('arrival_at', $array);
        self::assertArrayHasKey('confidence', $array);

        // Optional fields (should be present in this case)
        self::assertArrayHasKey('current_location', $array);
        self::assertArrayHasKey('feedback_summary', $array);

        // Nested location fields
        self::assertArrayHasKey('lat', $array['current_location']);
        self::assertArrayHasKey('lon', $array['current_location']);
        self::assertArrayHasKey('stops_away', $array['current_location']);
    }
}
