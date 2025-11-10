<?php

declare(strict_types=1);

namespace App\Twig\Components\Route;

use App\Dto\CountsDTO;
use App\Dto\HeadwayDTO;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Route header showing headway and vehicle counts.
 */
#[AsTwigComponent('Route:Header')]
final class Header
{
    public HeadwayDTO $headway;
    public CountsDTO $counts;
}
