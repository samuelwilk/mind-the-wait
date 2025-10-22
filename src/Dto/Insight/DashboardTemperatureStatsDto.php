<?php

declare(strict_types=1);

namespace App\Dto\Insight;

/**
 * Statistics for dashboard temperature threshold card insight.
 *
 * Contains critical temperature threshold and performance impact data.
 */
final readonly class DashboardTemperatureStatsDto implements \JsonSerializable
{
    public function __construct(
        public int $threshold,
        public float $aboveThreshold,
        public float $belowThreshold,
        public float $performanceDrop,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'threshold'       => $this->threshold,
            'aboveThreshold'  => $this->aboveThreshold,
            'belowThreshold'  => $this->belowThreshold,
            'performanceDrop' => $this->performanceDrop,
        ];
    }
}
