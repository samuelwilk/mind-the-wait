<?php

declare(strict_types=1);

namespace App\Tests\Service\Proximity;

use App\Service\Proximity\ProximityCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ProximityCalculator::class)]
final class ProximityCalculatorTest extends TestCase
{
    private ProximityCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ProximityCalculator();
    }

    /**
     * Test Haversine formula with known real-world distances.
     */
    #[DataProvider('realWorldDistanceProvider')]
    public function testRealWorldDistances(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $expectedMeters,
        float $toleranceMeters = 50.0,
    ): void {
        $distance = $this->calculator->distanceBetween($lat1, $lon1, $lat2, $lon2);

        self::assertEqualsWithDelta(
            $expectedMeters,
            $distance,
            $toleranceMeters,
            sprintf(
                'Distance should be approximately %.0f meters (±%.0fm), got %.2f meters',
                $expectedMeters,
                $toleranceMeters,
                $distance
            )
        );
    }

    public static function realWorldDistanceProvider(): array
    {
        return [
            'Saskatoon City Centre to University' => [
                52.1324, -106.6607, // City Centre
                52.1321, -106.6351, // University of Saskatchewan
                1750.0, // ~1.75 km
                50.0,
            ],
            'Evergreen to Confederation Mall' => [
                52.1674, -106.5755, // Evergreen (Wyant stop)
                52.1297, -106.7093, // Confederation Mall
                10050.0, // ~10 km
                200.0,
            ],
            'Same location (zero distance)' => [
                52.1324, -106.6607,
                52.1324, -106.6607,
                0.0,
                1.0,
            ],
            'One city block (~100m)' => [
                52.1324, -106.6607,
                52.1333, -106.6607, // ~100m north
                100.0,
                10.0,
            ],
            'Adjacent bus stops (~200m)' => [
                52.1320, -106.6350,
                52.1338, -106.6350, // ~200m apart
                200.0,
                20.0,
            ],
        ];
    }

    /**
     * Test distance calculation with route 43 stops (Evergreen / City Centre).
     */
    public function testRoute43StopDistances(): void
    {
        // Route 43: Evergreen / Wyant stop to Primrose / Lenore stop
        $distance = $this->calculator->distanceBetween(
            52.1674, -106.5755, // Evergreen / Wyant
            52.1638, -106.6227  // Primrose / Lenore
        );

        // These stops are approximately 3.2 km apart
        self::assertGreaterThan(3200, $distance);
        self::assertLessThan(3300, $distance);
    }

    /**
     * Test that distance is symmetric (A→B === B→A).
     */
    public function testDistanceIsSymmetric(): void
    {
        $lat1 = 52.1324;
        $lon1 = -106.6607;
        $lat2 = 52.1321;
        $lon2 = -106.6351;

        $distanceAtoB = $this->calculator->distanceBetween($lat1, $lon1, $lat2, $lon2);
        $distanceBtoA = $this->calculator->distanceBetween($lat2, $lon2, $lat1, $lon1);

        self::assertEqualsWithDelta($distanceAtoB, $distanceBtoA, 0.001);
    }

    /**
     * Test distance increases monotonically as points move apart.
     */
    public function testDistanceIncreasesMonotonically(): void
    {
        $origin = ['lat' => 52.1324, 'lon' => -106.6607];

        // Points progressively farther north
        $point1 = ['lat' => 52.1334, 'lon' => -106.6607]; // ~1km north
        $point2 = ['lat' => 52.1344, 'lon' => -106.6607]; // ~2km north
        $point3 = ['lat' => 52.1354, 'lon' => -106.6607]; // ~3km north

        $d1 = $this->calculator->distanceBetween($origin['lat'], $origin['lon'], $point1['lat'], $point1['lon']);
        $d2 = $this->calculator->distanceBetween($origin['lat'], $origin['lon'], $point2['lat'], $point2['lon']);
        $d3 = $this->calculator->distanceBetween($origin['lat'], $origin['lon'], $point3['lat'], $point3['lon']);

        self::assertLessThan($d2, $d1);
        self::assertLessThan($d3, $d2);
    }

    /**
     * Test that small coordinate changes produce small distance changes.
     */
    public function testSmallCoordinateChanges(): void
    {
        $baseLat = 52.1324;
        $baseLon = -106.6607;

        // Move 0.0001 degrees (~11 meters at this latitude)
        $distance = $this->calculator->distanceBetween(
            $baseLat,
            $baseLon,
            $baseLat + 0.0001,
            $baseLon
        );

        self::assertGreaterThan(10, $distance);
        self::assertLessThan(12, $distance);
    }

    /**
     * Test with equator coordinates (different Earth curvature).
     */
    public function testEquatorCoordinates(): void
    {
        // Two points on the equator, 1 degree apart
        // At equator: 1 degree longitude ≈ 111 km
        $distance = $this->calculator->distanceBetween(
            0.0, 0.0,
            0.0, 1.0
        );

        self::assertEqualsWithDelta(111195, $distance, 100);
    }

    /**
     * Test with polar coordinates (extreme latitude).
     */
    public function testPolarCoordinates(): void
    {
        // Two points near north pole
        // At high latitudes, longitude degrees represent shorter distances
        $distance = $this->calculator->distanceBetween(
            89.0, 0.0,
            89.0, 1.0
        );

        // Much shorter than at equator
        self::assertLessThan(2000, $distance);
    }

    /**
     * Test that negative coordinates work correctly (Southern/Western hemispheres).
     */
    public function testNegativeCoordinates(): void
    {
        // Sydney (-33.8688, 151.2093) to Melbourne (-37.8136, 144.9631)
        $distance = $this->calculator->distanceBetween(
            -33.8688, 151.2093,
            -37.8136, 144.9631
        );

        // Distance is approximately 714 km
        self::assertEqualsWithDelta(714000, $distance, 5000);
    }

    /**
     * Test maximum possible distance (antipodal points).
     */
    public function testMaximumDistance(): void
    {
        // Two points on opposite sides of Earth
        $distance = $this->calculator->distanceBetween(
            0.0, 0.0,     // Equator, Prime Meridian
            0.0, 180.0    // Equator, International Date Line
        );

        // Half of Earth's circumference ≈ 20,015 km
        self::assertEqualsWithDelta(20015000, $distance, 10000);
    }
}
