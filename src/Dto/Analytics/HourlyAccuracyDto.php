<?php

declare(strict_types=1);

namespace App\Dto\Analytics;

final readonly class HourlyAccuracyDto
{
    public function __construct(
        public int $hour,
        public float $maeSeconds,
        public float $within3Min,
        public int $sampleSize,
    ) {
    }

    public function getHourLabel(): string
    {
        return match (true) {
            $this->hour === 0  => '12 AM',
            $this->hour < 12   => $this->hour.' AM',
            $this->hour === 12 => '12 PM',
            default            => ($this->hour - 12).' PM',
        };
    }

    public function getMaeMinutes(): float
    {
        return round($this->maeSeconds / 60, 1);
    }
}
