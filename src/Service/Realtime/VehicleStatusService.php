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

        if ($absDelay <= 60) {
            return [
                'color'    => VehicleStatusColor::GREEN,
                'label'    => VehiclePunctualityLabel::ON_TIME,
                'severity' => 'minor',
            ];
        }

        if ($delaySeconds < -60) {
            $severity = $absDelay >= 300 ? 'critical' : 'major';
            $color    = $absDelay >= 300 ? VehicleStatusColor::RED : VehicleStatusColor::YELLOW;

            return [
                'color'    => $color,
                'label'    => VehiclePunctualityLabel::AHEAD,
                'severity' => $severity,
            ];
        }

        $severity = $absDelay >= 300 ? 'critical' : 'major';
        $color    = $absDelay >= 300 ? VehicleStatusColor::RED : VehicleStatusColor::YELLOW;

        return [
            'color'    => $color,
            'label'    => VehiclePunctualityLabel::LATE,
            'severity' => $severity,
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
