<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Trip;
use App\Enum\DirectionEnum;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Trip>
 */
final class TripFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Trip::class;
    }

    protected function defaults(): array
    {
        return [
            'gtfsId'    => self::faker()->unique()->regexify('trip-[0-9]{6}'),
            'headsign'  => self::faker()->words(2, true),
            'direction' => DirectionEnum::Zero,
            'route'     => RouteFactory::new(),
            'city'      => CityFactory::new()->findOrCreate(['slug' => 'test-city']),
        ];
    }
}
