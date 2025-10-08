<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\PredictionConfidence;

final readonly class ArrivalPredictionDto
{
    public function __construct(
        public string $vehicleId,
        public string $routeId,
        public string $tripId,
        public string $stopId,
        public ?string $headsign,
        public int $arrivalAt,              // Unix timestamp
        public PredictionConfidence $confidence,
        public ?VehicleStatusDto $status = null,
        public ?array $currentLocation = null,  // ['lat' => float, 'lon' => float, 'stops_away' => int]
        public ?array $feedbackSummary = null,
    ) {
    }

    public function arrivalInSec(?int $now = null): int
    {
        $now = $now ?? time();

        return max(0, $this->arrivalAt - $now);
    }

    public function toArray(?int $now = null): array
    {
        return [
            'vehicle_id'       => $this->vehicleId,
            'route_id'         => $this->routeId,
            'trip_id'          => $this->tripId,
            'stop_id'          => $this->stopId,
            'headsign'         => $this->headsign,
            'arrival_in_sec'   => $this->arrivalInSec($now),
            'arrival_at'       => $this->arrivalAt,
            'confidence'       => $this->confidence->value,
            'status'           => $this->status?->toArray(),
            'current_location' => $this->currentLocation,
            'feedback_summary' => $this->feedbackSummary,
        ];
    }
}
