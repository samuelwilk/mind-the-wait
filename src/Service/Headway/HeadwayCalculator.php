<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\VehicleDto;
use App\Enum\ScoreGradeEnum;

use function count;

final readonly class HeadwayCalculator
{
    public function __construct(
        private PositionInterpolator $interpolator,
    ) {
    }

    /**
     * Calculate observed headway using position-based interpolation.
     * Estimates when each vehicle will cross a reference point (midpoint of route)
     * and calculates time deltas at that common point.
     *
     * @param list<VehicleDto> $vehicles
     */
    public function observedHeadwaySec(array $vehicles): ?int
    {
        if (count($vehicles) < 2) {
            return null;
        }

        // Try position-based calculation first
        $crossingTimes = [];
        foreach ($vehicles as $v) {
            $crossingTime = $this->interpolator->estimateTimeAtProgress($v, 0.5);
            if ($crossingTime !== null) {
                $crossingTimes[] = $crossingTime;
            }
        }

        // If we have enough position-based estimates, use those
        if (count($crossingTimes) >= 2) {
            return $this->calculateMedianHeadway($crossingTimes);
        }

        // Fallback: use raw timestamps (legacy behavior, less accurate)
        $times = [];
        foreach ($vehicles as $v) {
            if ($v->timestamp !== null) {
                $times[] = $v->timestamp;
            }
        }

        if (count($times) < 2) {
            return null;
        }

        return $this->calculateMedianHeadway($times);
    }

    /**
     * Calculate median headway from a list of timestamps.
     *
     * @param list<int> $times Unix timestamps
     */
    private function calculateMedianHeadway(array $times): ?int
    {
        sort($times);
        if (count($times) < 2) {
            return null;
        }

        $deltas = [];
        for ($i = 1; $i < count($times); ++$i) {
            $d = max(0, $times[$i] - $times[$i - 1]);
            if ($d > 0) {
                $deltas[] = $d;
            }
        }

        if (!$deltas) {
            return null;
        }

        sort($deltas);
        $count = count($deltas);

        // Proper median calculation
        if ($count % 2 === 0) {
            // Even number: average the two middle elements
            $mid1 = $deltas[intdiv($count, 2) - 1];
            $mid2 = $deltas[intdiv($count, 2)];

            return (int) (($mid1 + $mid2) / 2);
        }

        // Odd number: return middle element
        return $deltas[intdiv($count, 2)];
    }

    public function grade(?int $observedSec, int $vehicleCount): ScoreGradeEnum
    {
        return ScoreGradeEnum::fromObserved($observedSec, $vehicleCount);
    }
}
