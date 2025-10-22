<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents the complete set of route scores.
 *
 * Immutable DTO containing all route/direction scores and timestamp.
 */
final readonly class RouteScoresDto
{
    /**
     * @param list<RouteScoreDto> $items
     */
    public function __construct(
        public int $ts,
        public array $items,
    ) {
    }
}
