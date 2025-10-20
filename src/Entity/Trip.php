<?php

namespace App\Entity;

use App\Enum\DirectionEnum;
use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $gtfsId = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Route $route = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $serviceId = null;

    #[ORM\Column(enumType: DirectionEnum::class)]
    private ?DirectionEnum $direction = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $headsign = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: false)]
    private City $city;

    /**
     * @var Collection<int, StopTime>
     */
    #[ORM\OneToMany(targetEntity: StopTime::class, mappedBy: 'trip')]
    private Collection $stopTimes;

    public function __construct()
    {
        $this->stopTimes = new ArrayCollection();
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

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getServiceId(): ?string
    {
        return $this->serviceId;
    }

    public function setServiceId(?string $serviceId): static
    {
        $this->serviceId = $serviceId;

        return $this;
    }

    public function getDirection(): ?DirectionEnum
    {
        return $this->direction;
    }

    public function setDirection(DirectionEnum $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getHeadsign(): ?string
    {
        return $this->headsign;
    }

    public function setHeadsign(?string $headsign): static
    {
        $this->headsign = $headsign;

        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @return Collection<int, StopTime>
     */
    public function getStopTimes(): Collection
    {
        return $this->stopTimes;
    }

    public function addStopTime(StopTime $stopTime): static
    {
        if (!$this->stopTimes->contains($stopTime)) {
            $this->stopTimes->add($stopTime);
            $stopTime->setTrip($this);
        }

        return $this;
    }

    public function removeStopTime(StopTime $stopTime): static
    {
        if ($this->stopTimes->removeElement($stopTime)) {
            // set the owning side to null (unless already changed)
            if ($stopTime->getTrip() === $this) {
                $stopTime->setTrip(null);
            }
        }

        return $this;
    }
}
