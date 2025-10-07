<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\VehiclePunctualityLabel;
use App\Repository\VehicleFeedbackRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use function is_array;
use function is_string;
use function trim;

#[Route('/api/vehicle-feedback')]
final class VehicleFeedbackController extends AbstractController
{
    public function __construct(private readonly VehicleFeedbackRepositoryInterface $feedbackRepository)
    {
    }

    #[Route('', name: 'vehicle_feedback_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $vehicleIdRaw = $payload['vehicleId'] ?? null;
        $voteRaw      = $payload['vote']      ?? null;
        if (!is_string($vehicleIdRaw) || trim($vehicleIdRaw) === '') {
            return $this->json(['error' => 'vehicleId is required'], 422);
        }

        if (!is_string($voteRaw) || ($label = VehiclePunctualityLabel::tryFrom($voteRaw)) === null) {
            return $this->json(['error' => 'vote must be one of: ahead, on_time, late'], 422);
        }

        $summary = $this->feedbackRepository->recordVote(trim($vehicleIdRaw), $label);

        return $this->json([
            'vehicleId' => trim($vehicleIdRaw),
            'vote'      => $label->value,
            'summary'   => $summary,
        ]);
    }

    #[Route('/{vehicleId}', name: 'vehicle_feedback_summary', methods: ['GET'])]
    public function summary(string $vehicleId): JsonResponse
    {
        return $this->json([
            'vehicleId' => $vehicleId,
            'summary'   => $this->feedbackRepository->getSummary($vehicleId),
        ]);
    }
}
