<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\VehicleDto;
use App\Enum\ScoreGradeEnum;

use function count;
use function max;
use function sort;
use function time;
use function usort;

final readonly class HeadwayCalculator
{
    private const PAST_ARRIVAL_GRACE_SEC = 60;

    public function __construct(
        private CrossingTimeEstimatorInterface $interpolator,
        private StopTimeProviderInterface $realtimeStopTimes,
    ) {
    }

    /**
     * Calculate observed headway using realtime TripUpdate predictions when available.
     * Falls back to position interpolation and raw timestamps if predictions are missing.
     *
     * @param list<VehicleDto> $vehicles
     */
    public function observedHeadwaySec(array $vehicles): ?int
    {
        if (count($vehicles) < 2) {
            return null;
        }

        // Prefer predictive headways from GTFS-RT TripUpdate feed
        $predictedArrivals = $this->collectUpcomingArrivals($vehicles);
        if (count($predictedArrivals) >= 2) {
            return $this->calculateMedianHeadway($predictedArrivals);
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
     * Gather the next predicted arrival times (absolute seconds) for vehicles that share an upcoming stop.
     *
     * @param list<VehicleDto> $vehicles
     *
     * @return list<int>
     */
    private function collectUpcomingArrivals(array $vehicles): array
    {
        $byStop = [];
        $now    = time();

        foreach ($vehicles as $vehicle) {
            if ($vehicle->tripId === null) {
                continue;
            }

            $stopTimes = $this->realtimeStopTimes->getStopTimesForTrip($vehicle->tripId);
            if ($stopTimes === null) {
                continue;
            }

            $referenceTs = max($vehicle->timestamp ?? 0, $now);
            $nextStop    = $this->findNextUpcomingStop($stopTimes, $referenceTs);
            if ($nextStop === null) {
                continue;
            }

            $byStop[$nextStop['stop_id']] ??= [];
            $byStop[$nextStop['stop_id']][] = $nextStop['time'];
        }

        if ($byStop === []) {
            return [];
        }

        $candidates = [];
        foreach ($byStop as $stopId => $times) {
            if (count($times) < 2) {
                continue;
            }

            sort($times);
            $candidates[] = [
                'stop'      => $stopId,
                'times'     => $times,
                'reference' => $times[0],
            ];
        }

        if ($candidates === []) {
            return [];
        }

        usort($candidates, static fn (array $a, array $b) => $a['reference'] <=> $b['reference']);

        /** @var list<int> */
        $selected = $candidates[0]['times'];

        return $selected;
    }

    /**
     * @param list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}> $stopTimes
     *
     * @return array{stop_id: string, time: int}|null
     */
    private function findNextUpcomingStop(array $stopTimes, int $referenceTs): ?array
    {
        foreach ($stopTimes as $stop) {
            $time = $stop['arr'] ?? $stop['dep'] ?? null;
            if ($time === null) {
                continue;
            }

            if ($time < $referenceTs - self::PAST_ARRIVAL_GRACE_SEC) {
                continue;
            }

            return [
                'stop_id' => (string) $stop['stop_id'],
                'time'    => (int) $time,
            ];
        }

        return null;
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
