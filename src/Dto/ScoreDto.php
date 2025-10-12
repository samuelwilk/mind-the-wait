<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ConfidenceLevel;
use App\Enum\DirectionEnum;
use App\Enum\ScoreGradeEnum;

final readonly class ScoreDto
{
    public function __construct(
        public string $routeId,
        public DirectionEnum $direction,
        public ?int $observedHeadwaySec,
        public ?int $scheduledHeadwaySec,
        public int $vehicles,
        public ScoreGradeEnum $grade,
        public ConfidenceLevel $confidence,
        public int $asOfTs,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'route_id'              => $this->routeId,
            'direction'             => $this->direction->value,  // <- enum → int
            'observed_headway_sec'  => $this->observedHeadwaySec,
            'scheduled_headway_sec' => $this->scheduledHeadwaySec,
            'vehicles'              => $this->vehicles,
            'grade'                 => $this->grade->value,      // <- enum → 'A'|'B'|'C'|'D'
            'confidence'            => $this->confidence->value, // <- enum → 'high'|'medium'|'low'
            'as_of'                 => $this->asOfTs,
        ];
    }
}
