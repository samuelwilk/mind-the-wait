<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }

    #[Route('/routes', name: 'app_routes')]
    public function routes(): Response
    {
        return $this->render('dashboard/routes.html.twig');
    }

    #[Route('/weather', name: 'app_weather')]
    public function weather(): Response
    {
        return $this->render('dashboard/weather.html.twig');
    }

    #[Route('/analysis', name: 'app_analysis')]
    public function analysis(): Response
    {
        return $this->render('dashboard/analysis.html.twig');
    }

    #[Route('/live', name: 'app_live')]
    public function live(): Response
    {
        return $this->render('dashboard/live.html.twig');
    }
}
