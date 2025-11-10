<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Enriched vehicle data combining position, status, and arrival prediction.
 *
 * All data needed to render a vehicle card in the UI.
 */
final readonly class EnrichedVehicleDTO
{
    public function __construct(
        public string $vehicleId,
        public VehicleDto $vehicle,
        public VehicleStatusDto $status,
        public ?ArrivalPredictionDto $nextArrival,
    ) {
    }

    /**
     * Get vehicle identifier for display.
     */
    public function getDisplayId(): string
    {
        // Extract numeric part from vehicle ID if possible
        // e.g., "veh_123" -> "Bus 123", "123" -> "Bus 123"
        if (preg_match('/\d+/', $this->vehicleId, $matches)) {
            return 'Bus '.$matches[0];
        }

        return $this->vehicleId;
    }

    /**
     * Get next stop name for display.
     */
    public function getNextStopName(): string
    {
        return $this->nextArrival?->stopName ?? 'Unknown';
    }

    /**
     * Get ETA in minutes.
     */
    public function getEtaMinutes(): ?int
    {
        if ($this->nextArrival === null) {
            return null;
        }

        return (int) round($this->nextArrival->arrivalInSec() / 60);
    }

    /**
     * Get speed in km/h rounded to nearest integer.
     */
    public function getSpeedKmh(): ?int
    {
        if ($this->vehicle->speed === null) {
            return null;
        }

        // Convert m/s to km/h
        return (int) round($this->vehicle->speed * 3.6);
    }

    /**
     * Get bearing in degrees (0-359).
     */
    public function getBearing(): ?int
    {
        return $this->vehicle->bearing !== null ? (int) round($this->vehicle->bearing) : null;
    }

    /**
     * Get timestamp of last position update.
     */
    public function getLastSeen(): ?\DateTimeImmutable
    {
        if ($this->vehicle->timestamp === null) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('U', (string) $this->vehicle->timestamp) ?: null;
    }

    /**
     * Get age of last position report in human-readable format.
     *
     * Examples: "23s ago", "45s ago", "120s ago"
     */
    public function getLastSeenText(): string
    {
        $lastSeen = $this->getLastSeen();
        if ($lastSeen === null) {
            return 'Unknown';
        }

        $now        = new \DateTimeImmutable();
        $ageSeconds = $now->getTimestamp() - $lastSeen->getTimestamp();

        return "{$ageSeconds}s ago";
    }

    /**
     * Get CSS color class based on data freshness.
     *
     * - Green: < 60s (fresh)
     * - Yellow: 60-120s (slightly stale)
     * - Red: > 120s (very stale)
     */
    public function getFreshnessColor(): string
    {
        $lastSeen = $this->getLastSeen();
        if ($lastSeen === null) {
            return 'text-gray-500';
        }

        $now        = new \DateTimeImmutable();
        $ageSeconds = $now->getTimestamp() - $lastSeen->getTimestamp();

        if ($ageSeconds < 60) {
            return 'text-success-600'; // Fresh (< 1 min)
        }

        if ($ageSeconds < 120) {
            return 'text-warning-600'; // Slightly stale (1-2 min)
        }

        return 'text-danger-600'; // Very stale (> 2 min)
    }
}
