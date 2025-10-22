<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\RoutePerformanceDaily;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RoutePerformanceDaily>
 */
final class RoutePerformanceDailyFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RoutePerformanceDaily::class;
    }

    protected function defaults(): array
    {
        return [
            'route'                 => RouteFactory::new(),
            'date'                  => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-30 days', 'now')),
            'totalPredictions'      => self::faker()->numberBetween(50, 500),
            'highConfidenceCount'   => self::faker()->numberBetween(30, 400),
            'mediumConfidenceCount' => self::faker()->numberBetween(10, 80),
            'lowConfidenceCount'    => self::faker()->numberBetween(0, 20),
            'avgDelaySec'           => self::faker()->numberBetween(-300, 600),
            'medianDelaySec'        => self::faker()->numberBetween(-200, 400),
            'onTimePercentage'      => (string) self::faker()->randomFloat(2, 50, 95),
            'latePercentage'        => (string) self::faker()->randomFloat(2, 0, 40),
            'earlyPercentage'       => (string) self::faker()->randomFloat(2, 0, 10),
            'bunchingIncidents'     => self::faker()->numberBetween(0, 10),
        ];
    }
}
