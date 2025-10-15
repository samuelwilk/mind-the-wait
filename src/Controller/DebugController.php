<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ArrivalLog;
use App\Entity\BunchingIncident;
use App\Entity\Route as RouteEntity;
use App\Entity\RoutePerformanceDaily;
use App\Entity\Stop;
use App\Entity\Trip;
use App\Entity\WeatherObservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Debug endpoints for checking database state.
 *
 * ⚠️ SECURITY WARNING - TEMPORARY ENDPOINT ⚠️
 *
 * This endpoint is publicly accessible and exposes database statistics.
 * While it does NOT expose sensitive data (credentials, tokens, full records),
 * it should be REMOVED before final production release.
 *
 * Security measures in place:
 * - Only exposes counts, not full records
 * - Latest records show only safe, non-sensitive fields
 * - No credentials or tokens exposed
 * - Comprehensive security tests verify safe output
 *
 * @see DebugControllerTest for security validation
 */
#[Route('/api/debug')]
final class DebugController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Get database table counts and basic stats.
     *
     * GET /api/debug/database-stats
     */
    #[Route('/database-stats', methods: ['GET'])]
    public function databaseStats(): JsonResponse
    {
        $weatherRepo     = $this->em->getRepository(WeatherObservation::class);
        $performanceRepo = $this->em->getRepository(RoutePerformanceDaily::class);
        $arrivalLogRepo  = $this->em->getRepository(ArrivalLog::class);
        $bunchingRepo    = $this->em->getRepository(BunchingIncident::class);

        // Get counts
        $stats = [
            'gtfs_static' => [
                'routes' => $this->em->getRepository(RouteEntity::class)->count([]),
                'stops'  => $this->em->getRepository(Stop::class)->count([]),
                'trips'  => $this->em->getRepository(Trip::class)->count([]),
            ],
            'historical_data' => [
                'arrival_logs'            => $arrivalLogRepo->count([]),
                'route_performance_daily' => $performanceRepo->count([]),
                'bunching_incidents'      => $bunchingRepo->count([]),
                'weather_observations'    => $weatherRepo->count([]),
            ],
        ];

        // Get latest weather observation if any exist
        if ($stats['historical_data']['weather_observations'] > 0) {
            $latestWeather = $weatherRepo->findOneBy([], ['observedAt' => 'DESC']);
            if ($latestWeather !== null) {
                $stats['latest_weather'] = [
                    'observed_at' => $latestWeather->getObservedAt()->format('Y-m-d H:i:s'),
                    'temperature' => $latestWeather->getTemperatureCelsius(),
                    'condition'   => $latestWeather->getWeatherCondition(),
                    'impact'      => $latestWeather->getTransitImpact()->value,
                ];
            }
        }

        // Get latest performance record if any exist
        if ($stats['historical_data']['route_performance_daily'] > 0) {
            $latestPerformance = $performanceRepo->findOneBy([], ['date' => 'DESC']);
            if ($latestPerformance !== null) {
                $stats['latest_performance'] = [
                    'date'               => $latestPerformance->getDate()->format('Y-m-d'),
                    'route_short_name'   => $latestPerformance->getRoute()->getShortName(),
                    'total_predictions'  => $latestPerformance->getTotalPredictions(),
                    'on_time_percentage' => (float) $latestPerformance->getOnTimePercentage(),
                ];
            }
        }

        // Get date range of arrival logs if any exist
        if ($stats['historical_data']['arrival_logs'] > 0) {
            $connection = $this->em->getConnection();
            $result     = $connection->executeQuery(
                'SELECT MIN(predicted_at) as earliest, MAX(predicted_at) as latest FROM arrival_log'
            )->fetchAssociative();

            if ($result !== false) {
                $stats['arrival_logs_date_range'] = [
                    'earliest' => $result['earliest'],
                    'latest'   => $result['latest'],
                ];
            }
        }

        $stats['_note'] = '⚠️ This is a temporary debug endpoint. Will be removed.';

        return $this->json($stats);
    }
}
