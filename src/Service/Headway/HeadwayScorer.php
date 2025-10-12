<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\ScoreDto;
use App\Enum\ConfidenceLevel;
use App\Enum\DirectionEnum;

use function count;

final readonly class HeadwayScorer
{
    public function __construct(
        private VehicleGrouper $grouper,
        private HeadwayCalculator $calc,
        private ScheduleAdherenceCalculator $adherenceCalc,
    ) {
    }

    /** @return list<ScoreDto> */
    public function compute(array $vehicles, int $asOfTs): array
    {
        $groups = $this->grouper->groupByRouteDirection($vehicles);

        $out = [];
        foreach ($groups as $key => $vs) {
            if ($vs === []) {
                continue;
            }
            [$routeId, $dirInt] = explode('|', $key);
            $vehicleCount       = count($vs);
            $observed           = $this->calc->observedHeadwaySec($vs);

            // For single-vehicle routes, calculate delay from schedule
            $delay = null;
            if ($vehicleCount === 1 && $observed === null) {
                $delay = $this->adherenceCalc->calculateDelay($vs[0]);
            }

            $grade = $this->calc->grade($observed, $vehicleCount, $delay);

            // Determine confidence level based on data source
            $confidence = $this->determineConfidence($observed, $vehicleCount, $delay);

            $out[] = new ScoreDto(
                routeId: $routeId,
                direction: DirectionEnum::from((int) $dirInt),
                observedHeadwaySec: $observed,
                scheduledHeadwaySec: null, // TODO: use StopTime to compute
                vehicles: $vehicleCount,
                grade: $grade,
                confidence: $confidence,
                asOfTs: $asOfTs
            );
        }

        usort($out, static fn (ScoreDto $a, ScoreDto $b) => [$a->routeId, $a->direction->value] <=> [$b->routeId, $b->direction->value]);

        return $out;
    }

    /**
     * Determine confidence level based on how score was calculated.
     */
    private function determineConfidence(?int $observed, int $vehicleCount, ?int $delay): ConfidenceLevel
    {
        // HIGH: Multi-vehicle headway calculation
        if ($observed !== null && $vehicleCount >= 2) {
            return ConfidenceLevel::HIGH;
        }

        // MEDIUM: Single-vehicle schedule adherence
        if ($vehicleCount === 1 && $delay !== null) {
            return ConfidenceLevel::MEDIUM;
        }

        // LOW: Default grade with no data
        return ConfidenceLevel::LOW;
    }
}
