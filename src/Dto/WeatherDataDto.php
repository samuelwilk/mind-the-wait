<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Weather data from Open-Meteo API.
 */
final readonly class WeatherDataDto
{
    public function __construct(
        public \DateTimeImmutable $time,
        public float $temperatureCelsius,
        public ?float $apparentTemperatureCelsius,
        public ?float $precipitationMm,
        public ?float $snowfallCm,
        public ?int $snowDepthCm,
        public int $weatherCode,
        public ?float $visibilityM,
        public ?float $windSpeedKmh,
    ) {
    }

    /**
     * Create from Open-Meteo API current weather response.
     */
    public static function fromOpenMeteoCurrentArray(array $data): self
    {
        return new self(
            time: new \DateTimeImmutable($data['time']),
            temperatureCelsius: (float) $data['temperature_2m'],
            apparentTemperatureCelsius: isset($data['apparent_temperature']) ? (float) $data['apparent_temperature'] : null,
            precipitationMm: isset($data['precipitation']) ? (float) $data['precipitation'] : null,
            snowfallCm: isset($data['snowfall']) ? (float) $data['snowfall'] : null,
            snowDepthCm: isset($data['snow_depth']) ? (int) $data['snow_depth'] : null,
            weatherCode: (int) $data['weather_code'],
            visibilityM: isset($data['visibility']) ? (float) $data['visibility'] : null,
            windSpeedKmh: isset($data['wind_speed_10m']) ? (float) $data['wind_speed_10m'] : null,
        );
    }

    /**
     * Create from Open-Meteo API hourly archive data at specific index.
     */
    public static function fromOpenMeteoHourlyArray(array $hourly, int $index): self
    {
        return new self(
            time: new \DateTimeImmutable($hourly['time'][$index]),
            temperatureCelsius: (float) $hourly['temperature_2m'][$index],
            apparentTemperatureCelsius: isset($hourly['apparent_temperature'][$index]) ? (float) $hourly['apparent_temperature'][$index] : null,
            precipitationMm: isset($hourly['precipitation'][$index]) ? (float) $hourly['precipitation'][$index] : null,
            snowfallCm: isset($hourly['snowfall'][$index]) ? (float) $hourly['snowfall'][$index] : null,
            snowDepthCm: isset($hourly['snow_depth'][$index]) ? (int) $hourly['snow_depth'][$index] : null,
            weatherCode: (int) $hourly['weather_code'][$index],
            visibilityM: isset($hourly['visibility'][$index]) ? (float) $hourly['visibility'][$index] : null,
            windSpeedKmh: isset($hourly['wind_speed_10m'][$index]) ? (float) $hourly['wind_speed_10m'][$index] : null,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'time'                         => $this->time->format('c'),
            'temperature_celsius'          => $this->temperatureCelsius,
            'apparent_temperature_celsius' => $this->apparentTemperatureCelsius,
            'precipitation_mm'             => $this->precipitationMm,
            'snowfall_cm'                  => $this->snowfallCm,
            'snow_depth_cm'                => $this->snowDepthCm,
            'weather_code'                 => $this->weatherCode,
            'visibility_m'                 => $this->visibilityM,
            'wind_speed_kmh'               => $this->windSpeedKmh,
        ];
    }
}
