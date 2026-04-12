<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Analytics\DateRangeDto;
use App\Service\Dashboard\AnalyticsService;
use App\Service\Dashboard\OverviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly OverviewService $overviewService,
        private readonly AnalyticsService $analyticsService,
    ) {
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $metrics = $this->overviewService->getSystemMetrics();

        return $this->render('dashboard/index.html.twig', [
            'metrics' => $metrics,
        ]);
    }

    #[Route('/analysis', name: 'app_analysis')]
    public function analysis(Request $request): Response
    {
        // Get date range from query params or session
        $preset    = $request->query->getString('preset', DateRangeDto::PRESET_LAST_30_DAYS);
        $startDate = $request->query->getString('start');
        $endDate   = $request->query->getString('end');

        if ($preset === DateRangeDto::PRESET_CUSTOM && $startDate !== '' && $endDate !== '') {
            try {
                $dateRange = DateRangeDto::custom(
                    new \DateTimeImmutable($startDate),
                    new \DateTimeImmutable($endDate.' +1 day'),
                );
            } catch (\Exception) {
                $dateRange = DateRangeDto::fromPreset(DateRangeDto::PRESET_ALL_TIME);
            }
        } else {
            $dateRange = DateRangeDto::fromPreset($preset);
        }

        // Get selected routes from query params
        $selectedRoutes = $request->query->all('routes');
        $routeIds       = array_filter(array_map('intval', $selectedRoutes));

        // Get analytics data
        $pageData = $this->analyticsService->getAnalyticsPageData($dateRange, $routeIds ?: null);

        // Get available routes for the selector
        $availableRoutes = $this->analyticsService->getAvailableRoutes($dateRange);

        return $this->render('dashboard/analytics.html.twig', [
            'pageData'         => $pageData,
            'availableRoutes'  => $availableRoutes,
            'selectedRouteIds' => $routeIds,
            'presets'          => DateRangeDto::getPresets(),
            'currentPreset'    => $dateRange->preset,
        ]);
    }

    #[Route('/live', name: 'app_live')]
    public function live(): Response
    {
        return $this->render('dashboard/live.html.twig');
    }
}
