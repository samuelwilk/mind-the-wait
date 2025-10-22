<?php

declare(strict_types=1);

namespace App\Dto\Insight;

/**
 * Statistics for dashboard winter impact card insight.
 *
 * Contains average performance drop during snow vs clear conditions.
 */
final readonly class DashboardWinterImpactStatsDto implements \JsonSerializable
{
    public function __construct(
        public float $avgDrop,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'avgDrop' => $this->avgDrop,
        ];
    }
}
