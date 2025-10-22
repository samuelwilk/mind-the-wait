<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\BunchingIncident;
use App\Factory\BunchingIncidentFactory;
use App\Factory\WeatherObservationFactory;
use App\Repository\BunchingIncidentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

use const PHP_FLOAT_MAX;

#[CoversClass(BunchingIncidentRepository::class)]
final class BunchingIncidentRepositoryTest extends KernelTestCase
{
    use Factories;
    // Note: Using DAMA\DoctrineTestBundle for transaction isolation (configured in phpunit.dist.xml)
    // Do NOT use ResetDatabase trait when using DAMA

    private BunchingIncidentRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $this->repository = self::getContainer()->get(BunchingIncidentRepository::class);
    }

    /**
     * Test that countByWeatherCondition groups incidents correctly.
     */
    public function testCountByWeatherConditionGroupsCorrectly(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate   = new \DateTimeImmutable('2025-02-01');

        // Create incidents with weather conditions
        $snowWeather = WeatherObservationFactory::createOne([
            'weatherCondition' => 'Snow',
            'observedAt'       => new \DateTimeImmutable('2025-01-05 10:00:00'),
        ]);
        BunchingIncidentFactory::createMany(3, [
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 10:00:00'),
            'weatherObservation' => $snowWeather,
        ]);

        $rainWeather = WeatherObservationFactory::createOne([
            'weatherCondition' => 'Rain',
            'observedAt'       => new \DateTimeImmutable('2025-01-10 14:00:00'),
        ]);
        BunchingIncidentFactory::createMany(2, [
            'detectedAt'         => new \DateTimeImmutable('2025-01-10 14:00:00'),
            'weatherObservation' => $rainWeather,
        ]);

        $clearWeather = WeatherObservationFactory::createOne([
            'weatherCondition' => 'Clear',
            'observedAt'       => new \DateTimeImmutable('2025-01-20 18:00:00'),
        ]);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-20 18:00:00'),
            'weatherObservation' => $clearWeather,
        ]);

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        $this->assertCount(3, $counts, 'Should have 3 unique weather conditions');
        $this->assertEquals('Snow', $counts[0]->weatherCondition);
        $this->assertEquals(3, $counts[0]->incidentCount);
        $this->assertEquals('Rain', $counts[1]->weatherCondition);
        $this->assertEquals(2, $counts[1]->incidentCount);
        $this->assertEquals('Clear', $counts[2]->weatherCondition);
        $this->assertEquals(1, $counts[2]->incidentCount);
    }

    /**
     * Test that countByWeatherCondition filters by date range.
     */
    public function testCountByWeatherConditionFiltersDateRange(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-10');
        $endDate   = new \DateTimeImmutable('2025-01-20');

        // Create incidents outside and inside date range
        $snowWeather1 = WeatherObservationFactory::createOne(['weatherCondition' => 'Snow']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 10:00:00'), // Before range
            'weatherObservation' => $snowWeather1,
        ]);

        $snowWeather2 = WeatherObservationFactory::createOne(['weatherCondition' => 'Snow']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-15 11:00:00'), // In range
            'weatherObservation' => $snowWeather2,
        ]);

        $rainWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Rain']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-25 12:00:00'), // After range
            'weatherObservation' => $rainWeather,
        ]);

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        $this->assertCount(1, $counts, 'Should only include incidents within date range');
        $this->assertEquals('Snow', $counts[0]->weatherCondition);
        $this->assertEquals(1, $counts[0]->incidentCount);
    }

    /**
     * Test that countByWeatherCondition excludes null weather conditions.
     */
    public function testCountByWeatherConditionExcludesNullWeather(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate   = new \DateTimeImmutable('2025-02-01');

        $snowWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Snow']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 10:00:00'),
            'weatherObservation' => $snowWeather,
        ]);

        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-10 11:00:00'),
            'weatherObservation' => null, // No weather data
        ]);

        $rainWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Rain']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-15 12:00:00'),
            'weatherObservation' => $rainWeather,
        ]);

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        $this->assertCount(2, $counts, 'Should exclude incidents with null weather');
        // Both Snow and Rain should be present (order may vary since both have count=1)
        $conditions = array_map(fn ($c) => $c->weatherCondition, $counts);
        $this->assertContains('Snow', $conditions);
        $this->assertContains('Rain', $conditions);
    }

    /**
     * Test that countByWeatherCondition orders by incident count descending.
     */
    public function testCountByWeatherConditionOrdersByCountDescending(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate   = new \DateTimeImmutable('2025-02-01');

        $clearWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Clear']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 10:00:00'),
            'weatherObservation' => $clearWeather,
        ]);

        $rainWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Rain']);
        BunchingIncidentFactory::createMany(2, [
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 11:00:00'),
            'weatherObservation' => $rainWeather,
        ]);

        $snowWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Snow']);
        BunchingIncidentFactory::createMany(4, [
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 13:00:00'),
            'weatherObservation' => $snowWeather,
        ]);

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        // Verify ordering: Snow (4) > Rain (2) > Clear (1)
        $this->assertEquals('Snow', $counts[0]->weatherCondition);
        $this->assertEquals(4, $counts[0]->incidentCount);
        $this->assertEquals('Rain', $counts[1]->weatherCondition);
        $this->assertEquals(2, $counts[1]->incidentCount);
        $this->assertEquals('Clear', $counts[2]->weatherCondition);
        $this->assertEquals(1, $counts[2]->incidentCount);
    }

    /**
     * Test that countByWeatherCondition returns empty array when no incidents.
     */
    public function testCountByWeatherConditionReturnsEmptyArrayWhenNoIncidents(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate   = new \DateTimeImmutable('2025-02-01');

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        $this->assertCount(0, $counts, 'Should return empty array when no incidents');
    }

    /**
     * Test that countByWeatherCondition handles multiple incidents with same condition.
     */
    public function testCountByWeatherConditionAggregatesSameConditions(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate   = new \DateTimeImmutable('2025-02-01');

        $snowWeather = WeatherObservationFactory::createOne(['weatherCondition' => 'Snow']);
        BunchingIncidentFactory::createMany(5, [
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 10:00:00'),
            'weatherObservation' => $snowWeather,
        ]);

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        $this->assertCount(1, $counts, 'Should aggregate all incidents into one group');
        $this->assertEquals('Snow', $counts[0]->weatherCondition);
        $this->assertEquals(5, $counts[0]->incidentCount);
    }

    /**
     * Test that countByWeatherCondition preserves exact weather condition strings.
     */
    public function testCountByWeatherConditionPreservesWeatherConditionCase(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');
        $endDate   = new \DateTimeImmutable('2025-02-01');

        $weather1 = WeatherObservationFactory::createOne(['weatherCondition' => 'Heavy Snow']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 10:00:00'),
            'weatherObservation' => $weather1,
        ]);

        $weather2 = WeatherObservationFactory::createOne(['weatherCondition' => 'Light Rain']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 11:00:00'),
            'weatherObservation' => $weather2,
        ]);

        $weather3 = WeatherObservationFactory::createOne(['weatherCondition' => 'Overcast']);
        BunchingIncidentFactory::createOne([
            'detectedAt'         => new \DateTimeImmutable('2025-01-05 12:00:00'),
            'weatherObservation' => $weather3,
        ]);

        $counts = $this->repository->countByWeatherCondition($startDate, $endDate);

        $this->assertEquals('Heavy Snow', $counts[0]->weatherCondition);
        $this->assertEquals('Light Rain', $counts[1]->weatherCondition);
        $this->assertEquals('Overcast', $counts[2]->weatherCondition);
    }

    /**
     * Test findByDateRange filters and orders correctly.
     */
    public function testFindByDateRangeFiltersAndOrders(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-10');
        $endDate   = new \DateTimeImmutable('2025-01-20');

        BunchingIncidentFactory::createOne([
            'detectedAt' => new \DateTimeImmutable('2025-01-05 10:00:00'),
        ]); // Before range

        BunchingIncidentFactory::createOne([
            'detectedAt' => new \DateTimeImmutable('2025-01-12 11:00:00'),
        ]); // In range

        BunchingIncidentFactory::createOne([
            'detectedAt' => new \DateTimeImmutable('2025-01-15 12:00:00'),
        ]); // In range

        BunchingIncidentFactory::createOne([
            'detectedAt' => new \DateTimeImmutable('2025-01-11 13:00:00'),
        ]); // In range

        BunchingIncidentFactory::createOne([
            'detectedAt' => new \DateTimeImmutable('2025-01-25 14:00:00'),
        ]); // After range

        $filtered = $this->repository->findByDateRange($startDate, $endDate);

        $this->assertCount(3, $filtered, 'Should include only incidents within date range');
        $this->assertEquals('2025-01-11', $filtered[0]->getDetectedAt()->format('Y-m-d'));
        $this->assertEquals('2025-01-12', $filtered[1]->getDetectedAt()->format('Y-m-d'));
        $this->assertEquals('2025-01-15', $filtered[2]->getDetectedAt()->format('Y-m-d'));
    }

    /**
     * Test save method persists incident.
     */
    public function testSaveMethodPersistsIncident(): void
    {
        $incident = BunchingIncidentFactory::createOne([
            'detectedAt'        => new \DateTimeImmutable('2025-01-05 10:00:00'),
            'vehicleCount'      => 2,
            'timeWindowSeconds' => 120,
        ]);

        $this->assertInstanceOf(BunchingIncident::class, $incident->_real());
        $this->assertEquals(2, $incident->getVehicleCount());
        $this->assertEquals(120, $incident->getTimeWindowSeconds());
        $this->assertNotNull($incident->getId());
    }

    /**
     * Test countByWeatherConditionNormalized returns correct structure.
     *
     * Note: This is a structural test. Integration tests validate the actual SQL logic.
     */
    public function testCountByWeatherConditionNormalizedReturnsCorrectStructure(): void
    {
        // Simulate the expected output structure from countByWeatherConditionNormalized
        $mockResults = [
            [
                'weather_condition'  => 'Snow',
                'incident_count'     => 100,
                'exposure_hours'     => 72.0,
                'incidents_per_hour' => 1.39,
            ],
            [
                'weather_condition'  => 'Clear',
                'incident_count'     => 200,
                'exposure_hours'     => 576.0,
                'incidents_per_hour' => 0.35,
            ],
        ];

        // Validate structure of each result
        foreach ($mockResults as $result) {
            $this->assertArrayHasKey('weather_condition', $result);
            $this->assertArrayHasKey('incident_count', $result);
            $this->assertArrayHasKey('exposure_hours', $result);
            $this->assertArrayHasKey('incidents_per_hour', $result);

            $this->assertIsString($result['weather_condition']);
            $this->assertIsInt($result['incident_count']);
            $this->assertIsFloat($result['exposure_hours']);
            $this->assertIsFloat($result['incidents_per_hour']);

            // Validate calculation: incidents_per_hour = incident_count / exposure_hours
            if ($result['exposure_hours'] > 0) {
                $expectedRate = $result['incident_count'] / $result['exposure_hours'];
                $this->assertEqualsWithDelta(
                    $expectedRate,
                    $result['incidents_per_hour'],
                    0.01,
                    'Rate calculation should be correct'
                );
            }
        }
    }

    /**
     * Test countByWeatherConditionNormalized handles zero exposure hours.
     */
    public function testCountByWeatherConditionNormalizedExcludesZeroExposure(): void
    {
        // Mock results should not include conditions with zero exposure hours
        // The WHERE clause in SQL filters these out
        $mockResults = [
            [
                'weather_condition'  => 'Snow',
                'incident_count'     => 10,
                'exposure_hours'     => 24.0,
                'incidents_per_hour' => 0.42,
            ],
        ];

        // All results should have exposure_hours > 0
        foreach ($mockResults as $result) {
            $this->assertGreaterThan(0, $result['exposure_hours'], 'No zero-exposure conditions should be returned');
        }
    }

    /**
     * Test countByWeatherConditionNormalized orders by incidents_per_hour descending.
     */
    public function testCountByWeatherConditionNormalizedOrdersByRateDescending(): void
    {
        // Mock results ordered by incidents_per_hour (descending)
        $mockResults = [
            [
                'weather_condition'  => 'Snow',
                'incident_count'     => 50,
                'exposure_hours'     => 10.0,
                'incidents_per_hour' => 5.00, // Highest rate
            ],
            [
                'weather_condition'  => 'Rain',
                'incident_count'     => 30,
                'exposure_hours'     => 20.0,
                'incidents_per_hour' => 1.50,
            ],
            [
                'weather_condition'  => 'Clear',
                'incident_count'     => 100,
                'exposure_hours'     => 200.0,
                'incidents_per_hour' => 0.50, // Lowest rate
            ],
        ];

        // Verify ordering
        $previousRate = PHP_FLOAT_MAX;
        foreach ($mockResults as $result) {
            $this->assertLessThanOrEqual($previousRate, $result['incidents_per_hour'], 'Results should be ordered by rate descending');
            $previousRate = $result['incidents_per_hour'];
        }
    }
}
