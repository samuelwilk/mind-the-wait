<?php

declare(strict_types=1);

namespace App\Dto\Insight;

/**
 * Statistics for winter operations insight generation.
 *
 * Contains route performance comparison between clear and snow conditions.
 */
final readonly class WinterOperationsStatsDto implements \JsonSerializable
{
    public function __construct(
        public string $worstRoute,
        public float $clearPerf,
        public float $snowPerf,
        public float $performanceDrop,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'worstRoute'      => $this->worstRoute,
            'clearPerf'       => $this->clearPerf,
            'snowPerf'        => $this->snowPerf,
            'performanceDrop' => $this->performanceDrop,
        ];
    }
}
