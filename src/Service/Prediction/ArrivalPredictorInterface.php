<?php

declare(strict_types=1);

namespace App\Service\Prediction;

use App\Dto\ArrivalPredictionDto;

interface ArrivalPredictorInterface
{
    /**
     * Predict when a specific vehicle will arrive at a given stop.
     *
     * @param string $stopId    GTFS stop_id
     * @param string $tripId    GTFS trip_id
     * @param string $vehicleId Vehicle identifier
     *
     * @return ArrivalPredictionDto|null Prediction with confidence level, or null if cannot predict
     */
    public function predictArrival(string $stopId, string $tripId, string $vehicleId): ?ArrivalPredictionDto;

    /**
     * Get all upcoming vehicle arrivals for a specific stop.
     *
     * @param string   $stopId  GTFS stop_id
     * @param int|null $limit   Maximum number of predictions to return
     * @param int|null $routeId Optional filter by route
     *
     * @return list<ArrivalPredictionDto>
     */
    public function predictArrivalsForStop(string $stopId, ?int $limit = null, ?string $routeId = null): array;
}
