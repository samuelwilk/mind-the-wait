<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\WeatherDataDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeatherDataDto::class)]
final class WeatherDataDtoTest extends TestCase
{
    public function testFromOpenMeteoCurrentArrayWithAllFields(): void
    {
        $data = [
            'time'                 => '2025-10-12T14:30:00',
            'temperature_2m'       => 2.4,
            'apparent_temperature' => -1.2,
            'precipitation'        => 0.5,
            'snowfall'             => 0.2,
            'snow_depth'           => 5,
            'weather_code'         => 71,
            'visibility'           => 8000.0,
            'wind_speed_10m'       => 15.5,
        ];

        $dto = WeatherDataDto::fromOpenMeteoCurrentArray($data);

        self::assertEquals(new \DateTimeImmutable('2025-10-12T14:30:00'), $dto->time);
        self::assertSame(2.4, $dto->temperatureCelsius);
        self::assertSame(-1.2, $dto->apparentTemperatureCelsius);
        self::assertSame(0.5, $dto->precipitationMm);
        self::assertSame(0.2, $dto->snowfallCm);
        self::assertSame(5, $dto->snowDepthCm);
        self::assertSame(71, $dto->weatherCode);
        self::assertSame(8000.0, $dto->visibilityM);
        self::assertSame(15.5, $dto->windSpeedKmh);
    }

    public function testFromOpenMeteoCurrentArrayWithNullableFields(): void
    {
        $data = [
            'time'           => '2025-10-12T14:30:00',
            'temperature_2m' => 20.0,
            'weather_code'   => 0,
        ];

        $dto = WeatherDataDto::fromOpenMeteoCurrentArray($data);

        self::assertSame(20.0, $dto->temperatureCelsius);
        self::assertSame(0, $dto->weatherCode);
        self::assertNull($dto->apparentTemperatureCelsius);
        self::assertNull($dto->precipitationMm);
        self::assertNull($dto->snowfallCm);
        self::assertNull($dto->snowDepthCm);
        self::assertNull($dto->visibilityM);
        self::assertNull($dto->windSpeedKmh);
    }

    public function testFromOpenMeteoCurrentArrayCastsTypesCorrectly(): void
    {
        $data = [
            'time'                 => '2025-10-12T14:30:00',
            'temperature_2m'       => '25',           // String should be cast to float
            'apparent_temperature' => '30',
            'precipitation'        => '5.5',
            'snowfall'             => '1.2',
            'snow_depth'           => '10',               // String should be cast to int
            'weather_code'         => '95',             // String should be cast to int
            'visibility'           => '10000',
            'wind_speed_10m'       => '20.5',
        ];

        $dto = WeatherDataDto::fromOpenMeteoCurrentArray($data);

        self::assertSame(25.0, $dto->temperatureCelsius);
        self::assertSame(30.0, $dto->apparentTemperatureCelsius);
        self::assertSame(5.5, $dto->precipitationMm);
        self::assertSame(1.2, $dto->snowfallCm);
        self::assertSame(10, $dto->snowDepthCm);
        self::assertSame(95, $dto->weatherCode);
        self::assertSame(10000.0, $dto->visibilityM);
        self::assertSame(20.5, $dto->windSpeedKmh);
    }

    public function testFromOpenMeteoHourlyArrayWithAllFields(): void
    {
        $hourly = [
            'time'                 => ['2025-10-12T00:00:00', '2025-10-12T01:00:00', '2025-10-12T02:00:00'],
            'temperature_2m'       => [2.0, 1.5, 1.0],
            'apparent_temperature' => [-2.0, -2.5, -3.0],
            'precipitation'        => [0.1, 0.2, 0.3],
            'snowfall'             => [0.05, 0.1, 0.15],
            'snow_depth'           => [5, 5, 5],
            'weather_code'         => [71, 71, 71],
            'visibility'           => [8000.0, 7500.0, 7000.0],
            'wind_speed_10m'       => [10.0, 12.0, 15.0],
        ];

        // Test middle index
        $dto = WeatherDataDto::fromOpenMeteoHourlyArray($hourly, 1);

        self::assertEquals(new \DateTimeImmutable('2025-10-12T01:00:00'), $dto->time);
        self::assertSame(1.5, $dto->temperatureCelsius);
        self::assertSame(-2.5, $dto->apparentTemperatureCelsius);
        self::assertSame(0.2, $dto->precipitationMm);
        self::assertSame(0.1, $dto->snowfallCm);
        self::assertSame(5, $dto->snowDepthCm);
        self::assertSame(71, $dto->weatherCode);
        self::assertSame(7500.0, $dto->visibilityM);
        self::assertSame(12.0, $dto->windSpeedKmh);
    }

