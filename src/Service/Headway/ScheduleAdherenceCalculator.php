<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\VehicleDto;

use function abs;
use function count;

/**
 * Calculates schedule adherence (delay) for vehicles based on position and schedule.
 */
final readonly class ScheduleAdherenceCalculator
{
    public function __construct(
        private StopTimeProviderInterface $stopTimeProvider,
        private CrossingTimeEstimatorInterface $positionInterpolator,
    ) {
    }

    /**
     * Calculate delay for a single vehicle by comparing position to schedule.
     *
     * @return int|null Delay in seconds (positive = late, negative = early, null = can't calculate)
     */
    public function calculateDelay(VehicleDto $vehicle): ?int
    {
        if ($vehicle->tripId === null) {
            return null;
        }

        // Get scheduled stop times from database
        $stopTimes = $this->stopTimeProvider->getStopTimesForTrip($vehicle->tripId);
        if ($stopTimes === null || empty($stopTimes)) {
            return null;
        }

        // Estimate vehicle's progress through the route (0.0 to 1.0)
        $progress = $this->positionInterpolator->estimateProgress($vehicle);
        if ($progress === null) {
            return null;
        }

        // Find the two stops the vehicle is between
        $stopPair = $this->findSurroundingStops($stopTimes, $progress);
        if ($stopPair === null) {
            return null;
        }

        // Interpolate expected time at current position
        $expectedTime = $this->interpolateExpectedTime($stopPair, $progress);
        if ($expectedTime === null) {
            return null;
        }

        // Calculate delay: actual time - expected time
        $actualTime = $vehicle->timestamp ?? time();
        $delay      = $actualTime - $expectedTime;

        return (int) $delay;
    }

    /**
     * Find the two stops that surround the vehicle's current position.
     *
     * @param list<array{stop_id: string, seq: int, arr: int|null, dep: int|null}> $stopTimes
     *
     * @return array{before: array, after: array, progress: float}|null
     */
    private function findSurroundingStops(array $stopTimes, float $vehicleProgress): ?array
    {
        $totalStops = count($stopTimes);
        if ($totalStops < 2) {
            return null;
        }

        // Convert progress (0.0-1.0) to stop index
        $targetIndex = $vehicleProgress * ($totalStops - 1);
        $beforeIndex = (int) floor($targetIndex);
        $afterIndex  = min($beforeIndex + 1, $totalStops - 1);

        if (!isset($stopTimes[$beforeIndex]) || !isset($stopTimes[$afterIndex])) {
            return null;
        }

        // Calculate progress between these two stops (0.0-1.0)
        $progressBetweenStops = $targetIndex - $beforeIndex;

        return [
            'before'   => $stopTimes[$beforeIndex],
            'after'    => $stopTimes[$afterIndex],
            'progress' => $progressBetweenStops,
        ];
    }

    /**
     * Interpolate expected time at vehicle's position between two stops.
     *
     * @param array{before: array, after: array, progress: float} $stopPair
     */
    private function interpolateExpectedTime(array $stopPair, float $vehicleProgress): ?int
    {
        $beforeStop = $stopPair['before'];
        $afterStop  = $stopPair['after'];
        $progress   = $stopPair['progress'];

        // Get times (use departure for before stop, arrival for after stop)
        $beforeTime = $beforeStop['dep'] ?? $beforeStop['arr'] ?? null;
        $afterTime  = $afterStop['arr']  ?? $afterStop['dep'] ?? null;

        if ($beforeTime === null || $afterTime === null) {
            return null;
        }

        // If times are invalid (after before before), can't interpolate
        if ($afterTime <= $beforeTime) {
            return null;
        }

        // Linear interpolation: expectedTime = beforeTime + progress * (afterTime - beforeTime)
        $timeDiff     = $afterTime - $beforeTime;
        $expectedTime = $beforeTime + ($progress * $timeDiff);

        return (int) round($expectedTime);
    }

    /**
     * Classify delay into a descriptive category.
     */
    public function classifyDelay(?int $delaySec): string
    {
        if ($delaySec === null) {
            return 'unknown';
        }

        $absDelay = abs($delaySec);

        if ($delaySec < -120) {  // More than 2 min early
            return 'very_early';
        }

        if ($delaySec < -60) {   // 1-2 min early
            return 'early';
        }

        if ($absDelay <= 60) {   // Within 1 minute
            return 'on_time';
        }

        if ($delaySec <= 180) {  // 1-3 min late
            return 'slightly_late';
        }

        if ($delaySec <= 300) {  // 3-5 min late
            return 'late';
        }

        return 'very_late';      // More than 5 min late
    }
}
