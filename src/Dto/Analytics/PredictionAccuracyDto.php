<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

use function abs;
use function round;
use function sprintf;

final readonly class PredictionAccuracyDto
{
    /**
     * @param list<ConfidenceAccuracyDto> $byConfidence
     * @param list<HourlyAccuracyDto>     $byHour
     */
    public function __construct(
        public float $maeSeconds,
        public float $biasSeconds,
        public int $sampleSize,
        public float $within1Min,
        public float $within2Min,
        public float $within3Min,
        public float $within5Min,
        public array $byConfidence,
        public array $byHour,
    ) {
    }

    public function getMaeMinutes(): float
    {
        return round($this->maeSeconds / 60, 1);
    }

    public function getFormattedMae(): string
    {
        $min = (int) ($this->maeSeconds / 60);
        $sec = (int) ($this->maeSeconds % 60);

        return sprintf('%d:%02d', $min, $sec);
    }

    public function getFormattedBias(): string
    {
        $abs  = abs($this->biasSeconds);
        $min  = (int) ($abs / 60);
        $sec  = (int) ($abs % 60);
        $sign = $this->biasSeconds >= 0 ? '+' : '-';

        return sprintf('%s%d:%02d', $sign, $min, $sec);
    }

    public function getBiasDirection(): string
    {
        if (abs($this->biasSeconds) < 10) {
            return 'neutral';
        }

        return $this->biasSeconds > 0 ? 'late' : 'early';
    }

    public function getFormattedSampleSize(): string
    {
        if ($this->sampleSize >= 1_000_000) {
            return round($this->sampleSize / 1_000_000, 1).'M';
        }
        if ($this->sampleSize >= 1_000) {
            return round($this->sampleSize / 1_000, 1).'K';
        }

        return (string) $this->sampleSize;
    }
}
