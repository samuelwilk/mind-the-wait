<?php

namespace App\Controller;

use App\Repository\RealtimeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class ScoreController extends AbstractController
{
    public function __construct(private readonly RealtimeRepository $realtimeRepository)
    {
    }

    #[Route('/api/score', name: 'api_score', methods: ['GET'])]
    public function score(): JsonResponse
    {
        $data = $this->realtimeRepository->readScores();

        return $this->json($data);
    }
}
