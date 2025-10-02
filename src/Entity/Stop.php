<?php

namespace App\Entity;

use App\Repository\StopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StopRepository::class)]
class Stop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $gtfsId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $lat = null;

    #[ORM\Column]
    private ?float $long = null;

    /**
     * @var Collection<int, StopTime>
     */
    #[ORM\OneToMany(targetEntity: StopTime::class, mappedBy: 'stop')]
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(float $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLong(): ?float
    {
        return $this->long;
    }

    public function setLong(float $long): static
    {
        $this->long = $long;

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
            $stopTime->setStop($this);
        }

        return $this;
    }

    public function removeStopTime(StopTime $stopTime): static
    {
        if ($this->stopTimes->removeElement($stopTime)) {
            // set the owning side to null (unless already changed)
            if ($stopTime->getStop() === $this) {
                $stopTime->setStop(null);
            }
        }

        return $this;
    }
}
