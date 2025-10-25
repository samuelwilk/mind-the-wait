<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class RouteMetricDto
{
    public function __construct(
        public string $routeId,
        public string $shortName,
        public string $longName,
        public string $grade,
        public float $onTimePercentage,
        public ?string $colour = null,
        public ?int $activeVehicles = null,
        public ?string $trend = null,
        public ?string $issue = null,
        public int $daysOfData = 0,
        public string $confidenceLevel = 'unknown',
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'route_id'           => $this->routeId,
            'short_name'         => $this->shortName,
            'long_name'          => $this->longName,
            'grade'              => $this->grade,
            'on_time_percentage' => $this->onTimePercentage,
            'colour'             => $this->colour,
            'active_vehicles'    => $this->activeVehicles,
            'trend'              => $this->trend,
            'issue'              => $this->issue,
            'days_of_data'       => $this->daysOfData,
            'confidence_level'   => $this->confidenceLevel,
        ];
    }
}
