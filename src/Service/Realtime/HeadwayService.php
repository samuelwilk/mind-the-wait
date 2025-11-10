<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\HeadwayDTO;
use App\Dto\VehicleDto;

use function count;
use function usort;

/**
 * Calculates current headway windows (min/max) from vehicle positions.
 *
 * Computes time gaps between consecutive vehicles on a route direction.
 */
final readonly class HeadwayService
{
    /**
     * Calculate headway window from vehicles on a route/direction.
     *
     * @param list<VehicleDto> $vehicles Vehicles to analyze (should be filtered by route+direction)
     *
     * @return HeadwayDTO Headway window with min/max gaps in seconds
     */
    public function calculateHeadway(array $vehicles): HeadwayDTO
    {
        if (count($vehicles) < 2) {
            // Not enough vehicles to calculate headway
            return new HeadwayDTO(minSec: 0, maxSec: 0);
        }

        // Sort vehicles by timestamp (most recent first)
        $sorted = $vehicles;
        usort($sorted, fn ($a, $b) => ($b->timestamp ?? 0) <=> ($a->timestamp ?? 0));

        $gaps = [];

        // Calculate time gaps between consecutive vehicles
        for ($i = 0; $i < count($sorted) - 1; ++$i) {
            $current = $sorted[$i];
            $next    = $sorted[$i + 1];

            if ($current->timestamp !== null && $next->timestamp !== null) {
                $gap = abs($current->timestamp - $next->timestamp);

                // Only consider reasonable gaps (less than 1 hour)
                if ($gap > 0 && $gap < 3600) {
                    $gaps[] = $gap;
                }
            }
        }

        if (count($gaps) === 0) {
            return new HeadwayDTO(minSec: 0, maxSec: 0);
        }

        return new HeadwayDTO(
            minSec: (int) min($gaps),
            maxSec: (int) max($gaps)
        );
    }
}
