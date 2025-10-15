<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Data transfer object for bunching incident counts grouped by weather condition.
 */
final readonly class BunchingCountDto
{
    public function __construct(
        public string $weatherCondition,
        public int $incidentCount,
    ) {
    }
}
