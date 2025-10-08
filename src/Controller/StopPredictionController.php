<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\StopRepository;
use App\Service\Prediction\ArrivalPredictorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use function time;

#[Route('/api/stops')]
final class StopPredictionController extends AbstractController
{
    public function __construct(
        private readonly ArrivalPredictorInterface $arrivalPredictor,
        private readonly StopRepository $stopRepo,
    ) {
    }

    #[Route('/{stopId}/predictions', name: 'stop_predictions', methods: ['GET'])]
    public function predictions(string $stopId, Request $request): JsonResponse
    {
        $stop = $this->stopRepo->findOneByGtfsId($stopId);
        if ($stop === null) {
            return $this->json(['error' => 'Stop not found'], 404);
        }

        $limit   = $request->query->getInt('limit', 10);
        $routeId = $request->query->get('route');

        $predictions = $this->arrivalPredictor->predictArrivalsForStop(
            stopId: $stopId,
            limit: $limit,
            routeId: $routeId
        );

        $now = time();

        return $this->json([
            'stop_id'     => $stopId,
            'stop_name'   => $stop->getName(),
            'predictions' => array_map(fn ($p) => $p->toArray($now), $predictions),
        ]);
    }
}
