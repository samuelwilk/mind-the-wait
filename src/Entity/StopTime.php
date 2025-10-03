<?php

namespace App\Entity;

use App\Repository\StopTimeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StopTimeRepository::class)]
#[ORM\Table(
    indexes: [
        new ORM\Index(name: 'idx_stop_time_trip', columns: ['trip_id']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'stop_time_trip_seq_unique',
            columns: ['trip_id', 'stop_sequence']
        ),
    ]
)]
class StopTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stopTimes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(inversedBy: 'stopTimes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stop $stop = null;

    #[ORM\Column]
    private ?int $stopSequence = null;

    #[ORM\Column(nullable: true)]
    private ?int $arrivalTime = null;

    #[ORM\Column(nullable: true)]
    private ?int $departureTime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function getStop(): ?Stop
    {
        return $this->stop;
    }

    public function setStop(?Stop $stop): static
    {
        $this->stop = $stop;

        return $this;
    }

    public function getStopSequence(): ?int
    {
        return $this->stopSequence;
    }

    public function setStopSequence(int $stopSequence): static
    {
        $this->stopSequence = $stopSequence;

        return $this;
    }

    public function getArrivalTime(): ?int
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(?int $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;

        return $this;
    }

    public function getDepartureTime(): ?int
    {
        return $this->departureTime;
    }

    public function setDepartureTime(?int $departureTime): static
    {
        $this->departureTime = $departureTime;

        return $this;
    }
}
