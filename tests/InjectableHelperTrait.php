<?php

declare(strict_types=1);

namespace App\Tests;

/**
 * Helper trait for getting services from the Symfony container in integration tests.
 *
 * Usage: Extend Symfony\Bundle\FrameworkBundle\Test\KernelTestCase and use this trait.
 */
trait InjectableHelperTrait
{
    /**
     * Get a service from the container.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws \Exception
     */
    protected function getInjectable($class)
    {
        // @phpstan-ignore-next-line
        $injectable = static::getContainer()->get($class);
        if (!$injectable instanceof $class) {
            throw new \Exception($class.' not found in container');
        }

        return $injectable;
    }
}
