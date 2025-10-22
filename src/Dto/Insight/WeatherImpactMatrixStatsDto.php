<?php

declare(strict_types=1);

namespace App\Dto\Insight;

/**
 * Statistics for weather impact matrix insight generation.
 *
 * Contains system-wide weather condition performance data.
 */
final readonly class WeatherImpactMatrixStatsDto implements \JsonSerializable
{
    public function __construct(
        public string $worstCondition,
        public float $avgPerformance,
        public int $dayCount,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'worstCondition' => $this->worstCondition,
            'avgPerformance' => $this->avgPerformance,
            'dayCount'       => $this->dayCount,
        ];
    }
}
