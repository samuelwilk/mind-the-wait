<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Route;
use App\Enum\RouteTypeEnum;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Route>
 */
final class RouteFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Route::class;
    }

    protected function defaults(): array
    {
        return [
            'gtfsId'    => self::faker()->unique()->regexify('route-[0-9]{4}'),
            'shortName' => self::faker()->numberBetween(1, 99),
            'longName'  => self::faker()->words(3, true),
            'colour'    => 'FF0000',
            'routeType' => RouteTypeEnum::Bus,
            'city'      => CityFactory::new()->findOrCreate(['slug' => 'test-city']),
        ];
    }
}
