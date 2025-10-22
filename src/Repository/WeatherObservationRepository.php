<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\WeatherObservationDto;
use App\Entity\WeatherObservation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use function count;
use function sprintf;

use const PHP_INT_MAX;

/**
 * @extends BaseRepository<WeatherObservation>
 */
final class WeatherObservationRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em, ManagerRegistry $registry)
    {
        parent::__construct($em, $registry, WeatherObservation::class);
    }

    /**
     * Find most recent weather observation (excludes future forecasts).
     */
    public function findLatest(): ?WeatherObservation
    {
        return $this->createQueryBuilder('w')
            ->where('w.observedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('w.observedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find weather observation closest to a specific date/time.
     */
    public function findClosestTo(\DateTimeInterface $dateTime): ?WeatherObservation
    {
        // Find all observations within ±4 hours and pick closest in PHP
        $start = (clone $dateTime)->modify('-4 hours');
        $end   = (clone $dateTime)->modify('+4 hours');

        $observations = $this->createQueryBuilder('w')
            ->where('w.observedAt >= :start')
            ->andWhere('w.observedAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        if (count($observations) === 0) {
            return null;
        }

        // Find observation with minimum time difference
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
     * Find observations within a date range.
     *
     * @return list<WeatherObservation>
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.observedAt >= :start')
            ->andWhere('w.observedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('w.observedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get count of observations by transit impact level.
     *
     * @return array<string, int>
     */
    public function countByImpact(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $results = $this->createQueryBuilder('w')
            ->select('w.transitImpact as impact', 'COUNT(w.id) as count')
            ->where('w.observedAt >= :start')
            ->andWhere('w.observedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('w.transitImpact')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['impact']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get average temperature for a date range.
     */
    public function getAverageTemperature(\DateTimeInterface $start, \DateTimeInterface $end): ?float
    {
        $result = $this->createQueryBuilder('w')
            ->select('AVG(w.temperatureCelsius) as avg_temp')
            ->where('w.observedAt >= :start')
            ->andWhere('w.observedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : null;
    }

    /**
     * Find existing observation for a given timestamp (for idempotent upserts).
     */
    public function findByObservedAt(\DateTimeImmutable $observedAt): ?WeatherObservation
    {
        return $this->createQueryBuilder('w')
            ->where('w.observedAt = :observedAt')
            ->setParameter('observedAt', $observedAt)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Save or update weather observation (idempotent upsert).
     *
     * If observation with same observed_at exists, update it. Otherwise insert new.
     */
    public function upsert(WeatherObservation $observation): WeatherObservation
    {
        $existing = $this->findByObservedAt($observation->getObservedAt());

        if ($existing !== null) {
            // Update existing observation
            $existing->setTemperatureCelsius($observation->getTemperatureCelsius());
            $existing->setFeelsLikeCelsius($observation->getFeelsLikeCelsius());
            $existing->setPrecipitationMm($observation->getPrecipitationMm());
            $existing->setSnowfallCm($observation->getSnowfallCm());
            $existing->setSnowDepthCm($observation->getSnowDepthCm());
            $existing->setWeatherCode($observation->getWeatherCode());
            $existing->setWeatherCondition($observation->getWeatherCondition());
            $existing->setVisibilityKm($observation->getVisibilityKm());
            $existing->setWindSpeedKmh($observation->getWindSpeedKmh());
            $existing->setTransitImpact($observation->getTransitImpact());
            $existing->setDataSource($observation->getDataSource());

            $this->save($existing, flush: true);

            return $existing;
        }

        // Insert new observation
        $this->save($observation, flush: true);

        return $observation;
    }

    /**
     * Create or update weather observation from DTO.
     *
     * Handles entity construction and persistence, separating service layer from entity details.
     * If observation with same observed_at exists, updates it. Otherwise creates new.
     */
    public function upsertFromDto(WeatherObservationDto $dto): WeatherObservation
    {
        $existing = $this->findByObservedAt($dto->observedAt);

        if ($existing !== null) {
            // Update existing observation
            $this->hydrateEntityFromDto($existing, $dto);
            $this->save($existing, flush: true);

            return $existing;
        }

        // Create new observation
        $observation = new WeatherObservation();
        $this->hydrateEntityFromDto($observation, $dto);
        $this->save($observation, flush: true);

        return $observation;
    }

    /**
     * Hydrate entity from DTO (shared logic for create/update).
     */
    private function hydrateEntityFromDto(WeatherObservation $entity, WeatherObservationDto $dto): void
    {
        $entity->setObservedAt($dto->observedAt);
        $entity->setTemperatureCelsius($dto->temperatureCelsius);
        $entity->setFeelsLikeCelsius($dto->feelsLikeCelsius);
        $entity->setPrecipitationMm($dto->precipitationMm);
        $entity->setSnowfallCm($dto->snowfallCm);
        $entity->setSnowDepthCm($dto->snowDepthCm);
        $entity->setWeatherCode($dto->weatherCode);
        $entity->setWeatherCondition($dto->weatherCondition);
        $entity->setVisibilityKm($dto->visibilityKm);
        $entity->setWindSpeedKmh($dto->windSpeedKmh);
        $entity->setTransitImpact($dto->transitImpact);
        $entity->setDataSource($dto->dataSource);
    }

    /**
     * Delete weather observations older than specified number of days.
     *
     * @return int Number of deleted rows
     */
    public function deleteOlderThan(int $days): int
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->createQueryBuilder('w')
            ->delete()
            ->where('w.observedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
