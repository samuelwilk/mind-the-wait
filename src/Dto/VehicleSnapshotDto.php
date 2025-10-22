<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents a single vehicle's realtime position and status.
 *
 * Immutable DTO for transferring vehicle snapshot data.
 */
final readonly class VehicleSnapshotDto
{
    public function __construct(
        public string $id,
        public string $trip,
        public string $route,
        public float $lat,
        public float $lon,
        public int $bearing,
        public ?float $speed,
        public int $ts,
        public VehicleFeedbackDto $feedback,
    ) {
    }
}
