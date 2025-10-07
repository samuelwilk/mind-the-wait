<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\ScoreDto;
use App\Enum\DirectionEnum;

use function count;

final readonly class HeadwayScorer
{
    public function __construct(
        private VehicleGrouper $grouper,
        private HeadwayCalculator $calc,
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
            $observed           = $this->calc->observedHeadwaySec($vs);
            $grade              = $this->calc->grade($observed, count($vs));

            $out[] = new ScoreDto(
                routeId: $routeId,
                direction: DirectionEnum::from((int) $dirInt),
                observedHeadwaySec: $observed,
                scheduledHeadwaySec: null, // TODO: use StopTime to compute
                vehicles: count($vs),
                grade: $grade,
                asOfTs: $asOfTs
            );
        }

        usort($out, static fn (ScoreDto $a, ScoreDto $b) => [$a->routeId, $a->direction->value] <=> [$b->routeId, $b->direction->value]);

        return $out;
    }
}
