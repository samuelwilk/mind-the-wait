<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Stop;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Stop>
 */
final class StopFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Stop::class;
    }

    protected function defaults(): array
    {
        return [
            'gtfsId' => self::faker()->unique()->regexify('stop-[0-9]{4}'),
            'name'   => self::faker()->streetAddress(),
            'lat'    => self::faker()->latitude(51.5, 53.5),  // Saskatoon area
            'long'   => self::faker()->longitude(-107.5, -105.5),
            'city'   => CityFactory::new()->findOrCreate(['slug' => 'test-city']),
        ];
    }
}
