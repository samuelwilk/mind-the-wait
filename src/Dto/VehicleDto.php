<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\DirectionEnum;

final readonly class VehicleDto
{
    public function __construct(
        public string $routeId,
        public ?DirectionEnum $direction,
        public ?int $timestamp, // epoch seconds
        public ?float $lat = null,
        public ?float $lon = null,
        public ?string $tripId = null,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): ?self
    {
        // route keys we might see: "route", "route_id", "routeId"
        $route = $row['route'] ?? $row['route_id'] ?? $row['routeId'] ?? null;
        if ($route === null) {
            return null; // route is the only hard requirement
        }

        // direction keys: "direction", "direction_id", "directionId"
        $dirRaw    = $row['direction'] ?? $row['direction_id'] ?? $row['directionId'] ?? null;
        $direction = null;
        if (is_numeric($dirRaw)) {
            $direction = DirectionEnum::tryFrom((int) $dirRaw); // may still be null if out of range
        }

        // timestamp keys: "ts", "timestamp"
        $tsRaw = $row['ts'] ?? $row['timestamp'] ?? null;
        $ts    = is_numeric($tsRaw) ? (int) $tsRaw : null;

        // lat/lon
        $lat = isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : null;
        $lon = isset($row['lon']) && is_numeric($row['lon']) ? (float) $row['lon'] : null;

        // trip keys: "trip", "trip_id", "tripId"
        $trip   = $row['trip'] ?? $row['trip_id'] ?? $row['tripId'] ?? null;
        $tripId = $trip !== null ? (string) $trip : null;

        return new self((string) $route, $direction, $ts, $lat, $lon, $tripId);
    }

    /** @param array<string,mixed> $row */
    public static function fromArrayWithDirMap(array $row, array $tripDirMap): ?self
    {
        $dto = self::fromArray($row);
        if ($dto === null || $dto->direction !== null) {
            return $dto; // already good or missing route
        }

        // Try to resolve direction from trip map
        if ($dto->tripId !== null && isset($tripDirMap[$dto->tripId])) {
            $directionFromMap = DirectionEnum::tryFrom((int) $tripDirMap[$dto->tripId]);

            // Create new instance with resolved direction since properties are readonly
            return new self($dto->routeId, $directionFromMap, $dto->timestamp, $dto->lat, $dto->lon, $dto->tripId);
        }

        return $dto;
    }
}
