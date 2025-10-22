<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\WeatherObservation;
use App\Enum\TransitImpact;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<WeatherObservation>
 */
final class WeatherObservationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return WeatherObservation::class;
    }

    protected function defaults(): array
    {
        return [
            'observedAt'         => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-7 days', 'now')),
            'temperatureCelsius' => (string) self::faker()->randomFloat(1, -30, 35),
            'feelsLikeCelsius'   => (string) self::faker()->randomFloat(1, -35, 30),
            'weatherCode'        => self::faker()->numberBetween(0, 99),
            'weatherCondition'   => self::faker()->randomElement(['Clear', 'Overcast', 'Rain', 'Snow', 'Fog']),
            'transitImpact'      => TransitImpact::NONE,
            'precipitationMm'    => (string) self::faker()->randomFloat(1, 0, 20),
            'snowfallCm'         => (string) self::faker()->randomFloat(1, 0, 15),
            'snowDepthCm'        => self::faker()->numberBetween(0, 50),
            'visibilityKm'       => (string) self::faker()->randomFloat(1, 0.1, 10),
            'windSpeedKmh'       => (string) self::faker()->randomFloat(1, 0, 60),
            'dataSource'         => 'test',
        ];
    }
}
