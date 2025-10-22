<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\City;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<City>
 */
final class CityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return City::class;
    }

    protected function defaults(): array
    {
        return [
            'name'      => 'Test City',
            'slug'      => 'test-city',
            'country'   => 'CA',
            'centerLat' => '52.1324',
            'centerLon' => '-106.6689',
            'zoomLevel' => 12,
            'active'    => true,
        ];
    }

    public static function saskatoon(): self
    {
        return self::new([
            'name'      => 'Saskatoon',
            'slug'      => 'saskatoon',
            'centerLat' => '52.1324',
            'centerLon' => '-106.6689',
        ]);
    }
}
