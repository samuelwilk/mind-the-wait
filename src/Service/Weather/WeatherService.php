<?php

declare(strict_types=1);

namespace App\Service\Weather;

use App\Dto\WeatherDataDto;
use App\Dto\WeatherObservationDto;
use App\Entity\WeatherObservation;
use App\Enum\TransitImpact;
use App\Repository\WeatherObservationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

/**
 * Fetches weather data from Open-Meteo API and classifies transit impact.
 *
 * Uses Open-Meteo (free, no API key required) for current + historical weather.
 */
final readonly class WeatherService
{
    private const SASKATOON_LAT = 52.1324;
    private const SASKATOON_LON = -106.6607;

    // Open-Meteo API endpoints
    private const API_CURRENT = 'https://api.open-meteo.com/v1/forecast';
    private const API_ARCHIVE = 'https://archive-api.open-meteo.com/v1/archive';

    public function __construct(
        private HttpClientInterface $httpClient,
        private WeatherObservationRepository $weatherRepo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch current weather from Open-Meteo and save to database.
     */
    public function fetchAndStoreCurrent(): ?WeatherObservation
    {
        try {
            $response = $this->httpClient->request('GET', self::API_CURRENT, [
                'query' => [
                    'latitude'  => self::SASKATOON_LAT,
                    'longitude' => self::SASKATOON_LON,
                    'current'   => 'temperature_2m,apparent_temperature,precipitation,snowfall,snow_depth,weather_code,visibility,wind_speed_10m',
                    'timezone'  => 'America/Regina',
                ],
            ]);

            $data        = $response->toArray();
            $weatherData = WeatherDataDto::fromOpenMeteoCurrentArray($data['current']);

            // Map weather code to human-readable condition
            $condition = $this->mapWeatherCode($weatherData->weatherCode);

            // Classify transit impact
            $impact = $this->classifyTransitImpact(
                temperature: $weatherData->temperatureCelsius,
                condition: $condition,
                snowfallCm: $weatherData->snowfallCm           ?? 0,
                precipitationMm: $weatherData->precipitationMm ?? 0,
                visibilityM: $weatherData->visibilityM         ?? 10000,
                windSpeedKmh: $weatherData->windSpeedKmh       ?? 0
            );

            // Create DTO and delegate persistence to repository
            $dto = new WeatherObservationDto(
                observedAt: $weatherData->time,
                temperatureCelsius: (string) $weatherData->temperatureCelsius,
                feelsLikeCelsius: $weatherData->apparentTemperatureCelsius !== null ? (string) $weatherData->apparentTemperatureCelsius : null,
                precipitationMm: $weatherData->precipitationMm             !== null ? (string) $weatherData->precipitationMm : null,
                snowfallCm: $weatherData->snowfallCm                       !== null ? (string) $weatherData->snowfallCm : null,
                snowDepthCm: $weatherData->snowDepthCm,
                weatherCode: $weatherData->weatherCode,
                weatherCondition: $condition,
                visibilityKm: $weatherData->visibilityM  !== null ? (string) ($weatherData->visibilityM / 1000) : null,
                windSpeedKmh: $weatherData->windSpeedKmh !== null ? (string) $weatherData->windSpeedKmh : null,
                transitImpact: $impact,
                dataSource: 'open_meteo',
            );

            $observation = $this->weatherRepo->upsertFromDto($dto);

            $this->logger->info('Weather observation collected', [
                'observed_at' => $observation->getObservedAt()->format('Y-m-d H:i'),
                'temperature' => $observation->getTemperatureCelsius(),
                'condition'   => $condition,
                'impact'      => $impact->value,
            ]);

            return $observation;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch weather', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Backfill historical weather data for a date range.
     *
     * @return array{success: int, failed: int}
     */
    public function backfillHistoricalWeather(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $success = 0;
        $failed  = 0;

        try {
            $response = $this->httpClient->request('GET', self::API_ARCHIVE, [
                'query' => [
                    'latitude'   => self::SASKATOON_LAT,
                    'longitude'  => self::SASKATOON_LON,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'hourly'     => 'temperature_2m,apparent_temperature,precipitation,snowfall,snow_depth,weather_code,visibility,wind_speed_10m',
                    'timezone'   => 'America/Regina',
                ],
            ]);

            $data   = $response->toArray();
            $hourly = $data['hourly'];

            foreach ($hourly['time'] as $index => $time) {
                try {
                    $weatherData = WeatherDataDto::fromOpenMeteoHourlyArray($hourly, $index);
                    $condition   = $this->mapWeatherCode($weatherData->weatherCode);

                    $impact = $this->classifyTransitImpact(
                        temperature: $weatherData->temperatureCelsius,
                        condition: $condition,
                        snowfallCm: $weatherData->snowfallCm           ?? 0,
                        precipitationMm: $weatherData->precipitationMm ?? 0,
                        visibilityM: $weatherData->visibilityM         ?? 10000,
                        windSpeedKmh: $weatherData->windSpeedKmh       ?? 0
                    );

                    // Create DTO and delegate persistence to repository
                    $dto = new WeatherObservationDto(
                        observedAt: $weatherData->time,
                        temperatureCelsius: (string) $weatherData->temperatureCelsius,
                        feelsLikeCelsius: $weatherData->apparentTemperatureCelsius !== null ? (string) $weatherData->apparentTemperatureCelsius : null,
                        precipitationMm: $weatherData->precipitationMm             !== null ? (string) $weatherData->precipitationMm : null,
                        snowfallCm: $weatherData->snowfallCm                       !== null ? (string) $weatherData->snowfallCm : null,
                        snowDepthCm: $weatherData->snowDepthCm,
                        weatherCode: $weatherData->weatherCode,
                        weatherCondition: $condition,
                        visibilityKm: $weatherData->visibilityM  !== null ? (string) ($weatherData->visibilityM / 1000) : null,
                        windSpeedKmh: $weatherData->windSpeedKmh !== null ? (string) $weatherData->windSpeedKmh : null,
                        transitImpact: $impact,
                        dataSource: 'open_meteo',
                    );

                    $this->weatherRepo->upsertFromDto($dto);
                    ++$success;
                } catch (\Exception $e) {
                    ++$failed;
                    $this->logger->warning('Failed to backfill weather observation', [
                        'time'  => $time,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info(sprintf('Backfilled %d weather observations (%d failed)', $success, $failed));
        } catch (\Exception $e) {
            $this->logger->error('Failed to backfill weather', [
                'start' => $startDate->format('Y-m-d'),
                'end'   => $endDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Classify transit impact based on weather conditions (year-round).
     *
     * Based on comprehensive analysis from docs/features/weather-integration.md
     */
    public function classifyTransitImpact(
        float $temperature,
        string $condition,
        float $snowfallCm,
        float $precipitationMm,
        float $visibilityM,
        float $windSpeedKmh,
    ): TransitImpact {
        $visibilityKm = $visibilityM / 1000;

        // SEVERE: Dangerous conditions, major delays expected
        // Winter: Extreme cold, blizzard conditions
        if ($temperature < -35 || $snowfallCm > 15 || $visibilityKm < 0.5) {
            return TransitImpact::SEVERE;
        }

        // Summer: Severe thunderstorms, flooding, extreme heat
        if ($condition === 'thunderstorm'
            || $precipitationMm > 25  // Heavy rain (flooding risk)
            || $temperature     > 35       // Extreme heat (mechanical failures)
            || $windSpeedKmh    > 70) {      // High winds (safety hazards)
            return TransitImpact::SEVERE;
        }

        // MODERATE: Noticeable impact, some delays
        // Winter: Heavy snow, very cold
        if ($temperature < -25 || $snowfallCm > 5) {
            return TransitImpact::MODERATE;
        }

        // Year-round: Heavy rain, poor visibility, strong winds
        if ($precipitationMm > 10           // Heavy rain
            || $visibilityKm < 2               // Poor visibility
            || $windSpeedKmh > 50              // Strong winds
            || $condition === 'showers'        // Heavy rain showers
            || ($condition === 'rain' && $precipitationMm > 5)) {
            return TransitImpact::MODERATE;
        }

        // MINOR: Slight impact, minimal delays
        // Winter: Cold, light snow
        if ($temperature < -15 || ($condition === 'snow' && $snowfallCm <= 2)) {
            return TransitImpact::MINOR;
        }

        // Summer: Light rain, moderate heat
        if ($condition === 'rain'
            || $precipitationMm > 2
            || ($temperature > 28 && $temperature <= 35)  // Hot but not extreme
            || $windSpeedKmh > 30) {                          // Moderate winds
            return TransitImpact::MINOR;
        }

        // NONE: Clear conditions, no significant impact
        return TransitImpact::NONE;
    }

    /**
     * Map Open-Meteo weather code to human-readable condition.
     *
     * Codes: https://open-meteo.com/en/docs
     */
    private function mapWeatherCode(int $code): string
    {
        return match (true) {
            $code === 0                => 'clear',
            $code >= 1  && $code <= 3  => 'cloudy',
            $code >= 51 && $code <= 67 => 'rain',
            $code >= 71 && $code <= 77 => 'snow',
            $code >= 80 && $code <= 86 => 'showers',
            $code >= 95 && $code <= 99 => 'thunderstorm',
            default                    => 'unknown',
        };
    }
}
