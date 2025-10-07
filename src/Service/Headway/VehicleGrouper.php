<?php

declare(strict_types=1);

namespace App\Service\Headway;

use App\Dto\VehicleDto;

/** Groups vehicles by (routeId|direction). */
final class VehicleGrouper
{
    /** @param list<VehicleDto> $vehicles
     * @return array<string, list<VehicleDto>> key = "route|dirInt"
     */
    public function groupByRouteDirection(array $vehicles): array
    {
        $out = [];
        foreach ($vehicles as $v) {
            $key = $v->routeId.'|'.($v->direction?->value ?? 'all');
            $out[$key] ??= [];
            $out[$key][] = $v;
        }

        return $out;
    }
}
