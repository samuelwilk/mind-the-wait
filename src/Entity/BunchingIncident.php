<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records vehicle bunching incidents.
 *
 * Bunching occurs when 2+ vehicles on the same route arrive at a stop
 * within a short time window (typically < 2 minutes), indicating
 * poor headway management.
 */
#[ORM\Entity(repositoryClass: \App\Repository\BunchingIncidentRepository::class)]
#[ORM\Table(name: 'bunching_incident')]
#[ORM\Index(columns: ['route_id', 'detected_at'], name: 'idx_bunching_route_detected')]
#[ORM\Index(columns: ['stop_id', 'detected_at'], name: 'idx_bunching_stop_detected')]
#[ORM\Index(columns: ['detected_at'], name: 'idx_bunching_detected_at')]
class BunchingIncident
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\ManyToOne(targetEntity: Stop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Stop $stop;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $detectedAt;

    #[ORM\Column(type: Types::INTEGER)]
    private int $vehicleCount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $timeWindowSeconds;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $vehicleIds = null;

    #[ORM\ManyToOne(targetEntity: WeatherObservation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WeatherObservation $weatherObservation = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getStop(): Stop
    {
        return $this->stop;
    }

    public function setStop(Stop $stop): self
    {
        $this->stop = $stop;

        return $this;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(\DateTimeImmutable $detectedAt): self
    {
        $this->detectedAt = $detectedAt;

        return $this;
    }

    public function getVehicleCount(): int
    {
        return $this->vehicleCount;
    }

    public function setVehicleCount(int $vehicleCount): self
    {
        $this->vehicleCount = $vehicleCount;

        return $this;
    }

    public function getTimeWindowSeconds(): int
    {
        return $this->timeWindowSeconds;
    }

    public function setTimeWindowSeconds(int $timeWindowSeconds): self
    {
        $this->timeWindowSeconds = $timeWindowSeconds;

        return $this;
    }

    public function getVehicleIds(): ?string
    {
        return $this->vehicleIds;
    }

    public function setVehicleIds(?string $vehicleIds): self
    {
        $this->vehicleIds = $vehicleIds;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
