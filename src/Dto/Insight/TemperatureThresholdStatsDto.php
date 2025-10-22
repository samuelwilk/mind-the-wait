<?php

declare(strict_types=1);

namespace App\Dto\Insight;

/**
 * Statistics for temperature threshold insight generation.
 *
 * Contains performance comparison above and below critical temperature.
 */
final readonly class TemperatureThresholdStatsDto implements \JsonSerializable
{
    public function __construct(
        public float $aboveThreshold,
        public float $belowThreshold,
        public float $performanceDrop,
        public int $daysAbove,
        public int $daysBelow,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'aboveThreshold'  => $this->aboveThreshold,
            'belowThreshold'  => $this->belowThreshold,
            'performanceDrop' => $this->performanceDrop,
            'daysAbove'       => $this->daysAbove,
            'daysBelow'       => $this->daysBelow,
        ];
    }
}
