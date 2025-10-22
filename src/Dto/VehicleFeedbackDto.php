<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Represents crowd-sourced feedback for a vehicle's punctuality.
 */
final readonly class VehicleFeedbackDto
{
    public function __construct(
        public int $ahead,
        public int $onTime,
        public int $late,
        public int $total,
    ) {
    }
}
