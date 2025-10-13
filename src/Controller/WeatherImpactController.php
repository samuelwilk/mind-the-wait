<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Dashboard\WeatherAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for weather impact analysis page.
 *
 * Displays comprehensive insights about how weather conditions affect
 * transit performance, including winter operations, temperature thresholds,
 * and weather-specific route vulnerabilities.
 */
final class WeatherImpactController extends AbstractController
{
    public function __construct(
        private readonly WeatherAnalysisService $weatherAnalysisService,
    ) {
    }

    #[Route('/weather', name: 'app_weather')]
    public function index(): Response
    {
        // Get all weather impact insights
        $insights = $this->weatherAnalysisService->getWeatherImpactInsights();

        return $this->render('dashboard/weather.html.twig', [
            'insights' => $insights,
        ]);
    }
}
