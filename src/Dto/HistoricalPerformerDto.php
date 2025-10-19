<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Data transfer object for historical route performance summary.
 *
 * Used to represent top/worst performing routes over a time period
 * based on aggregated daily performance data.
 */
final readonly class HistoricalPerformerDto
{
    /**
     * @param string      $gtfsId           Route GTFS ID
     * @param string      $shortName        Route short name (e.g., "1", "60")
     * @param string      $longName         Route long name
     * @param float       $avgOnTimePercent Average on-time percentage over period
     * @param int         $daysCount        Number of days analyzed
     * @param string      $grade            Letter grade (A-F) based on performance
     * @param string|null $colour           Route color hex code
     */
    public function __construct(
        public string $gtfsId,
        public string $shortName,
        public string $longName,
        public float $avgOnTimePercent,
        public int $daysCount,
        public string $grade,
        public ?string $colour = null,
    ) {
    }
}
