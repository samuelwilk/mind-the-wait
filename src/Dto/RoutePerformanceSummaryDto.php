<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Data transfer object for route performance summary across weather conditions.
 *
 * Used in winter operations analysis to compare route performance
 * between clear and adverse weather conditions.
 */
final readonly class RoutePerformanceSummaryDto
{
    /**
     * @param string $routeId          Route GTFS ID
     * @param string $shortName        Route short name (e.g., "16")
     * @param string $longName         Route long name (e.g., "Eastview / City Centre")
     * @param float  $clearPerformance On-time performance % during clear weather
     * @param float  $snowPerformance  On-time performance % during snow
     * @param float  $performanceDrop  Performance decrease (clear - snow)
     * @param int    $daysAnalyzed     Number of days included in analysis
     */
    public function __construct(
        public string $routeId,
        public string $shortName,
        public string $longName,
        public float $clearPerformance,
        public float $snowPerformance,
        public float $performanceDrop,
        public int $daysAnalyzed,
    ) {
    }
}
