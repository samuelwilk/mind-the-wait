<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\RouteMetricDto;
use App\Dto\SystemMetricsDto;
use App\Dto\WeatherDataDto;
use App\Entity\WeatherObservation;
use App\Repository\RealtimeRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\Repository\RouteRepository;
use App\Repository\WeatherObservationRepository;

use function array_slice;
use function count;

/**
 * Service for fetching system-wide overview metrics for the dashboard.
 */
final readonly class OverviewService
{
    public function __construct(
        private RealtimeRepository $realtimeRepo,
        private RouteRepository $routeRepo,
        private WeatherObservationRepository $weatherRepo,
        private RoutePerformanceDailyRepository $performanceRepo,
        private InsightGeneratorService $insightGenerator,
    ) {
    }

    /**
     * Get complete system metrics for overview dashboard.
     */
    public function getSystemMetrics(): SystemMetricsDto
    {
        // Get current realtime snapshot
        $snapshot = $this->realtimeRepo->snapshot();
        $scores   = $this->realtimeRepo->readScores();

        // Count active vehicles
        $activeVehicles = count($snapshot['vehicles'] ?? []);

        // Count total routes
        $totalRoutes = $this->routeRepo->count();

        // Calculate system-wide grade and on-time percentage from scores
        [$systemGrade, $onTimePercentage] = $this->calculateSystemGrade($scores['items']);

        // Get yesterday's performance for trend
        $changeVsYesterday = $this->calculateTrendVsYesterday();

        // Get current weather
        $currentWeather = $this->getCurrentWeather();

        // Get top performers (top 5 routes by grade)
        $topPerformers = $this->getTopPerformers($scores['items'], limit: 5);

        // Get routes needing attention (bottom 5 routes by grade)
        $needsAttention = $this->getNeedsAttention($scores['items'], limit: 5);

        // Get historical top/worst performers (last 30 days)
        $historicalTopPerformers   = $this->getHistoricalTopPerformers(days: 30, limit: 5);
        $historicalWorstPerformers = $this->getHistoricalWorstPerformers(days: 30, limit: 5);

        // Generate AI insights for dashboard cards
        $winterImpactStats  = $this->calculateWinterImpactStats();
        $tempThresholdStats = $this->calculateTemperatureThresholdStats();

        return new SystemMetricsDto(
            systemGrade: $systemGrade,
            onTimePercentage: $onTimePercentage,
            activeVehicles: $activeVehicles,
            totalRoutes: $totalRoutes,
            changeVsYesterday: $changeVsYesterday,
            currentWeather: $currentWeather,
            topPerformers: $topPerformers,
            needsAttention: $needsAttention,
            historicalTopPerformers: $historicalTopPerformers,
            historicalWorstPerformers: $historicalWorstPerformers,
            winterWeatherImpactInsight: $this->insightGenerator->generateDashboardWinterImpactCard($winterImpactStats),
            temperatureThresholdInsight: $this->insightGenerator->generateDashboardTemperatureCard($tempThresholdStats),
            timestamp: time(),
        );
    }

    /**
     * Calculate system-wide grade and on-time percentage from route scores.
     *
     * @param list<array<string,mixed>> $scores
     *
     * @return array{0: string, 1: float}
     */
    private function calculateSystemGrade(array $scores): array
    {
        if (count($scores) === 0) {
            return ['N/A', 0.0];
        }

        // Grade points: A=4, B=3, C=2, D=1, F=0
        $gradePoints = [
            'A'   => 4.0,
            'B'   => 3.0,
            'C'   => 2.0,
            'D'   => 1.0,
            'F'   => 0.0,
            'N/A' => 2.0, // Neutral for missing data
        ];

        $totalPoints   = 0.0;
        $totalVehicles = 0;

        foreach ($scores as $score) {
            $grade    = $score['grade']    ?? 'N/A';
            $vehicles = $score['vehicles'] ?? 1;

            $points = $gradePoints[$grade] ?? 2.0;

            // Weight by number of vehicles on route
            $totalPoints   += $points * $vehicles;
            $totalVehicles += $vehicles;
        }

        if ($totalVehicles === 0) {
            return ['N/A', 0.0];
        }

        // Calculate weighted average grade point
        $avgPoints = $totalPoints / $totalVehicles;

        // Convert back to letter grade
        $systemGrade = match (true) {
            $avgPoints >= 3.5 => 'A',
            $avgPoints >= 2.5 => 'B',
            $avgPoints >= 1.5 => 'C',
            $avgPoints >= 0.5 => 'D',
            default           => 'F',
        };

        // Convert to on-time percentage (A=95%, B=85%, C=75%, D=65%, F=50%)
        $onTimePercentage = match ($systemGrade) {
            'A'     => 90 + ($avgPoints - 3.5) * 10,  // 90-100%
            'B'     => 80 + ($avgPoints - 2.5) * 10,  // 80-90%
            'C'     => 70 + ($avgPoints - 1.5) * 10,  // 70-80%
            'D'     => 60 + ($avgPoints - 0.5) * 10,  // 60-70%
            'F'     => 50 + $avgPoints         * 10,           // 50-60%
            default => 0.0,
        };

        return [$systemGrade, round($onTimePercentage, 1)];
    }

    /**
     * Calculate trend vs yesterday's performance.
     *
     * @return float Percentage change (positive = improvement, negative = decline)
     */
    private function calculateTrendVsYesterday(): float
    {
        $today     = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');

        // Get all routes
        $routes = $this->routeRepo->findAll();
        if (count($routes) === 0) {
            return 0.0;
        }

        $todayTotal     = 0.0;
        $yesterdayTotal = 0.0;
        $count          = 0;

        foreach ($routes as $route) {
            $todayPerf     = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $today, $today->modify('+1 day'));
            $yesterdayPerf = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $yesterday, $today);

            if (count($todayPerf) > 0 && count($yesterdayPerf) > 0) {
                $todayTotal     += $todayPerf[0]->getOnTimePercentage()     ?? 0.0;
                $yesterdayTotal += $yesterdayPerf[0]->getOnTimePercentage() ?? 0.0;
                ++$count;
            }
        }

        if ($count === 0 || $yesterdayTotal === 0.0) {
            return 0.0;
        }

        $todayAvg     = $todayTotal     / $count;
        $yesterdayAvg = $yesterdayTotal / $count;

        return round($todayAvg - $yesterdayAvg, 1);
    }

    /**
     * Get current weather data.
     */
    private function getCurrentWeather(): ?WeatherDataDto
    {
        $observation = $this->weatherRepo->findLatest();
        if ($observation === null) {
            return null;
        }

        return $this->observationToDto($observation);
    }

    /**
     * Convert WeatherObservation entity to WeatherDataDto.
     */
    private function observationToDto(WeatherObservation $obs): WeatherDataDto
    {
        return new WeatherDataDto(
            time: \DateTimeImmutable::createFromInterface($obs->getObservedAt()),
            temperatureCelsius: (float) $obs->getTemperatureCelsius(),
            apparentTemperatureCelsius: $obs->getFeelsLikeCelsius() !== null ? (float) $obs->getFeelsLikeCelsius() : null,
            precipitationMm: $obs->getPrecipitationMm()             !== null ? (float) $obs->getPrecipitationMm() : null,
            snowfallCm: $obs->getSnowfallCm()                       !== null ? (float) $obs->getSnowfallCm() : null,
            snowDepthCm: $obs->getSnowDepthCm(),
            weatherCode: $obs->getWeatherCode() ?? 0, // Default to 0 (clear) if null
            visibilityM: $obs->getVisibilityKm()  !== null ? (float) $obs->getVisibilityKm() * 1000 : null,
            windSpeedKmh: $obs->getWindSpeedKmh() !== null ? (float) $obs->getWindSpeedKmh() : null,
        );
    }

    /**
     * Get top performing routes.
     *
     * @param list<array<string,mixed>> $scores
     *
     * @return list<RouteMetricDto>
     */
    private function getTopPerformers(array $scores, int $limit = 5): array
    {
        // Filter out N/A grades AND routes with no vehicles
        $validScores = array_filter($scores, fn (array $score) => ($score['grade'] ?? 'N/A') !== 'N/A' && ($score['vehicles'] ?? 0) > 0
        );

        if (count($validScores) === 0) {
            return [];
        }

        // Sort by grade (A > B > C > D > F), then by vehicle count (more vehicles = higher confidence)
        $gradeOrder = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1];

        usort($validScores, function ($a, $b) use ($gradeOrder) {
            $gradeA = $gradeOrder[$a['grade'] ?? 'N/A'] ?? 0;
            $gradeB = $gradeOrder[$b['grade'] ?? 'N/A'] ?? 0;

            // Primary sort: grade
            if ($gradeA !== $gradeB) {
                return $gradeB <=> $gradeA; // Descending
            }

            // Secondary sort: vehicle count (higher is better for confidence)
            $vehiclesA = $a['vehicles'] ?? 0;
            $vehiclesB = $b['vehicles'] ?? 0;

            return $vehiclesB <=> $vehiclesA;
        });

        return array_map(
            fn (array $score) => $this->scoreToRouteMetric($score),
            array_slice($validScores, 0, $limit)
        );
    }

    /**
     * Get routes needing attention.
     *
     * @param list<array<string,mixed>> $scores
     *
     * @return list<RouteMetricDto>
     */
    private function getNeedsAttention(array $scores, int $limit = 5): array
    {
        // Filter: Keep routes with grades D or F, or grade C with issues
        // Also filter out routes with no vehicles
        $validScores = array_filter($scores, function (array $score) {
            $grade    = $score['grade']    ?? 'N/A';
            $vehicles = $score['vehicles'] ?? 0;

            if ($vehicles === 0 || $grade === 'N/A') {
                return false;
            }

            // Always include D and F grades
            if ($grade === 'D' || $grade === 'F') {
                return true;
            }

            // Include grade C if it's a single-vehicle route (limited data)
            if ($grade === 'C' && $vehicles === 1) {
                return true;
            }

            return false;
        });

        if (count($validScores) === 0) {
            return [];
        }

        // Sort by grade (F > D > C) - worst first
        $gradeOrder = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1];

        usort($validScores, function ($a, $b) use ($gradeOrder) {
            $gradeA = $gradeOrder[$a['grade'] ?? 'N/A'] ?? 0;
            $gradeB = $gradeOrder[$b['grade'] ?? 'N/A'] ?? 0;

            // Primary sort: grade (worst first)
            if ($gradeA !== $gradeB) {
                return $gradeA <=> $gradeB; // Ascending (worst first)
            }

            // Secondary sort: vehicle count (fewer vehicles = more concerning for single-vehicle routes)
            $vehiclesA = $a['vehicles'] ?? 0;
            $vehiclesB = $b['vehicles'] ?? 0;

            return $vehiclesA <=> $vehiclesB;
        });

        return array_map(
            fn (array $score) => $this->scoreToRouteMetric($score, includeIssue: true),
            array_slice($validScores, 0, $limit)
        );
    }

    /**
     * Convert score array to RouteMetricDto.
     *
     * @param array<string,mixed> $score
     */
    private function scoreToRouteMetric(array $score, bool $includeIssue = false): RouteMetricDto
    {
        $routeId = $score['route_id'] ?? 'unknown';
        $route   = $this->routeRepo->findOneBy(['gtfsId' => $routeId]);

        $shortName = $route?->getShortName() ?? $routeId;
        $longName  = $route?->getLongName()  ?? 'Unknown Route';

        $grade            = $score['grade']    ?? 'N/A';
        $vehicleCount     = $score['vehicles'] ?? 0;
        $onTimePercentage = $this->gradeToOnTimePercentage($grade);

        $issue = null;
        if ($includeIssue && ($grade === 'D' || $grade === 'F')) {
            $issue = $this->identifyIssue($score);
        }

        // For single-vehicle routes with grade C, indicate limited data
        if ($vehicleCount === 1 && $grade === 'C' && ($score['observed_headway_sec'] ?? null) === null) {
            $issue = $issue ?? 'limited_data';
        }

        return new RouteMetricDto(
            routeId: $routeId,
            shortName: $shortName,
            longName: $longName,
            grade: $grade,
            onTimePercentage: $onTimePercentage,
            colour: $route?->getColour(),
            activeVehicles: $vehicleCount,
            trend: null, // TODO: Calculate trend when we have historical data
            issue: $issue,
        );
    }

    /**
     * Convert letter grade to approximate on-time percentage.
     */
    private function gradeToOnTimePercentage(string $grade): float
    {
        return match ($grade) {
            'A'     => 92.0,
            'B'     => 85.0,
            'C'     => 75.0,
            'D'     => 65.0,
            'F'     => 50.0,
            default => 0.0,
        };
    }

    /**
     * Identify issue for poorly performing route.
     *
     * @param array<string,mixed> $score
     */
    private function identifyIssue(array $score): string
    {
        $observedHeadway  = $score['observed_headway_sec']  ?? null;
        $scheduledHeadway = $score['scheduled_headway_sec'] ?? null;

        if ($observedHeadway === null || $scheduledHeadway === null) {
            return 'delays';
        }

        // If observed headway is much shorter than scheduled, it's bunching
        if ($observedHeadway < $scheduledHeadway * 0.5) {
            return 'bunching';
        }

        // If observed headway is much longer, it's gaps
        if ($observedHeadway > $scheduledHeadway * 1.5) {
            return 'gaps';
        }

        return 'delays';
    }

    /**
     * Get historical top performers based on route_performance_daily.
     *
     * @return list<RouteMetricDto>
     */
    private function getHistoricalTopPerformers(int $days = 30, int $limit = 5): array
    {
        $performers = $this->performanceRepo->findHistoricalTopPerformers($days, minDays: 3, limit: $limit);

        return array_map(
            fn ($dto) => new RouteMetricDto(
                routeId: $dto->gtfsId,
                shortName: $dto->shortName,
                longName: $dto->longName,
                grade: $dto->grade,
                onTimePercentage: $dto->avgOnTimePercent,
                colour: $dto->colour,
                activeVehicles: null,
                trend: null,
                issue: null,
            ),
            $performers
        );
    }

    /**
     * Get historical worst performers based on route_performance_daily.
     *
     * @return list<RouteMetricDto>
     */
    private function getHistoricalWorstPerformers(int $days = 30, int $limit = 5): array
    {
        $performers = $this->performanceRepo->findHistoricalWorstPerformers($days, minDays: 3, limit: $limit);

        return array_map(
            fn ($dto) => new RouteMetricDto(
                routeId: $dto->gtfsId,
                shortName: $dto->shortName,
                longName: $dto->longName,
                grade: $dto->grade,
                onTimePercentage: $dto->avgOnTimePercent,
                colour: $dto->colour,
                activeVehicles: null,
                trend: null,
                issue: null,
            ),
            $performers
        );
    }

    /**
     * Calculate winter impact statistics for dashboard card.
     *
     * @return array<string, mixed>
     */
    private function calculateWinterImpactStats(): array
    {
        $results = $this->performanceRepo->findWinterPerformanceComparison(minDays: 1, limit: 100);

        if (count($results) === 0) {
            return ['avgDrop' => 0.0];
        }

        // Calculate average drop across all routes
        $totalDrop = 0.0;
        foreach ($results as $dto) {
            $totalDrop += $dto->performanceDrop;
        }

        $avgDrop = round($totalDrop / count($results), 1);

        return [
            'avgDrop' => $avgDrop,
        ];
    }

    /**
     * Calculate temperature threshold statistics for dashboard card.
     *
     * @return array<string, mixed>
     */
    private function calculateTemperatureThresholdStats(): array
    {
        $result = $this->performanceRepo->findPerformanceByTemperatureThreshold(threshold: -20.0);

        $performanceDrop = $result['above']->avgPerformance - $result['below']->avgPerformance;

        return [
            'threshold'       => '-20',
            'performanceDrop' => round($performanceDrop, 1),
            'aboveThreshold'  => $result['above']->avgPerformance,
            'belowThreshold'  => $result['below']->avgPerformance,
        ];
    }
}