    public function testFromOpenMeteoHourlyArrayWithNullableFields(): void
    {
        $hourly = [
            'time'           => ['2025-10-12T00:00:00', '2025-10-12T01:00:00'],
            'temperature_2m' => [20.0, 21.0],
            'weather_code'   => [0, 1],
        ];

        $dto = WeatherDataDto::fromOpenMeteoHourlyArray($hourly, 0);

        self::assertSame(20.0, $dto->temperatureCelsius);
        self::assertSame(0, $dto->weatherCode);
        self::assertNull($dto->apparentTemperatureCelsius);
        self::assertNull($dto->precipitationMm);
        self::assertNull($dto->snowfallCm);
        self::assertNull($dto->snowDepthCm);
        self::assertNull($dto->visibilityM);
        self::assertNull($dto->windSpeedKmh);
    }

    public function testFromOpenMeteoHourlyArrayAtFirstIndex(): void
    {
        $hourly = [
            'time'           => ['2025-10-12T00:00:00', '2025-10-12T01:00:00', '2025-10-12T02:00:00'],
            'temperature_2m' => [10.0, 11.0, 12.0],
            'weather_code'   => [0, 1, 2],
        ];

        $dto = WeatherDataDto::fromOpenMeteoHourlyArray($hourly, 0);

        self::assertEquals(new \DateTimeImmutable('2025-10-12T00:00:00'), $dto->time);
        self::assertSame(10.0, $dto->temperatureCelsius);
        self::assertSame(0, $dto->weatherCode);
    }

    public function testFromOpenMeteoHourlyArrayAtLastIndex(): void
    {
        $hourly = [
            'time'           => ['2025-10-12T00:00:00', '2025-10-12T01:00:00', '2025-10-12T02:00:00'],
            'temperature_2m' => [10.0, 11.0, 12.0],
            'weather_code'   => [0, 1, 2],
        ];

        $dto = WeatherDataDto::fromOpenMeteoHourlyArray($hourly, 2);

        self::assertEquals(new \DateTimeImmutable('2025-10-12T02:00:00'), $dto->time);
        self::assertSame(12.0, $dto->temperatureCelsius);
        self::assertSame(2, $dto->weatherCode);
    }

    public function testFromOpenMeteoHourlyArrayCastsTypesCorrectly(): void
    {
        $hourly = [
            'time'                 => ['2025-10-12T00:00:00'],
            'temperature_2m'       => ['25'],           // String should be cast to float
            'apparent_temperature' => ['30'],
            'precipitation'        => ['5.5'],
            'snowfall'             => ['1.2'],
            'snow_depth'           => ['10'],               // String should be cast to int
            'weather_code'         => ['95'],             // String should be cast to int
            'visibility'           => ['10000'],
            'wind_speed_10m'       => ['20.5'],
        ];

        $dto = WeatherDataDto::fromOpenMeteoHourlyArray($hourly, 0);

        self::assertSame(25.0, $dto->temperatureCelsius);
        self::assertSame(30.0, $dto->apparentTemperatureCelsius);
        self::assertSame(5.5, $dto->precipitationMm);
        self::assertSame(1.2, $dto->snowfallCm);
        self::assertSame(10, $dto->snowDepthCm);
        self::assertSame(95, $dto->weatherCode);
        self::assertSame(10000.0, $dto->visibilityM);
        self::assertSame(20.5, $dto->windSpeedKmh);
    }

    public function testFromOpenMeteoCurrentArrayWithZeroValues(): void
    {
        $data = [
            'time'                 => '2025-10-12T14:30:00',
            'temperature_2m'       => 0.0,
            'apparent_temperature' => 0.0,
            'precipitation'        => 0.0,
            'snowfall'             => 0.0,
            'snow_depth'           => 0,
            'weather_code'         => 0,
            'visibility'           => 0.0,
            'wind_speed_10m'       => 0.0,
        ];

        $dto = WeatherDataDto::fromOpenMeteoCurrentArray($data);

        // Zero values should NOT be treated as null
        self::assertSame(0.0, $dto->temperatureCelsius);
        self::assertSame(0.0, $dto->apparentTemperatureCelsius);
        self::assertSame(0.0, $dto->precipitationMm);
        self::assertSame(0.0, $dto->snowfallCm);
        self::assertSame(0, $dto->snowDepthCm);
        self::assertSame(0, $dto->weatherCode);
        self::assertSame(0.0, $dto->visibilityM);
        self::assertSame(0.0, $dto->windSpeedKmh);
    }

    public function testWeatherDataDtoIsReadonly(): void
    {
        $data = [
            'time'           => '2025-10-12T14:30:00',
            'temperature_2m' => 20.0,
            'weather_code'   => 0,
        ];

        $dto = WeatherDataDto::fromOpenMeteoCurrentArray($data);

        // Reflection to check if class is readonly
        $reflection = new \ReflectionClass($dto);
        self::assertTrue($reflection->isReadOnly(), 'WeatherDataDto should be readonly');
    }
}
