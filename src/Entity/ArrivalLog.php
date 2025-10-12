<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PredictionConfidence;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Logs every arrival prediction for historical analysis.
 *
 * This table grows continuously (~30K rows/day). Archive or delete rows >90 days old.
 */
#[ORM\Entity(repositoryClass: \App\Repository\ArrivalLogRepository::class)]
#[ORM\Table(name: 'arrival_log')]
#[ORM\Index(columns: ['route_id', 'predicted_at'], name: 'idx_arrival_log_route_predicted_at')]
#[ORM\Index(columns: ['stop_id', 'predicted_at'], name: 'idx_arrival_log_stop_predicted_at')]
#[ORM\Index(columns: ['trip_id', 'predicted_at'], name: 'idx_arrival_log_trip_predicted_at')]
#[ORM\Index(columns: ['predicted_at'], name: 'idx_arrival_log_predicted_at')]
class ArrivalLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $vehicleId;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $tripId;

    #[ORM\ManyToOne(targetEntity: Stop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Stop $stop;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $predictedArrivalAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledArrivalAt = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $delaySec = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: PredictionConfidence::class)]
    private PredictionConfidence $confidence;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $stopsAway = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $predictedAt;

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

    public function getVehicleId(): string
    {
        return $this->vehicleId;
    }

    public function setVehicleId(string $vehicleId): self
    {
        $this->vehicleId = $vehicleId;

        return $this;
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

    public function getTripId(): string
    {
        return $this->tripId;
    }

    public function setTripId(string $tripId): self
    {
        $this->tripId = $tripId;

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

    public function getPredictedArrivalAt(): \DateTimeImmutable
    {
        return $this->predictedArrivalAt;
    }

    public function setPredictedArrivalAt(\DateTimeImmutable $predictedArrivalAt): self
    {
        $this->predictedArrivalAt = $predictedArrivalAt;

        return $this;
    }

    public function getScheduledArrivalAt(): ?\DateTimeImmutable
    {
        return $this->scheduledArrivalAt;
    }

    public function setScheduledArrivalAt(?\DateTimeImmutable $scheduledArrivalAt): self
    {
        $this->scheduledArrivalAt = $scheduledArrivalAt;

        return $this;
    }

    public function getDelaySec(): ?int
    {
        return $this->delaySec;
    }

    public function setDelaySec(?int $delaySec): self
    {
        $this->delaySec = $delaySec;

        return $this;
    }

    public function getConfidence(): PredictionConfidence
    {
        return $this->confidence;
    }

    public function setConfidence(PredictionConfidence $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getStopsAway(): ?int
    {
        return $this->stopsAway;
    }

    public function setStopsAway(?int $stopsAway): self
    {
        $this->stopsAway = $stopsAway;

        return $this;
    }

    public function getPredictedAt(): \DateTimeImmutable
    {
        return $this->predictedAt;
    }

    public function setPredictedAt(\DateTimeImmutable $predictedAt): self
    {
        $this->predictedAt = $predictedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
