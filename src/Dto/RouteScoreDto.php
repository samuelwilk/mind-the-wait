<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents a route's headway score and performance grade.
 *
 * Immutable DTO for transferring route scoring data.
 */
final readonly class RouteScoreDto
{
    public function __construct(
        public string $routeId,
        public int $direction,
        public ?int $observedHeadwaySec,
        public ?int $scheduledHeadwaySec,
        public int $vehicles,
        public string $grade,
        public string $confidence,
        public int $asOf,
    ) {
    }
}
