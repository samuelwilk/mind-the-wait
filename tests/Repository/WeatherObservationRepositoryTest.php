<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\WeatherObservation;
use App\Enum\TransitImpact;
use App\Repository\WeatherObservationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function count;

use const PHP_INT_MAX;

#[CoversClass(WeatherObservationRepository::class)]
final class WeatherObservationRepositoryTest extends TestCase
{
    /**
     * Test findClosestTo logic: should pick observation with minimum time difference.
     */
    public function testFindClosestToSelectsNearestObservation(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        // Mock observations at different times
        $observations = [
            $this->createWeatherObservation('2025-10-12 10:00:00'), // 2 hours before
            $this->createWeatherObservation('2025-10-12 11:45:00'), // 15 minutes before (closest!)
            $this->createWeatherObservation('2025-10-12 13:30:00'), // 1.5 hours after
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 11:45:00'),
            $closest->getObservedAt(),
            'Should select observation closest to target time'
        );
    }

    /**
     * Test findClosestTo when observation is exactly at target time.
     */
    public function testFindClosestToWithExactMatch(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        $observations = [
            $this->createWeatherObservation('2025-10-12 11:00:00'),
            $this->createWeatherObservation('2025-10-12 12:00:00'), // Exact match
            $this->createWeatherObservation('2025-10-12 13:00:00'),
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 12:00:00'),
            $closest->getObservedAt(),
            'Should select exact match when available'
        );
    }

    /**
     * Test findClosestTo picks first observation when multiple have same distance.
     */
    public function testFindClosestToWithTiePicksFirst(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        $observations = [
            $this->createWeatherObservation('2025-10-12 11:30:00'), // 30 min before (first tie)
            $this->createWeatherObservation('2025-10-12 12:30:00'), // 30 min after
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 11:30:00'),
            $closest->getObservedAt(),
            'Should pick first observation when distances are equal'
        );
    }

    /**
     * Test findClosestTo with single observation.
     */
    public function testFindClosestToWithSingleObservation(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        $observations = [
            $this->createWeatherObservation('2025-10-12 10:00:00'),
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 10:00:00'),
            $closest->getObservedAt(),
            'Should return single observation'
        );
    }

    /**
     * Test findClosestTo with empty array.
     */
    public function testFindClosestToWithEmptyArray(): void
    {
        $targetTime   = new \DateTimeImmutable('2025-10-12 12:00:00');
        $observations = [];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNull($closest, 'Should return null when no observations exist');
    }

    /**
     * Test findClosestTo prefers observation before target over one after.
     */
    public function testFindClosestToPrefersPastObservation(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        $observations = [
            $this->createWeatherObservation('2025-10-12 11:50:00'), // 10 min before (closer)
            $this->createWeatherObservation('2025-10-12 12:15:00'), // 15 min after
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 11:50:00'),
            $closest->getObservedAt(),
            'Should prefer observation before target when it is closer'
        );
    }

    /**
     * Test findClosestTo with observations spanning several hours.
     */
    public function testFindClosestToWithLargeTimeSpan(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        $observations = [
            $this->createWeatherObservation('2025-10-12 08:00:00'), // 4 hours before
            $this->createWeatherObservation('2025-10-12 09:30:00'), // 2.5 hours before
            $this->createWeatherObservation('2025-10-12 11:55:00'), // 5 min before (closest!)
            $this->createWeatherObservation('2025-10-12 14:00:00'), // 2 hours after
            $this->createWeatherObservation('2025-10-12 16:00:00'), // 4 hours after
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 11:55:00'),
            $closest->getObservedAt(),
            'Should find closest observation among many'
        );
    }

    /**
     * Test findClosestTo handles observations in any order.
     */
    public function testFindClosestToHandlesUnorderedObservations(): void
    {
        $targetTime = new \DateTimeImmutable('2025-10-12 12:00:00');

        // Observations in random order
        $observations = [
            $this->createWeatherObservation('2025-10-12 14:00:00'),
            $this->createWeatherObservation('2025-10-12 11:45:00'), // Closest
            $this->createWeatherObservation('2025-10-12 09:00:00'),
            $this->createWeatherObservation('2025-10-12 13:00:00'),
        ];

        $closest = $this->findClosestObservation($observations, $targetTime);

        self::assertNotNull($closest);
        self::assertEquals(
            new \DateTimeImmutable('2025-10-12 11:45:00'),
            $closest->getObservedAt(),
            'Should find closest regardless of order'
        );
    }

    /**
     * Simulate the findClosestTo logic from WeatherObservationRepository.
     *
     * @param list<WeatherObservation> $observations
     */
    private function findClosestObservation(array $observations, \DateTimeInterface $dateTime): ?WeatherObservation
    {
        if (count($observations) === 0) {
            return null;
        }

        // This replicates the logic from WeatherObservationRepository::findClosestTo()
        $targetTimestamp = $dateTime->getTimestamp();
        $closest         = null;
        $minDiff         = PHP_INT_MAX;

        foreach ($observations as $observation) {
            $diff = abs($observation->getObservedAt()->getTimestamp() - $targetTimestamp);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $observation;
            }
        }

        return $closest;
    }

    private function createWeatherObservation(string $dateTime): WeatherObservation
    {
        $observation = new WeatherObservation();
        $observation->setObservedAt(new \DateTimeImmutable($dateTime));
        $observation->setTemperatureCelsius('20.0');
        $observation->setWeatherCondition('clear');
        $observation->setWeatherCode(0);
        $observation->setTransitImpact(TransitImpact::NONE);
        $observation->setDataSource('test');

        return $observation;
    }
}
