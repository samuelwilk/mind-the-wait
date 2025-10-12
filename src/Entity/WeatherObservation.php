<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Timestampable;
use App\Enum\TransitImpact;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores weather observations for Saskatoon.
 *
 * Collected hourly to enable weather-context analysis:
 * - "Route 27 is 78% on-time in clear weather, 52% in snow"
 * - "System performance drops 20% during snowfall"
 */
#[ORM\Entity(repositoryClass: \App\Repository\WeatherObservationRepository::class)]
#[ORM\Table(name: 'weather_observation')]
#[ORM\UniqueConstraint(name: 'observed_at_unique', columns: ['observed_at'])]
#[ORM\Index(columns: ['observed_at'], name: 'idx_weather_observed_at')]
#[ORM\Index(columns: ['transit_impact', 'observed_at'], name: 'idx_weather_impact_observed_at')]
#[ORM\HasLifecycleCallbacks]
class WeatherObservation
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $observedAt;

    /**
     * Temperature in Celsius.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1)]
    private string $temperatureCelsius;

    /**
     * "Feels like" temperature (wind chill/humidex).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1, nullable: true)]
    private ?string $feelsLikeCelsius = null;

    /**
     * Precipitation in millimeters (rain).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, nullable: true)]
    private ?string $precipitationMm = null;

    /**
     * Snowfall in centimeters.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1, nullable: true)]
    private ?string $snowfallCm = null;

    /**
     * Snow depth on ground in centimeters.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $snowDepthCm = null;

    /**
     * Weather code from data provider (e.g., Open-Meteo codes).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $weatherCode = null;

    /**
     * Human-readable weather condition: 'clear', 'cloudy', 'rain', 'snow', 'thunderstorm'.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $weatherCondition;

    /**
     * Visibility in kilometers.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $visibilityKm = null;

    /**
     * Wind speed in km/h.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 1, nullable: true)]
    private ?string $windSpeedKmh = null;

    /**
     * Transit impact severity classification.
     */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: TransitImpact::class)]
    private TransitImpact $transitImpact;

    /**
     * Data source identifier: 'open_meteo', 'environment_canada'.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $dataSource;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObservedAt(): \DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function setObservedAt(\DateTimeImmutable $observedAt): self
    {
        $this->observedAt = $observedAt;

        return $this;
    }

    public function getTemperatureCelsius(): string
    {
        return $this->temperatureCelsius;
    }

    public function setTemperatureCelsius(string $temperatureCelsius): self
    {
        $this->temperatureCelsius = $temperatureCelsius;

        return $this;
    }

    public function getFeelsLikeCelsius(): ?string
    {
        return $this->feelsLikeCelsius;
    }

    public function setFeelsLikeCelsius(?string $feelsLikeCelsius): self
    {
        $this->feelsLikeCelsius = $feelsLikeCelsius;

        return $this;
    }

    public function getPrecipitationMm(): ?string
    {
        return $this->precipitationMm;
    }

    public function setPrecipitationMm(?string $precipitationMm): self
    {
        $this->precipitationMm = $precipitationMm;

        return $this;
    }

    public function getSnowfallCm(): ?string
    {
        return $this->snowfallCm;
    }

    public function setSnowfallCm(?string $snowfallCm): self
    {
        $this->snowfallCm = $snowfallCm;

        return $this;
    }

    public function getSnowDepthCm(): ?int
    {
        return $this->snowDepthCm;
    }

    public function setSnowDepthCm(?int $snowDepthCm): self
    {
        $this->snowDepthCm = $snowDepthCm;

        return $this;
    }

    public function getWeatherCode(): ?int
    {
        return $this->weatherCode;
    }

    public function setWeatherCode(?int $weatherCode): self
    {
        $this->weatherCode = $weatherCode;

        return $this;
    }

    public function getWeatherCondition(): string
    {
        return $this->weatherCondition;
    }

    public function setWeatherCondition(string $weatherCondition): self
    {
        $this->weatherCondition = $weatherCondition;

        return $this;
    }

    public function getVisibilityKm(): ?string
    {
        return $this->visibilityKm;
    }

    public function setVisibilityKm(?string $visibilityKm): self
    {
        $this->visibilityKm = $visibilityKm;

        return $this;
    }

    public function getWindSpeedKmh(): ?string
    {
        return $this->windSpeedKmh;
    }

    public function setWindSpeedKmh(?string $windSpeedKmh): self
    {
        $this->windSpeedKmh = $windSpeedKmh;

        return $this;
    }

    public function getTransitImpact(): TransitImpact
    {
        return $this->transitImpact;
    }

    public function setTransitImpact(TransitImpact $transitImpact): self
    {
        $this->transitImpact = $transitImpact;

        return $this;
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function setDataSource(string $dataSource): self
    {
        $this->dataSource = $dataSource;

        return $this;
    }
}
