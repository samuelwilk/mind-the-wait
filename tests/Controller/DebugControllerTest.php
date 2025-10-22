<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DebugController;
use App\Enum\TransitImpact;
use App\Factory\RouteFactory;
use App\Factory\RoutePerformanceDailyFactory;
use App\Factory\WeatherObservationFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DebugControllerTest extends KernelTestCase
{
    use Factories;
    // Note: Using DAMA\DoctrineTestBundle for transaction isolation (configured in phpunit.dist.xml)
    // Do NOT use ResetDatabase trait when using DAMA

    private DebugController $controller;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);
        $this->controller = self::getContainer()->get(DebugController::class);
    }

    public function testDatabaseStatsReturnsCorrectStructure(): void
    {
        $response = $this->controller->databaseStats();
        $data     = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('gtfs_static', $data);
        $this->assertArrayHasKey('historical_data', $data);
        $this->assertArrayHasKey('_note', $data);

        // Check structure
        $this->assertArrayHasKey('routes', $data['gtfs_static']);
        $this->assertArrayHasKey('stops', $data['gtfs_static']);
        $this->assertArrayHasKey('trips', $data['gtfs_static']);

        $this->assertArrayHasKey('arrival_logs', $data['historical_data']);
        $this->assertArrayHasKey('route_performance_daily', $data['historical_data']);
        $this->assertArrayHasKey('bunching_incidents', $data['historical_data']);
        $this->assertArrayHasKey('weather_observations', $data['historical_data']);
    }

    public function testDatabaseStatsWithWeatherData(): void
    {
        WeatherObservationFactory::createOne([
            'observedAt'         => new \DateTimeImmutable('2025-10-15 01:00:00'),
            'temperatureCelsius' => '3.8',
            'feelsLikeCelsius'   => '2.5',
            'weatherCode'        => 3,
            'weatherCondition'   => 'Overcast',
            'transitImpact'      => TransitImpact::NONE,
            'precipitationMm'    => '0.0',
            'snowfallCm'         => '0.0',
            'snowDepthCm'        => 0,
            'visibilityKm'       => '10.0',
            'windSpeedKmh'       => '5.0',
        ]);

        $response = $this->controller->databaseStats();
        $data     = json_decode($response->getContent(), true);

        $this->assertGreaterThan(0, $data['historical_data']['weather_observations']);
        $this->assertArrayHasKey('latest_weather', $data);
        $this->assertEquals('2025-10-15 01:00:00', $data['latest_weather']['observed_at']);
        $this->assertEquals('3.8', $data['latest_weather']['temperature']);
        $this->assertEquals('Overcast', $data['latest_weather']['condition']);
        $this->assertEquals('none', $data['latest_weather']['impact']);
    }

    public function testDatabaseStatsWithPerformanceData(): void
    {
        $route = RouteFactory::createOne([
            'gtfsId'    => 'test-route-1',
            'shortName' => '1',
            'longName'  => 'Test Route 1',
        ]);

        RoutePerformanceDailyFactory::createOne([
            'route'                 => $route,
            'date'                  => new \DateTimeImmutable('2025-10-14'),
            'totalPredictions'      => 100,
            'highConfidenceCount'   => 80,
            'mediumConfidenceCount' => 15,
            'lowConfidenceCount'    => 5,
            'avgDelaySec'           => 120,
            'medianDelaySec'        => 90,
            'onTimePercentage'      => '75.50',
            'latePercentage'        => '20.00',
            'earlyPercentage'       => '4.50',
            'bunchingIncidents'     => 0,
        ]);

        $response = $this->controller->databaseStats();
        $data     = json_decode($response->getContent(), true);

        $this->assertGreaterThan(0, $data['gtfs_static']['routes']);
        $this->assertGreaterThan(0, $data['historical_data']['route_performance_daily']);
        $this->assertArrayHasKey('latest_performance', $data);
        $this->assertEquals('2025-10-14', $data['latest_performance']['date']);
        $this->assertEquals('1', $data['latest_performance']['route_short_name']);
        $this->assertEquals(100, $data['latest_performance']['total_predictions']);
        $this->assertEquals(75.5, $data['latest_performance']['on_time_percentage']);
    }

    public function testResponseIsJson(): void
    {
        $response = $this->controller->databaseStats();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        // Verify it's valid JSON
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testDoesNotExposeSensitiveData(): void
    {
        $response = $this->controller->databaseStats();
        $content  = $response->getContent();

        // Verify no database credentials are exposed
        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('secret', strtolower($content));
        $this->assertStringNotContainsString('token', strtolower($content));
        $this->assertStringNotContainsString('api_key', strtolower($content));
        $this->assertStringNotContainsString('DATABASE_URL', $content);
        $this->assertStringNotContainsString('REDIS_URL', $content);

        // Verify no actual record data is exposed (only counts and latest)
        $data = json_decode($content, true);
        $this->assertArrayNotHasKey('users', $data);
        $this->assertArrayNotHasKey('credentials', $data);
        $this->assertArrayNotHasKey('sessions', $data);
    }

    public function testOnlyExposesCountsNotRecords(): void
    {
        $response = $this->controller->databaseStats();
        $data     = json_decode($response->getContent(), true);

        // Verify all values under gtfs_static are integers (counts)
        foreach ($data['gtfs_static'] as $key => $value) {
            $this->assertIsInt($value, "gtfs_static.$key should be an integer count");
        }

        // Verify all values under historical_data are integers (counts)
        foreach ($data['historical_data'] as $key => $value) {
            $this->assertIsInt($value, "historical_data.$key should be an integer count");
        }

        // If latest_weather exists, it should only have specific safe fields
        if (isset($data['latest_weather'])) {
            $allowedWeatherFields = ['observed_at', 'temperature', 'condition', 'impact'];
            foreach (array_keys($data['latest_weather']) as $field) {
                $this->assertContains($field, $allowedWeatherFields, "Unexpected field in latest_weather: $field");
            }
        }

        // If latest_performance exists, it should only have specific safe fields
        if (isset($data['latest_performance'])) {
            $allowedPerformanceFields = ['date', 'route_short_name', 'total_predictions', 'on_time_percentage'];
            foreach (array_keys($data['latest_performance']) as $field) {
                $this->assertContains($field, $allowedPerformanceFields, "Unexpected field in latest_performance: $field");
            }
        }
    }

    public function testIncludesSecurityWarningNote(): void
    {
        $response = $this->controller->databaseStats();
        $data     = json_decode($response->getContent(), true);

        // Verify the warning note is present
        $this->assertArrayHasKey('_note', $data);
        $this->assertStringContainsString('temporary', strtolower($data['_note']));
        $this->assertStringContainsString('debug', strtolower($data['_note']));
    }

    public function testDoesNotExposeFullDatabaseRecords(): void
    {
        RouteFactory::createOne([
            'gtfsId'    => 'sensitive-route-id',
            'shortName' => '999',
            'longName'  => 'Sensitive Route Name',
        ]);

        $response = $this->controller->databaseStats();
        $content  = $response->getContent();

        // Verify specific route details are NOT exposed
        $this->assertStringNotContainsString('sensitive-route-id', $content);
        $this->assertStringNotContainsString('Sensitive Route Name', $content);

        // Only counts and latest record summary should be present
        $data = json_decode($content, true);
        $this->assertIsInt($data['gtfs_static']['routes']);
        $this->assertGreaterThan(0, $data['gtfs_static']['routes']);
    }
}
