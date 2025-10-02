<?php

namespace App\Entity;

use App\Enum\RouteTypeEnum;
use App\Repository\RouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteRepository::class)]
class Route
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $gtfsId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $shortName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longName = null;

    #[ORM\Column(length: 6, nullable: true)]
    private ?string $colour = null;

    #[ORM\Column(nullable: true, enumType: RouteTypeEnum::class)]
    private ?RouteTypeEnum $routeType = null;

    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'route')]
    private Collection $trips;

    public function __construct()
    {
        $this->trips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGtfsId(): ?string
    {
        return $this->gtfsId;
    }

    public function setGtfsId(string $gtfsId): static
    {
        $this->gtfsId = $gtfsId;

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName;

        return $this;
    }

    public function getLongName(): ?string
    {
        return $this->longName;
    }

    public function setLongName(?string $longName): static
    {
        $this->longName = $longName;

        return $this;
    }

    public function getColour(): ?string
    {
        return $this->colour;
    }

    public function setColour(?string $colour): static
    {
        $this->colour = $colour;

        return $this;
    }

    public function getRouteType(): ?RouteTypeEnum
    {
        return $this->routeType;
    }

    public function setRouteType(?RouteTypeEnum $routeType): static
    {
        $this->routeType = $routeType;

        return $this;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): static
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setRoute($this);
        }

        return $this;
    }

    public function removeTrip(Trip $trip): static
    {
        if ($this->trips->removeElement($trip)) {
            // set the owning side to null (unless already changed)
            if ($trip->getRoute() === $this) {
                $trip->setRoute(null);
            }
        }

        return $this;
    }
}
