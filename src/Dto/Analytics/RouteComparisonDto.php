<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

/**
 * Data transfer object for route comparison analytics.
 *
 * Contains aggregated performance metrics for a single route,
 * designed for side-by-side comparison with other routes.
 */
final readonly class RouteComparisonDto
{
    public function __construct(
        public int $routeId,
        public string $shortName,
        public string $longName,
        public ?string $colour,
        public float $avgOnTimePercentage,
        public int $avgDelaySec,
        public int $totalPredictions,
        public int $daysWithData,
        public ?float $bunchingRate = null,
        public ?\DateTimeImmutable $bestDay = null,
        public ?float $bestDayPercentage = null,
        public ?\DateTimeImmutable $worstDay = null,
        public ?float $worstDayPercentage = null,
    ) {
    }

    /**
     * Get letter grade based on on-time percentage.
     */
    public function getGrade(): string
    {
        return match (true) {
            $this->avgOnTimePercentage >= 90 => 'A',
            $this->avgOnTimePercentage >= 80 => 'B',
            $this->avgOnTimePercentage >= 70 => 'C',
            $this->avgOnTimePercentage >= 60 => 'D',
            default                          => 'F',
        };
    }

    /**
     * Get grade color for UI.
     */
    public function getGradeColor(): string
    {
        return match ($this->getGrade()) {
            'A', 'B' => 'green',
            'C'     => 'yellow',
            'D'     => 'orange',
            default => 'red',
        };
    }
}
