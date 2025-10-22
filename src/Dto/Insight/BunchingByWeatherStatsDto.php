<?php

declare(strict_types=1);

namespace App\Dto\Insight;

/**
 * Statistics for bunching by weather insight generation.
 *
 * Contains normalized bunching rates per hour for different weather conditions.
 */
final readonly class BunchingByWeatherStatsDto implements \JsonSerializable
{
    public function __construct(
        public bool $hasData,
        public float $snowRate,
        public int $snowHours,
        public float $rainRate,
        public int $rainHours,
        public float $clearRate,
        public int $clearHours,
        public float $multiplier,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'hasData'     => $this->hasData,
            'snow_rate'   => $this->snowRate,
            'snow_hours'  => $this->snowHours,
            'rain_rate'   => $this->rainRate,
            'rain_hours'  => $this->rainHours,
            'clear_rate'  => $this->clearRate,
            'clear_hours' => $this->clearHours,
            'multiplier'  => $this->multiplier,
        ];
    }
}
