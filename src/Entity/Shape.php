<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single point on a route shape polyline (from GTFS shapes.txt).
 *
 * Each shape_id maps to one polyline (sequence of lat/lon points with distance).
 * Trips reference a shape_id to define the road path the bus actually follows.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shape')]
#[ORM\Index(columns: ['shape_id', 'sequence'], name: 'idx_shape_id_seq')]
class Shape
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $shapeId;

    #[ORM\Column(type: Types::FLOAT)]
    private float $lat;

    #[ORM\Column(type: Types::FLOAT)]
    private float $lon;

    #[ORM\Column(type: Types::INTEGER)]
    private int $sequence;

    /** Cumulative distance traveled along the shape (km or agency units). */
    #[ORM\Column(type: Types::FLOAT)]
    private float $distTraveled;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShapeId(): string
    {
        return $this->shapeId;
    }

    public function setShapeId(string $shapeId): self
    {
        $this->shapeId = $shapeId;

        return $this;
    }

    public function getLat(): float
    {
        return $this->lat;
    }

    public function setLat(float $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): float
    {
        return $this->lon;
    }

    public function setLon(float $lon): self
    {
        $this->lon = $lon;

        return $this;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getDistTraveled(): float
    {
        return $this->distTraveled;
    }

    public function setDistTraveled(float $distTraveled): self
    {
        $this->distTraveled = $distTraveled;

        return $this;
    }
}
