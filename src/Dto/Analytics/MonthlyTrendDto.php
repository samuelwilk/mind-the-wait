<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function sprintf;

/**
 * Data transfer object for monthly performance trend.
 *
 * Aggregates system-wide performance by month.
 */
final readonly class MonthlyTrendDto
{
    public function __construct(
        public int $year,
        public int $month,
        public float $avgOnTimePercentage,
        public int $avgDelaySec,
        public int $daysWithData,
        public int $totalPredictions,
    ) {
    }

    /**
     * Get formatted month label (e.g., "Oct 2025").
     */
    public function getMonthLabel(): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%d-%d-1', $this->year, $this->month));

        return $date !== false ? $date->format('M Y') : 'Unknown';
    }

    /**
     * Get the month as a sortable key (YYYY-MM format).
     */
    public function getSortKey(): string
    {
        return sprintf('%d-%02d', $this->year, $this->month);
    }
}
