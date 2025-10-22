<?php

declare(strict_types=1);

namespace App\DataFixtures\Story;

use App\Factory\CityFactory;
use App\Factory\RouteFactory;
use App\Factory\StopFactory;
use Zenstruck\Foundry\Story;

/**
 * Default test city with basic transit infrastructure.
 *
 * Provides a reusable test scenario with:
 * - Test City (test-city)
 * - 3 routes
 * - 5 stops
 */
final class DefaultCityStory extends Story
{
    public function build(): void
    {
        // Create test city (or reuse existing)
        $city = CityFactory::new([
            'name' => 'Test City',
            'slug' => 'test-city',
        ])->findOrCreate(['slug' => 'test-city']);

        $this->addState('city', $city);

        // Create routes for this city
        $route1 = RouteFactory::new([
            'gtfsId'    => 'route-1',
            'shortName' => '1',
            'longName'  => 'Downtown Express',
            'city'      => $city,
        ])->create();

        $route2 = RouteFactory::new([
            'gtfsId'    => 'route-2',
            'shortName' => '2',
            'longName'  => 'University Route',
            'city'      => $city,
        ])->create();

        $route3 = RouteFactory::new([
            'gtfsId'    => 'route-3',
            'shortName' => '3',
            'longName'  => 'Airport Shuttle',
            'city'      => $city,
        ])->create();

        $this->addState('route1', $route1);
        $this->addState('route2', $route2);
        $this->addState('route3', $route3);

        // Create stops
        $stops = StopFactory::new([
            'city' => $city,
        ])->many(5)->create();

        $this->addState('stops', $stops);
    }
}
