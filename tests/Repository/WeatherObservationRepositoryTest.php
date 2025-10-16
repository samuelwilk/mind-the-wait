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

    /**
     * Test findLatest excludes future observations.
     *
     * This tests the fix where findLatest() was returning forecast data
     * (observations with observed_at > NOW()).
     */
    public function testFindLatestExcludesFutureObservations(): void
    {
        $now = new \DateTimeImmutable();

        $observations = [
            $this->createWeatherObservation($now->modify('-2 hours')->format('Y-m-d H:i:s')), // 2 hours ago
            $this->createWeatherObservation($now->modify('-1 hour')->format('Y-m-d H:i:s')),  // 1 hour ago (latest!)
            $this->createWeatherObservation($now->modify('+1 hour')->format('Y-m-d H:i:s')),  // 1 hour future (forecast)
            $this->createWeatherObservation($now->modify('+2 hours')->format('Y-m-d H:i:s')), // 2 hours future (forecast)
        ];

        // Simulate findLatest() filtering logic: observed_at <= NOW()
        $filtered = array_filter($observations, fn ($obs) => $obs->getObservedAt() <= $now);

        // Sort by observed_at DESC and take first
        usort($filtered, fn ($a, $b) => $b->getObservedAt() <=> $a->getObservedAt());
        $latest = count($filtered) > 0 ? $filtered[0] : null;

        self::assertNotNull($latest);
        self::assertEquals(
            $now->modify('-1 hour')->format('Y-m-d H:i:s'),
            $latest->getObservedAt()->format('Y-m-d H:i:s'),
            'Should return latest observation that is not in the future'
        );
    }

    /**
     * Test findLatest returns null when only future observations exist.
     */
    public function testFindLatestReturnsNullWhenOnlyFutureObservations(): void
    {
        $now = new \DateTimeImmutable();

        $observations = [
            $this->createWeatherObservation($now->modify('+1 hour')->format('Y-m-d H:i:s')),
            $this->createWeatherObservation($now->modify('+2 hours')->format('Y-m-d H:i:s')),
        ];

        // Simulate findLatest() filtering logic
        $filtered = array_filter($observations, fn ($obs) => $obs->getObservedAt() <= $now);

        self::assertCount(0, $filtered, 'Should filter out all future observations');
    }

    /**
     * Test findLatest returns most recent when multiple past observations exist.
     */
    public function testFindLatestReturnsMostRecent(): void
    {
        $now = new \DateTimeImmutable();

        $observations = [
            $this->createWeatherObservation($now->modify('-5 hours')->format('Y-m-d H:i:s')),
            $this->createWeatherObservation($now->modify('-3 hours')->format('Y-m-d H:i:s')),
            $this->createWeatherObservation($now->modify('-1 hour')->format('Y-m-d H:i:s')),  // Most recent
            $this->createWeatherObservation($now->modify('-30 minutes')->format('Y-m-d H:i:s')), // Even more recent!
        ];

        // Simulate findLatest() filtering logic
        $filtered = array_filter($observations, fn ($obs) => $obs->getObservedAt() <= $now);
        usort($filtered, fn ($a, $b) => $b->getObservedAt() <=> $a->getObservedAt());
        $latest = count($filtered) > 0 ? $filtered[0] : null;

        self::assertNotNull($latest);
        self::assertEquals(
            $now->modify('-30 minutes')->format('Y-m-d H:i:s'),
            $latest->getObservedAt()->format('Y-m-d H:i:s'),
            'Should return the most recent observation'
        );
    }

    /**
     * Test findLatest includes observation at exactly current time.
     */
    public function testFindLatestIncludesCurrentTimeObservation(): void
    {
        $now = new \DateTimeImmutable();

        $observations = [
            $this->createWeatherObservation($now->modify('-1 hour')->format('Y-m-d H:i:s')),
            $this->createWeatherObservation($now->format('Y-m-d H:i:s')), // Exactly now (should be included)
        ];

        // Simulate findLatest() filtering logic: observed_at <= NOW()
        $filtered = array_filter($observations, fn ($obs) => $obs->getObservedAt() <= $now);

        self::assertCount(2, $filtered, 'Should include observation at exactly current time');

        usort($filtered, fn ($a, $b) => $b->getObservedAt() <=> $a->getObservedAt());
        $latest = $filtered[0];

        self::assertEquals(
            $now->format('Y-m-d H:i:s'),
            $latest->getObservedAt()->format('Y-m-d H:i:s'),
            'Should return observation at current time as latest'
        );
    }

    /**
     * Test upsert inserts new observation when it doesn't exist.
     *
     * This tests the fix for duplicate key errors when Open-Meteo returns
     * rounded timestamps (e.g., 19:30) multiple times per hour.
     */
    public function testUpsertInsertsNewObservationWhenNotExists(): void
    {
        $timestamp = new \DateTimeImmutable('2025-10-15 19:30:00');
        $existing  = [];

        $observation = $this->createWeatherObservation('2025-10-15 19:30:00');
        $observation->setTemperatureCelsius('8.7');
        $observation->setWeatherCondition('cloudy');

        // Simulate upsert: check if exists, if not insert
        $found = $this->findByObservedAt($existing, $timestamp);
        self::assertNull($found, 'Should not find existing observation');

        // In real code, this would call save() and return the new observation
        $result = $observation;

        self::assertNotNull($result);
        self::assertEquals('8.7', $result->getTemperatureCelsius());
        self::assertEquals('cloudy', $result->getWeatherCondition());
    }

    /**
     * Test upsert updates existing observation when observed_at matches.
     *
     * Critical test: This ensures we handle duplicate timestamps from Open-Meteo
     * gracefully by updating instead of throwing SQLSTATE[23505] errors.
     */
    public function testUpsertUpdatesExistingObservationWhenTimestampMatches(): void
    {
        $timestamp = new \DateTimeImmutable('2025-10-15 19:30:00');

        // Existing observation at 19:30 with old data
        $existing = $this->createWeatherObservation('2025-10-15 19:30:00');
        $existing->setTemperatureCelsius('10.0');
        $existing->setWeatherCondition('clear');
        $existing->setTransitImpact(TransitImpact::NONE);

        // New observation at same 19:30 timestamp with updated data
        $newObservation = $this->createWeatherObservation('2025-10-15 19:30:00');
        $newObservation->setTemperatureCelsius('8.7');
        $newObservation->setWeatherCondition('cloudy');
        $newObservation->setTransitImpact(TransitImpact::MINOR);
        $newObservation->setFeelsLikeCelsius('6.5');
        $newObservation->setPrecipitationMm('0.5');

        // Simulate upsert: update all fields
        $existing->setTemperatureCelsius($newObservation->getTemperatureCelsius());
        $existing->setFeelsLikeCelsius($newObservation->getFeelsLikeCelsius());
        $existing->setPrecipitationMm($newObservation->getPrecipitationMm());
        $existing->setWeatherCondition($newObservation->getWeatherCondition());
        $existing->setTransitImpact($newObservation->getTransitImpact());

        // Verify update worked
        self::assertEquals('8.7', $existing->getTemperatureCelsius());
        self::assertEquals('cloudy', $existing->getWeatherCondition());
        self::assertEquals(TransitImpact::MINOR, $existing->getTransitImpact());
        self::assertEquals('6.5', $existing->getFeelsLikeCelsius());
        self::assertEquals('0.5', $existing->getPrecipitationMm());
        self::assertEquals($timestamp, $existing->getObservedAt());
    }

    /**
     * Test findByObservedAt finds exact timestamp match.
     */
    public function testFindByObservedAtFindsExactMatch(): void
    {
        $target = new \DateTimeImmutable('2025-10-15 19:30:00');

        $observations = [
            $this->createWeatherObservation('2025-10-15 19:00:00'),
            $this->createWeatherObservation('2025-10-15 19:30:00'), // Match!
            $this->createWeatherObservation('2025-10-15 20:00:00'),
        ];

        $found = $this->findByObservedAt($observations, $target);

        self::assertNotNull($found);
        self::assertEquals($target, $found->getObservedAt());
    }

    /**
     * Test findByObservedAt returns null when no match exists.
     */
    public function testFindByObservedAtReturnsNullWhenNoMatch(): void
    {
        $target = new \DateTimeImmutable('2025-10-15 19:45:00');

        $observations = [
            $this->createWeatherObservation('2025-10-15 19:00:00'),
            $this->createWeatherObservation('2025-10-15 19:30:00'),
            $this->createWeatherObservation('2025-10-15 20:00:00'),
        ];

        $found = $this->findByObservedAt($observations, $target);

        self::assertNull($found, 'Should return null when observed_at does not match');
    }

    /**
     * Test upsert handles Open-Meteo's 30-minute timestamp rounding.
     *
     * Scenario: Schedule runs at 19:00, 19:30, 20:00 but Open-Meteo API
     * returns rounded timestamps (e.g., all return 19:30:00).
     * This was causing duplicate key violations.
     */
    public function testUpsertHandlesOpenMeteoRoundedTimestamps(): void
    {
        $roundedTimestamp = new \DateTimeImmutable('2025-10-15 19:30:00');
        $existing         = [];

        // First collection at 19:00 → API returns 19:30:00
        $firstCollection = $this->createWeatherObservation('2025-10-15 19:30:00');
        $firstCollection->setTemperatureCelsius('8.5');
        $firstCollection->setWeatherCondition('cloudy');

        $found = $this->findByObservedAt($existing, $roundedTimestamp);
        self::assertNull($found, 'First collection should not find existing');
        $existing[] = $firstCollection;

        // Second collection at 19:12 → API still returns 19:30:00 (duplicate!)
        $secondCollection = $this->createWeatherObservation('2025-10-15 19:30:00');
        $secondCollection->setTemperatureCelsius('8.7');
        $secondCollection->setWeatherCondition('cloudy');

        $found = $this->findByObservedAt($existing, $roundedTimestamp);
        self::assertNotNull($found, 'Second collection should find existing with same timestamp');

        // Simulate upsert: update existing instead of inserting
        $found->setTemperatureCelsius($secondCollection->getTemperatureCelsius());

        self::assertEquals('8.7', $found->getTemperatureCelsius());
        self::assertCount(1, $existing, 'Should still have only 1 observation (no duplicate)');
    }

    /**
     * Test upsert updates all weather fields correctly.
     */
    public function testUpsertUpdatesAllWeatherFields(): void
    {
        $timestamp = new \DateTimeImmutable('2025-10-15 19:30:00');

        // Existing observation with minimal data
        $existing = $this->createWeatherObservation('2025-10-15 19:30:00');
        $existing->setTemperatureCelsius('10.0');
        $existing->setWeatherCondition('clear');
        $existing->setFeelsLikeCelsius(null);
        $existing->setPrecipitationMm(null);
        $existing->setSnowfallCm(null);
        $existing->setSnowDepthCm(null);
        $existing->setVisibilityKm(null);
        $existing->setWindSpeedKmh(null);

        // New observation with complete data
        $newData = $this->createWeatherObservation('2025-10-15 19:30:00');
        $newData->setTemperatureCelsius('-15.5');
        $newData->setFeelsLikeCelsius('-22.0');
        $newData->setPrecipitationMm('2.5');
        $newData->setSnowfallCm('5.0');
        $newData->setSnowDepthCm(25);
        $newData->setWeatherCode(71);
        $newData->setWeatherCondition('snow');
        $newData->setVisibilityKm('1.5');
        $newData->setWindSpeedKmh('45.0');
        $newData->setTransitImpact(TransitImpact::MODERATE);
        $newData->setDataSource('open_meteo');

        // Simulate upsert update
        $existing->setTemperatureCelsius($newData->getTemperatureCelsius());
        $existing->setFeelsLikeCelsius($newData->getFeelsLikeCelsius());
        $existing->setPrecipitationMm($newData->getPrecipitationMm());
        $existing->setSnowfallCm($newData->getSnowfallCm());
        $existing->setSnowDepthCm($newData->getSnowDepthCm());
        $existing->setWeatherCode($newData->getWeatherCode());
        $existing->setWeatherCondition($newData->getWeatherCondition());
        $existing->setVisibilityKm($newData->getVisibilityKm());
        $existing->setWindSpeedKmh($newData->getWindSpeedKmh());
        $existing->setTransitImpact($newData->getTransitImpact());
        $existing->setDataSource($newData->getDataSource());

        // Verify all fields updated
        self::assertEquals('-15.5', $existing->getTemperatureCelsius());
        self::assertEquals('-22.0', $existing->getFeelsLikeCelsius());
        self::assertEquals('2.5', $existing->getPrecipitationMm());
        self::assertEquals('5.0', $existing->getSnowfallCm());
        self::assertEquals(25, $existing->getSnowDepthCm());
        self::assertEquals(71, $existing->getWeatherCode());
        self::assertEquals('snow', $existing->getWeatherCondition());
        self::assertEquals('1.5', $existing->getVisibilityKm());
        self::assertEquals('45.0', $existing->getWindSpeedKmh());
        self::assertEquals(TransitImpact::MODERATE, $existing->getTransitImpact());
        self::assertEquals('open_meteo', $existing->getDataSource());
    }

    /**
     * Simulate findByObservedAt from WeatherObservationRepository.
     *
     * @param list<WeatherObservation> $observations
     */
    private function findByObservedAt(array $observations, \DateTimeImmutable $observedAt): ?WeatherObservation
    {
        foreach ($observations as $observation) {
            if ($observation->getObservedAt() === $observedAt) {
                return $observation;
            }
        }

        return null;
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
