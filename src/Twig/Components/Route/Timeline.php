<?php

declare(strict_types=1);

namespace App\Twig\Components\Route;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Stop timeline showing approaching vehicles for each stop.
 */
#[AsTwigComponent('Route:Timeline')]
final class Timeline
{
    /**
     * @var list<\App\Dto\StopDTO>
     */
    public array $stops = [];
}
