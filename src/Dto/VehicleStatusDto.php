<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\VehiclePunctualityLabel;
use App\Enum\VehicleStatusColor;

final readonly class VehicleStatusDto
{
    public function __construct(
        public VehicleStatusColor $color,
        public VehiclePunctualityLabel $label,
        public string $severity, // minor | major | critical
        public int $deviationSec,
        public ?string $reason = null,
        public array $feedback = [],
    ) {
    }

    public function withFeedback(array $feedback): self
    {
        return new self(
            $this->color,
            $this->label,
            $this->severity,
            $this->deviationSec,
            $this->reason,
            $feedback
        );
    }

    public function withReason(?string $reason): self
    {
        return new self(
            $this->color,
            $this->label,
            $this->severity,
            $this->deviationSec,
            $reason,
            $this->feedback
        );
    }

    public function toArray(): array
    {
        return [
            'color'         => $this->color->value,
            'label'         => $this->label->value,
            'severity'      => $this->severity,
            'deviation_sec' => $this->deviationSec,
            'reason'        => $this->reason,
            'feedback'      => $this->feedback,
        ];
    }
}
