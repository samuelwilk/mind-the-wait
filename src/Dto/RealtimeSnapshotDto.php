<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents a complete realtime snapshot of the transit system.
 *
 * Immutable DTO containing vehicles, scores, and timestamp.
 */
final readonly class RealtimeSnapshotDto
{
    /**
     * @param list<VehicleSnapshotDto> $vehicles
     */
    public function __construct(
        public int $ts,
        public array $vehicles,
    ) {
    }
}
