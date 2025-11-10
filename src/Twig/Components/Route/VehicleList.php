<?php

declare(strict_types=1);

namespace App\Twig\Components\Route;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Vehicle list showing all active vehicles on route.
 */
#[AsTwigComponent('Route:VehicleList')]
final class VehicleList
{
    /**
     * @var list<\App\Dto\EnrichedVehicleDTO>
     */
    public array $vehicles = [];

    /**
     * Get vehicles sorted by ETA (soonest first).
     *
     * @return list<\App\Dto\EnrichedVehicleDTO>
     */
    public function getSortedVehicles(): array
    {
        $sorted = $this->vehicles;

        usort($sorted, function ($a, $b) {
            $etaA = $a->getEtaMinutes();
            $etaB = $b->getEtaMinutes();

            // Vehicles without ETA go to the end
            if ($etaA === null && $etaB === null) {
                return 0;
            }
            if ($etaA === null) {
                return 1;
            }
            if ($etaB === null) {
                return -1;
            }

            return $etaA <=> $etaB;
        });

        return $sorted;
    }
}
