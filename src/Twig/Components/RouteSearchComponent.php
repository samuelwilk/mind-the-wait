<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\Dashboard\RoutePerformanceService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent('RouteSearch')]
final class RouteSearchComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $sort = 'name';

    public function __construct(
        private readonly RoutePerformanceService $performanceService,
    ) {
    }

    #[ExposeInTemplate('routes')]
    public function getRoutes(): array
    {
        return $this->performanceService->getRouteListWithMetrics($this->search, $this->sort);
    }
}
