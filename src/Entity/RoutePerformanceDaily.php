<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Timestampable;
use App\Enum\ScheduleRealismGrade;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores daily aggregate performance metrics for routes.
 *
 * Computed from ArrivalLog entries by a daily collection command.
 * Powers dashboard features like "Route 27 was late 80% last week".
 */
#[ORM\Entity(repositoryClass: \App\Repository\RoutePerformanceDailyRepository::class)]
#[ORM\Table(name: 'route_performance_daily')]
#[ORM\UniqueConstraint(name: 'route_date_unique', columns: ['route_id', 'date'])]
#[ORM\Index(columns: ['date'], name: 'idx_route_performance_date')]
#[ORM\HasLifecycleCallbacks]
class RoutePerformanceDaily
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalPredictions = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $highConfidenceCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $mediumConfidenceCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $lowConfidenceCount = 0;

    /**
     * Average delay in seconds (negative = early, positive = late).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $avgDelaySec = null;

    /**
     * Median delay in seconds (more robust to outliers than average).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $medianDelaySec = null;

    /**
     * Percentage of arrivals that were on-time (within ±3 minutes).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $onTimePercentage = null;

    /**
     * Percentage of arrivals that were late (>3 minutes).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $latePercentage = null;

    /**
     * Percentage of arrivals that were early (>3 minutes).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $earlyPercentage = null;

    /**
     * Number of bunching incidents detected (future feature).
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $bunchingIncidents = 0;

    /**
     * Schedule realism ratio (actual time / scheduled time).
     *
     * - < 1.0 = over-scheduled (buses finish early)
     * - 1.0 = perfectly scheduled
     * - > 1.0 = under-scheduled (buses run late)
     *
     * Null if insufficient data (< 5 trip instances).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
    private ?string $scheduleRealismRatio = null;

    /**
     * Representative weather observation for this day (nullable for historical data).
     */
    #[ORM\ManyToOne(targetEntity: WeatherObservation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WeatherObservation $weatherObservation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function setRoute(Route $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getTotalPredictions(): int
    {
        return $this->totalPredictions;
    }

    public function setTotalPredictions(int $totalPredictions): self
    {
        $this->totalPredictions = $totalPredictions;

        return $this;
    }

    public function getHighConfidenceCount(): int
    {
        return $this->highConfidenceCount;
    }

    public function setHighConfidenceCount(int $highConfidenceCount): self
    {
        $this->highConfidenceCount = $highConfidenceCount;

        return $this;
    }

    public function getMediumConfidenceCount(): int
    {
        return $this->mediumConfidenceCount;
    }

    public function setMediumConfidenceCount(int $mediumConfidenceCount): self
    {
        $this->mediumConfidenceCount = $mediumConfidenceCount;

        return $this;
    }

    public function getLowConfidenceCount(): int
    {
        return $this->lowConfidenceCount;
    }

    public function setLowConfidenceCount(int $lowConfidenceCount): self
    {
        $this->lowConfidenceCount = $lowConfidenceCount;

        return $this;
    }

    public function getAvgDelaySec(): ?int
    {
        return $this->avgDelaySec;
    }

    public function setAvgDelaySec(?int $avgDelaySec): self
    {
        $this->avgDelaySec = $avgDelaySec;

        return $this;
    }

    public function getMedianDelaySec(): ?int
    {
        return $this->medianDelaySec;
    }

    public function setMedianDelaySec(?int $medianDelaySec): self
    {
        $this->medianDelaySec = $medianDelaySec;

        return $this;
    }

    public function getOnTimePercentage(): ?string
    {
        return $this->onTimePercentage;
    }

    public function setOnTimePercentage(?string $onTimePercentage): self
    {
        $this->onTimePercentage = $onTimePercentage;

        return $this;
    }

    public function getLatePercentage(): ?string
    {
        return $this->latePercentage;
    }

    public function setLatePercentage(?string $latePercentage): self
    {
        $this->latePercentage = $latePercentage;

        return $this;
    }

    public function getEarlyPercentage(): ?string
    {
        return $this->earlyPercentage;
    }

    public function setEarlyPercentage(?string $earlyPercentage): self
    {
        $this->earlyPercentage = $earlyPercentage;

        return $this;
    }

    public function getBunchingIncidents(): int
    {
        return $this->bunchingIncidents;
    }

    public function setBunchingIncidents(int $bunchingIncidents): self
    {
        $this->bunchingIncidents = $bunchingIncidents;

        return $this;
    }

    public function getWeatherObservation(): ?WeatherObservation
    {
        return $this->weatherObservation;
    }

    public function setWeatherObservation(?WeatherObservation $weatherObservation): self
    {
        $this->weatherObservation = $weatherObservation;

        return $this;
    }

    public function getScheduleRealismRatio(): ?string
    {
        return $this->scheduleRealismRatio;
    }

    public function setScheduleRealismRatio(?float $scheduleRealismRatio): self
    {
        $this->scheduleRealismRatio = $scheduleRealismRatio !== null
            ? (string) round($scheduleRealismRatio, 3)
            : null;

        return $this;
    }

    /**
     * Get schedule realism grade based on the ratio.
     *
     * Converts the stored ratio into a human-readable grade.
     */
    public function getScheduleRealismGrade(): ScheduleRealismGrade
    {
        $ratio = $this->scheduleRealismRatio !== null
            ? (float) $this->scheduleRealismRatio
            : null;

        return ScheduleRealismGrade::fromRatio($ratio);
    }
}
