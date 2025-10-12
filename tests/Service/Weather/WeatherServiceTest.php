<?php

declare(strict_types=1);

namespace App\Tests\Service\Weather;

use App\Enum\TransitImpact;
use App\Service\Weather\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(WeatherService::class)]
#[UsesClass(TransitImpact::class)]
final class WeatherServiceTest extends TestCase
{
    /**
     * Test winter severe conditions.
     */
    #[DataProvider('winterSevereConditionsProvider')]
    public function testWinterSevereConditions(
        float $temperature,
        float $snowfallCm,
        float $visibilityM,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: $temperature,
            condition: 'snow',
            snowfallCm: $snowfallCm,
            precipitationMm: 0,
            visibilityM: $visibilityM,
            windSpeedKmh: 0,
        );

        self::assertSame(TransitImpact::SEVERE, $impact, $description);
    }

    public static function winterSevereConditionsProvider(): array
    {
        return [
            [-40, 0, 10000, 'Extreme cold below -35°C'],
            [-36, 0, 10000, 'Extreme cold near -35°C'],
            [-20, 20, 10000, 'Heavy snowfall >15cm'],
            [-20, 16, 10000, 'Heavy snowfall >15cm'],
            [-10, 5, 400, 'Low visibility <0.5km'],
            [-10, 5, 400, 'Low visibility <0.5km'],
        ];
    }

    /**
     * Test summer severe conditions.
     */
    #[DataProvider('summerSevereConditionsProvider')]
    public function testSummerSevereConditions(
        float $temperature,
        string $condition,
        float $precipitationMm,
        float $windSpeedKmh,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: $temperature,
            condition: $condition,
            snowfallCm: 0,
            precipitationMm: $precipitationMm,
            visibilityM: 10000,
            windSpeedKmh: $windSpeedKmh,
        );

        self::assertSame(TransitImpact::SEVERE, $impact, $description);
    }

    public static function summerSevereConditionsProvider(): array
    {
        return [
            [25, 'thunderstorm', 0, 0, 'Thunderstorm conditions'],
            [25, 'rain', 30, 0, 'Heavy rain/flooding >25mm'],
            [25, 'rain', 26, 0, 'Heavy rain >25mm'],
            [36, 'clear', 0, 0, 'Extreme heat >35°C'],
            [36, 'clear', 0, 0, 'Extreme heat >35°C'],
            [25, 'clear', 0, 80, 'High winds >70 km/h'],
            [25, 'clear', 0, 75, 'High winds >70 km/h'],
        ];
    }

    /**
     * Test winter moderate conditions.
     */
    #[DataProvider('winterModerateConditionsProvider')]
    public function testWinterModerateConditions(
        float $temperature,
        float $snowfallCm,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: $temperature,
            condition: 'snow',
            snowfallCm: $snowfallCm,
            precipitationMm: 0,
            visibilityM: 10000,
            windSpeedKmh: 0,
        );

        self::assertSame(TransitImpact::MODERATE, $impact, $description);
    }

    public static function winterModerateConditionsProvider(): array
    {
        return [
            [-30, 0, 'Very cold below -25°C'],
            [-26, 0, 'Very cold below -25°C'],
            [-20, 8, 'Heavy snow >5cm'],
            [-20, 6, 'Heavy snow >5cm'],
        ];
    }

    /**
     * Test year-round moderate conditions.
     */
    #[DataProvider('yearRoundModerateConditionsProvider')]
    public function testYearRoundModerateConditions(
        float $precipitationMm,
        float $visibilityM,
        float $windSpeedKmh,
        string $condition,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: 20,
            condition: $condition,
            snowfallCm: 0,
            precipitationMm: $precipitationMm,
            visibilityM: $visibilityM,
            windSpeedKmh: $windSpeedKmh,
        );

        self::assertSame(TransitImpact::MODERATE, $impact, $description);
    }

    public static function yearRoundModerateConditionsProvider(): array
    {
        return [
            [15, 10000, 0, 'rain', 'Heavy rain >10mm'],
            [11, 10000, 0, 'rain', 'Heavy rain >10mm'],
            [0, 1500, 0, 'clear', 'Poor visibility <2km'],
            [0, 1900, 0, 'clear', 'Poor visibility <2km'],
            [0, 10000, 60, 'clear', 'Strong winds >50 km/h'],
            [0, 10000, 55, 'clear', 'Strong winds >50 km/h'],
            [0, 10000, 0, 'showers', 'Heavy rain showers'],
            [6, 10000, 0, 'rain', 'Rain with >5mm precipitation'],
        ];
    }

    /**
     * Test winter minor conditions.
     */
    #[DataProvider('winterMinorConditionsProvider')]
    public function testWinterMinorConditions(
        float $temperature,
        float $snowfallCm,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: $temperature,
            condition: 'snow',
            snowfallCm: $snowfallCm,
            precipitationMm: 0,
            visibilityM: 10000,
            windSpeedKmh: 0,
        );

        self::assertSame(TransitImpact::MINOR, $impact, $description);
    }

    public static function winterMinorConditionsProvider(): array
    {
        return [
            [-20, 0, 'Cold below -15°C'],
            [-15, 0, 'Cold at -15°C (boundary)'],
            [-10, 1, 'Light snow <=2cm'],
            [-10, 2, 'Light snow at 2cm (boundary)'],
        ];
    }

    /**
     * Test year-round minor conditions.
     */
    #[DataProvider('yearRoundMinorConditionsProvider')]
    public function testYearRoundMinorConditions(
        float $temperature,
        string $condition,
        float $precipitationMm,
        float $windSpeedKmh,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: $temperature,
            condition: $condition,
            snowfallCm: 0,
            precipitationMm: $precipitationMm,
            visibilityM: 10000,
            windSpeedKmh: $windSpeedKmh,
        );

        self::assertSame(TransitImpact::MINOR, $impact, $description);
    }

    public static function yearRoundMinorConditionsProvider(): array
    {
        return [
            [20, 'rain', 0, 0, 'Light rain'],
            [20, 'clear', 3, 0, 'Precipitation >2mm'],
            [30, 'clear', 0, 0, 'Hot but not extreme (28-35°C)'],
            [29, 'clear', 0, 0, 'Hot >28°C'],
            [20, 'clear', 0, 40, 'Moderate winds >30 km/h'],
            [20, 'clear', 0, 35, 'Moderate winds >30 km/h'],
        ];
    }

    /**
     * Test no impact conditions.
     */
    #[DataProvider('noImpactConditionsProvider')]
    public function testNoImpactConditions(
        float $temperature,
        string $condition,
        string $description,
    ): void {
        $service = $this->createWeatherService();
        $impact  = $service->classifyTransitImpact(
            temperature: $temperature,
            condition: $condition,
            snowfallCm: 0,
            precipitationMm: 0,
            visibilityM: 10000,
            windSpeedKmh: 0,
        );

        self::assertSame(TransitImpact::NONE, $impact, $description);
    }

    public static function noImpactConditionsProvider(): array
    {
        return [
            [20, 'clear', 'Clear and mild'],
            [10, 'clear', 'Clear and cool'],
            [-10, 'clear', 'Cold but clear (-10°C)'],
            [25, 'cloudy', 'Cloudy but warm'],
            [0, 'cloudy', 'Cloudy and cold'],
        ];
    }

    /**
     * Test weather code mapping.
     */
    #[DataProvider('weatherCodeMappingProvider')]
    public function testWeatherCodeMapping(int $weatherCode, string $expectedCondition): void
    {
        $service = $this->createWeatherService();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('mapWeatherCode');
        $method->setAccessible(true);

        $result = $method->invoke($service, $weatherCode);
        self::assertSame($expectedCondition, $result);
    }

    public static function weatherCodeMappingProvider(): array
    {
        return [
            [0, 'clear'],
            [1, 'cloudy'],
            [2, 'cloudy'],
            [3, 'cloudy'],
            [51, 'rain'],
            [61, 'rain'],
            [67, 'rain'],
            [71, 'snow'],
            [75, 'snow'],
            [77, 'snow'],
            [80, 'showers'],
            [85, 'showers'],
            [86, 'showers'],
            [95, 'thunderstorm'],
            [99, 'thunderstorm'],
            [999, 'unknown'],
        ];
    }

    /**
     * Test boundary conditions for classification thresholds.
     */
    public function testBoundaryConditionsAroundThresholds(): void
    {
        $service = $this->createWeatherService();

        // Test just above and below -35°C threshold
        $impactJustAbove = $service->classifyTransitImpact(
            temperature: -34.9,
            condition: 'clear',
            snowfallCm: 0,
            precipitationMm: 0,
            visibilityM: 10000,
            windSpeedKmh: 0,
        );
        self::assertNotSame(TransitImpact::SEVERE, $impactJustAbove, 'Just above -35°C should not be severe');

        $impactBelowBoundary = $service->classifyTransitImpact(
            temperature: -36.0,
            condition: 'clear',
            snowfallCm: 0,
            precipitationMm: 0,
            visibilityM: 10000,
            windSpeedKmh: 0,
        );
        self::assertSame(TransitImpact::SEVERE, $impactBelowBoundary, 'Below -35°C should be severe');
    }

    private function createWeatherService(): WeatherService
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $em         = $this->createMock(EntityManagerInterface::class);
        $registry   = $this->createMock(ManagerRegistry::class);

        // Create a stub WeatherObservationRepository using mocked dependencies
        $weatherRepo = new \App\Repository\WeatherObservationRepository($em, $registry);

        return new WeatherService(
            httpClient: $httpClient,
            weatherRepo: $weatherRepo,
            logger: new NullLogger(),
        );
    }
}
