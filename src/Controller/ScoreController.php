<?php

namespace App\Controller;

use App\Repository\RealtimeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ScoreController extends AbstractController
{
    public function __construct(private readonly RealtimeRepository $realtimeRepository)
    {
    }

    /**
     * Get headway scores for routes.
     *
     * Query parameters:
     * - city: City slug (optional, defaults to 'saskatoon')
     *
     * @param Request $request HTTP request
     */
    #[Route('/api/score', name: 'api_score', methods: ['GET'])]
    public function score(Request $request): JsonResponse
    {
        $citySlug = $request->query->get('city', 'saskatoon');
        $data     = $this->realtimeRepository->readScores($citySlug);

        return $this->json($data);
    }
}
