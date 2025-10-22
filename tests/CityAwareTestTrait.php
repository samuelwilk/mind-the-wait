<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provides test city setup for multi-city support.
 *
 * Tests that create Route, Trip, or Stop entities need a City association.
 * This trait provides a shared createTestCity() method.
 */
trait CityAwareTestTrait
{
    private ?City $testCity = null;

    /**
     * Get or create a test city for entity associations.
     *
     * Returns a cached instance to avoid duplicate city creation.
     * Call this in setUp() or before creating routes/trips/stops.
     *
     * @param EntityManagerInterface $em Entity manager instance
     *
     * @return City Test city (slug: 'test-city')
     */
    protected function getTestCity(EntityManagerInterface $em): City
    {
        if ($this->testCity !== null) {
            return $this->testCity;
        }

        // Check if test city already exists
        $city = $em->getRepository(City::class)->findOneBy(['slug' => 'test-city']);

        if ($city === null) {
            $city = new City();
            $city->setName('Test City');
            $city->setSlug('test-city');
            $city->setCountry('CA');
            $city->setCenterLat('52.1324');
            $city->setCenterLon('-106.6689');
            $city->setActive(true);
            $em->persist($city);
            $em->flush();
        }

        $this->testCity = $city;

        return $city;
    }
}
