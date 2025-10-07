<?php

namespace App\Service;

use function array_key_exists;
use function is_scalar;

final class GtfsFeatureAdapter
{
    private array $attrs;
    private array $geometry;

    public function __construct(array $feature)
    {
        foreach ($feature['attributes'] ?? [] as $k => $v) {
            $this->attrs[strtolower($k)] = $v;
        }
        $this->geometry = (array) ($feature['geometry'] ?? []);
    }

    // --- ROUTE ---
    public function routeId(): string
    {
        return $this->firstNonEmpty(['route_id', 'routeid']);
    }

    public function routeShortName(): ?string
    {
        return $this->firstAvailable(['route_short_name', 'routeshortname']);
    }

    public function routeLongName(): ?string
    {
        return $this->firstAvailable(['route_long_name', 'routelongname']);
    }

    public function routeColor(): ?string
    {
        return $this->firstAvailable(['route_color', 'routecolor']);
    }

    public function routeType(): ?int
    {
        return $this->intOrNull($this->firstAvailable(['route_type']));
    }

    // --- STOP ---
    public function stopId(): string
    {
        return $this->firstNonEmpty(['stop_id', 'stopid']);
    }

    public function stopName(): string
    {
        return $this->firstAvailable(['stop_name', 'stopname'], '');
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function latLon(): array
    {
        if (isset($this->geometry['x'], $this->geometry['y'])) {
            return [(float) $this->geometry['y'], (float) $this->geometry['x']];
        }

        return $this->latLonFromAttrs();
    }

    private function latLonFromAttrs(): array
    {
        return [
            (float) $this->firstAvailable(['stop_lat', 'lat', 'latitude'], 0.0),
            (float) $this->firstAvailable(['stop_lon', 'lon', 'longitude'], 0.0),
        ];
    }

    // --- TRIP ---
    public function tripId(): string
    {
        return $this->firstNonEmpty(['trip_id', 'tripid']);
    }

    public function tripHeadsign(): ?string
    {
        return $this->firstAvailable(['trip_headsign', 'tripheadsign']);
    }

    public function serviceId(): ?string
    {
        return $this->firstAvailable(['service_id', 'serviceid']);
    }

    public function directionId(): int
    {
        return $this->intOrZero($this->firstAvailable(['direction_id', 'directionid']));
    }

    public function tripRouteId(): string
    {
        return $this->firstNonEmpty(['route_id', 'routeid']);
    }

    // --- STOP TIME ---
    public function stopSequence(): int
    {
        return $this->intOrZero($this->firstAvailable(['stop_sequence', 'stopsequence']));
    }

    public function arrivalTime(): ?string
    {
        return $this->firstAvailable(['arrival_time', 'arrivaltime']);
    }

    public function departureTime(): ?string
    {
        return $this->firstAvailable(['departure_time', 'departuretime']);
    }

    // --- Helpers ---
    private function firstAvailable(array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->attrs)) {
                return $this->attrs[$key];
            }
        }

        return $default;
    }

    private function firstNonEmpty(array $keys): string
    {
        foreach ($keys as $key) {
            $val = $this->attrs[$key] ?? null;
            if (is_scalar($val) && trim((string) $val) !== '') {
                return (string) $val;
            }
        }

        return '';
    }

    private function intOrNull(mixed $val): ?int
    {
        return is_numeric($val) ? (int) $val : null;
    }

    private function intOrZero(mixed $val): int
    {
        return is_numeric($val) ? (int) $val : 0;
    }
}
