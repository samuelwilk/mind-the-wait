<?php

declare(strict_types=1);

namespace App\ValueObject\Chart;

/**
 * Immutable value object representing an ECharts configuration.
 *
 * This class encapsulates chart configuration arrays and provides
 * JSON serialization for use in Twig templates.
 */
final readonly class Chart implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $config ECharts configuration array
     */
    public function __construct(
        private array $config,
    ) {
    }

    /**
     * Get the raw ECharts configuration array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    public function jsonSerialize(): mixed
    {
        return $this->config;
    }
}
