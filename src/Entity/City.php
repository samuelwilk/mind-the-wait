<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'city')]
#[ORM\HasLifecycleCallbacks]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 2)]
    private string $country = 'CA';

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $gtfsStaticUrl = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $gtfsRtVehicleUrl = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $gtfsRtTripUrl = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $gtfsRtAlertUrl = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8)]
    private string $centerLat;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8)]
    private string $centerLon;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $zoomLevel = 12;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getGtfsStaticUrl(): ?string
    {
        return $this->gtfsStaticUrl;
    }

    public function setGtfsStaticUrl(?string $gtfsStaticUrl): self
    {
        $this->gtfsStaticUrl = $gtfsStaticUrl;

        return $this;
    }

    public function getGtfsRtVehicleUrl(): ?string
    {
        return $this->gtfsRtVehicleUrl;
    }

    public function setGtfsRtVehicleUrl(?string $gtfsRtVehicleUrl): self
    {
        $this->gtfsRtVehicleUrl = $gtfsRtVehicleUrl;

        return $this;
    }

    public function getGtfsRtTripUrl(): ?string
    {
        return $this->gtfsRtTripUrl;
    }

    public function setGtfsRtTripUrl(?string $gtfsRtTripUrl): self
    {
        $this->gtfsRtTripUrl = $gtfsRtTripUrl;

        return $this;
    }

    public function getGtfsRtAlertUrl(): ?string
    {
        return $this->gtfsRtAlertUrl;
    }

    public function setGtfsRtAlertUrl(?string $gtfsRtAlertUrl): self
    {
        $this->gtfsRtAlertUrl = $gtfsRtAlertUrl;

        return $this;
    }

    public function getCenterLat(): float
    {
        return (float) $this->centerLat;
    }

    public function setCenterLat(string $centerLat): self
    {
        $this->centerLat = $centerLat;

        return $this;
    }

    public function getCenterLon(): float
    {
        return (float) $this->centerLon;
    }

    public function setCenterLon(string $centerLon): self
    {
        $this->centerLon = $centerLon;

        return $this;
    }

    public function getZoomLevel(): int
    {
        return $this->zoomLevel;
    }

    public function setZoomLevel(int $zoomLevel): self
    {
        $this->zoomLevel = $zoomLevel;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
