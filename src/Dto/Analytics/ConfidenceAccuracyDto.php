<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function sprintf;

final readonly class ConfidenceAccuracyDto
{
    public function __construct(
        public string $confidence,
        public float $maeSeconds,
        public float $biasSeconds,
        public float $within3Min,
        public int $sampleSize,
    ) {
    }

    public function getConfidenceLabel(): string
    {
        return match ($this->confidence) {
            'high'   => 'GTFS-RT TripUpdate',
            'medium' => 'GPS Interpolation',
            'low'    => 'Schedule Only',
            default  => ucfirst($this->confidence),
        };
    }

    public function getConfidenceBadgeColor(): string
    {
        return match ($this->confidence) {
            'high'   => 'success',
            'medium' => 'warning',
            'low'    => 'gray',
            default  => 'gray',
        };
    }

    public function getFormattedMae(): string
    {
        $min = (int) ($this->maeSeconds / 60);
        $sec = (int) ($this->maeSeconds % 60);

        return sprintf('%d:%02d', $min, $sec);
    }
}
