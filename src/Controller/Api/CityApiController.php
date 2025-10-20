<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\CityRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cities', name: 'api_v1_cities_')]
final class CityApiController extends AbstractController
{
    public function __construct(
        private readonly CityRepository $cityRepo,
    ) {
    }

    /**
     * Get list of all active cities for iOS app city picker.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        description: 'Returns all active cities for multi-city support',
        summary: 'List cities',
        tags: ['Cities']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of active cities',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'cities',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Saskatoon'),
                            new OA\Property(property: 'slug', type: 'string', example: 'saskatoon'),
                            new OA\Property(property: 'country', type: 'string', example: 'CA'),
                            new OA\Property(property: 'center_lat', type: 'number', format: 'float', example: 52.1332),
                            new OA\Property(property: 'center_lon', type: 'number', format: 'float', example: -106.6700),
                            new OA\Property(property: 'zoom_level', type: 'integer', example: 12),
                        ]
                    )
                ),
            ]
        )
    )]
    public function list(): JsonResponse
    {
        $cities = $this->cityRepo->findActiveCities();

        return $this->json([
            'cities' => array_map(fn ($c) => [
                'id'         => $c->getId(),
                'name'       => $c->getName(),
                'slug'       => $c->getSlug(),
                'country'    => $c->getCountry(),
                'center_lat' => $c->getCenterLat(),
                'center_lon' => $c->getCenterLon(),
                'zoom_level' => $c->getZoomLevel(),
            ], $cities),
        ], headers: [
            'Cache-Control' => 'public, max-age=86400', // 24 hours
        ]);
    }
}
