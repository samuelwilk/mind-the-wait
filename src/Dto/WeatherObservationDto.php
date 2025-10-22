<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TransitImpact;

/**
 * Immutable DTO for weather observation data.
 *
 * Used to pass weather data from service layer to repository layer,
 * separating API parsing logic from entity construction.
 */
final readonly class WeatherObservationDto
{
    public function __construct(
        public \DateTimeImmutable $observedAt,
        public string $temperatureCelsius,
        public ?string $feelsLikeCelsius,
        public ?string $precipitationMm,
        public ?string $snowfallCm,
        public ?int $snowDepthCm,
        public ?int $weatherCode,
        public string $weatherCondition,
        public ?string $visibilityKm,
        public ?string $windSpeedKmh,
        public TransitImpact $transitImpact,
        public string $dataSource,
    ) {
    }
}
