<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents a bunching incident candidate from arrival log analysis.
 *
 * Immutable DTO for transferring bunching detection results from repository to service.
 */
final readonly class BunchingCandidateDto
{
    public function __construct(
        public int $routeId,
        public int $stopId,
        public \DateTimeImmutable $bunchingTime,
        public int $vehicleCount,
        public string $vehicleIds,
    ) {
    }
}
