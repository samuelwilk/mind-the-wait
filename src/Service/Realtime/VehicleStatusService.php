<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Dto\VehicleDto;
use App\Dto\VehicleStatusDto;
use App\Enum\VehiclePunctualityLabel;
use App\Enum\VehicleStatusColor;
use App\Repository\VehicleFeedbackRepositoryInterface;
use App\Service\Headway\StopTimeProviderInterface;

use function abs;
use function is_array;
use function is_numeric;
use function max;
use function time;

final readonly class VehicleStatusService
{
    private const PAST_STOP_GRACE_SEC = 90;

    public function __construct(
        private StopTimeProviderInterface $stopTimeProvider,
        private TrafficReasonProviderInterface $trafficReasonProvider,
        private VehicleFeedbackRepositoryInterface $feedbackRepository,
    ) {
    }

    public function enrichSnapshot(array $snapshot): array
    {
        if (!isset($snapshot['vehicles']) || !is_array($snapshot['vehicles'])) {
            return $snapshot;
        }

        $vehicles = $snapshot['vehicles'];
        foreach ($vehicles as $idx => $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $dto       = VehicleDto::fromArray($raw);
            $vehicleId = $this->resolveVehicleId($raw, $dto);

            if ($dto === null || $dto->tripId === null) {
                if ($vehicleId !== null) {
                    $vehicles[$idx]['feedback'] = $this->feedbackRepository->getSummary($vehicleId);
                }
                continue;
            }

            $status = $this->determineStatus($dto);
            if ($status !== null) {
                $feedback           = $vehicleId !== null ? $this->feedbackRepository->getSummary($vehicleId) : [];
                $statusWithFeedback = $status->withFeedback($feedback);

                $vehicles[$idx]['status'] = $statusWithFeedback->toArray();
                if ($vehicleId !== null) {
                    $vehicles[$idx]['feedback'] = $feedback;
                }
            } else {
                if ($vehicleId !== null) {
                    $vehicles[$idx]['feedback'] = $this->feedbackRepository->getSummary($vehicleId);
                }
            }
        }

        $snapshot['vehicles'] = $vehicles;

        return $snapshot;
    }

    private function determineStatus(VehicleDto $vehicle): ?VehicleStatusDto
    {
        $stopTimes = $this->stopTimeProvider->getStopTimesForTrip($vehicle->tripId);
        if ($stopTimes === null) {
            return null;
        }

        $referenceTs = max($vehicle->timestamp ?? 0, time());

        foreach ($stopTimes as $stop) {
            $time = $stop['arr'] ?? $stop['dep'] ?? null;
            if ($time === null) {
                continue;
            }

            if ($time < $referenceTs - self::PAST_STOP_GRACE_SEC) {
                continue;
            }

            $delay = $stop['delay'] ?? null;
            if (!is_numeric($delay)) {
                continue;
            }

            $delay = (int) $delay;

            $classification = $this->classifyDelay($delay);
            $reason         = $this->trafficReasonProvider->reasonFor($vehicle, $delay);

            return new VehicleStatusDto(
                color: $classification['color'],
                label: $classification['label'],
                severity: $classification['severity'],
                deviationSec: $delay,
                reason: $reason
            );
        }

        return null;
    }

    /**
     * @return array{color: VehicleStatusColor, label: VehiclePunctualityLabel, severity: string}
     */
    private function classifyDelay(int $delaySeconds): array
    {
        $absDelay = abs($delaySeconds);

        // On time (within 1 minute)
        if ($absDelay <= 60) {
            return [
                'color'    => VehicleStatusColor::YELLOW,
                'label'    => VehiclePunctualityLabel::ON_TIME,
                'severity' => '✓ vibing',
            ];
        }

        // Running early
        if ($delaySeconds < -60) {
            if ($absDelay >= 600) {
                // Wayyyy early (10+ min)
                return [
                    'color'    => VehicleStatusColor::GREEN,
                    'label'    => VehiclePunctualityLabel::AHEAD,
                    'severity' => '🚀 warp speed',
                ];
            }

            if ($absDelay >= 180) {
                // Moderately early (3-10 min)
                return [
                    'color'    => VehicleStatusColor::BLUE,
                    'label'    => VehiclePunctualityLabel::AHEAD,
                    'severity' => '⚡ zooming',
                ];
            }

            // Slightly early (1-3 min)
            return [
                'color'    => VehicleStatusColor::BLUE,
                'label'    => VehiclePunctualityLabel::AHEAD,
                'severity' => '🏃 speedy',
            ];
        }

        // Running late
        if ($absDelay >= 900) {
            // Catastrophically late (15+ min)
            return [
                'color'    => VehicleStatusColor::PURPLE,
                'label'    => VehiclePunctualityLabel::LATE,
                'severity' => '💀 ghost bus',
            ];
        }

        if ($absDelay >= 420) {
            // Very late (7-15 min)
            return [
                'color'    => VehicleStatusColor::RED,
                'label'    => VehiclePunctualityLabel::LATE,
                'severity' => '🔥 yikes',
            ];
        }

        if ($absDelay >= 180) {
            // Moderately late (3-7 min)
            return [
                'color'    => VehicleStatusColor::ORANGE,
                'label'    => VehiclePunctualityLabel::LATE,
                'severity' => '😬 delayed',
            ];
        }

        // Slightly late (1-3 min)
        return [
            'color'    => VehicleStatusColor::ORANGE,
            'label'    => VehiclePunctualityLabel::LATE,
            'severity' => '🐌 fashionably late',
        ];
    }

    private function resolveVehicleId(array $raw, ?VehicleDto $dto): ?string
    {
        $candidates = [
            isset($raw['id']) ? (string) $raw['id'] : null,
            $dto?->tripId,
            $dto?->routeId,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
