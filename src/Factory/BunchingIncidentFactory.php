<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\BunchingIncident;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BunchingIncident>
 */
final class BunchingIncidentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return BunchingIncident::class;
    }

    protected function defaults(): array
    {
        $detectedAt = self::faker()->dateTimeBetween('-30 days', 'now');

        return [
            'route'              => RouteFactory::new(),
            'stop'               => StopFactory::new(),
            'detectedAt'         => \DateTimeImmutable::createFromMutable($detectedAt),
            'vehicleCount'       => 2,
            'timeWindowSeconds'  => 120,
            'vehicleIds'         => self::faker()->regexify('veh-[0-9]{4},veh-[0-9]{4}'),
            'weatherObservation' => null,
        ];
    }

    public function withWeather(string $condition): self
    {
        return $this->with([
            'weatherObservation' => WeatherObservationFactory::new([
                'weatherCondition' => $condition,
                'observedAt'       => $this->defaults()['detectedAt'],
            ]),
        ]);
    }
}
