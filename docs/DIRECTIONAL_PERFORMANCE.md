# Directional Performance Tracking

## Executive Summary

**Problem:** Current performance metrics aggregate both directions of a route together, hiding directional performance differences. Route 16 "Eastview / City Centre" shows 13.9% on-time overall, but we cannot tell if both directions are equally poor or if one direction is dragging down the average.

**Solution:** Track and display performance metrics separately for each direction (0 = outbound, 1 = inbound) while maintaining backward compatibility with existing aggregated metrics.

**Impact:**
- Reveals infrastructure issues (one-way streets, signal priority, bottlenecks)
- Identifies time-of-day patterns (AM rush inbound vs PM rush outbound)
- Enables targeted service improvements
- Provides riders with accurate expectations per direction

**Timeline:** 2 weeks (1 week backend, 1 week frontend)

**Database Impact:** 1 new table, 1 migration, ~2x storage for daily performance records

---

## Table of Contents

1. [Problem Statement](#1-problem-statement)
2. [Current Architecture](#2-current-architecture)
3. [Proposed Solution](#3-proposed-solution)
4. [Database Schema Changes](#4-database-schema-changes)
5. [Backend Implementation](#5-backend-implementation)
6. [Frontend Implementation](#6-frontend-implementation)
7. [API Changes](#7-api-changes)
8. [Migration Strategy](#8-migration-strategy)
9. [Testing Strategy](#9-testing-strategy)
10. [Rollout Plan](#10-rollout-plan)
11. [Future Enhancements](#11-future-enhancements)

---

## 1. Problem Statement

### Current Behavior

Routes in GTFS typically operate bidirectionally with different characteristics:
- **Route 16: "Eastview / City Centre"**
  - Direction 0: Eastview → City Centre (outbound)
  - Direction 1: City Centre → Eastview (inbound)

Current aggregation (`RoutePerformanceDaily`) combines both directions:
```php
// src/Service/History/PerformanceAggregator.php:57-60
$logs = $this->arrivalLogRepo->findByRouteAndDateRange(
    $route->getId(),  // <-- ALL trips for this route, both directions
    $startOfDay,
    $endOfDay
);
```

### Hidden Insights

When Route 16 shows 13.9% on-time, we cannot answer:

1. **Directional asymmetry:** Is one direction significantly worse?
   - Example: Eastview→City Centre: 3% on-time (F grade)
   - City Centre→Eastview: 24.8% on-time (F grade)
   - Combined: 13.9% on-time (F grade) ❌ **hides the 8x difference**

2. **Infrastructure issues:** Are there directional bottlenecks?
   - One-way street congestion
   - Signal priority only on certain corridors
   - Bus stop placement (far-side vs near-side)

3. **Time-of-day patterns:** Does AM rush affect inbound more than outbound?
   - 7-9 AM: Inbound to City Centre (commuters) = heavy delays
   - 4-6 PM: Outbound from City Centre = heavy delays

4. **Schedule realism:** Is one direction over-scheduled?
   - Outbound: realistic schedule, 85% on-time
   - Inbound: unrealistic schedule, 15% on-time
   - Combined: 50% on-time ❌ **masks scheduling issue**

### User Impact

Riders need directional information:
- "How reliable is Route 16 **to downtown** during morning rush?"
- "Should I take Route 22 **from downtown** or wait for Route 16?"
- Current system cannot answer these questions accurately

---

## 2. Current Architecture

### Entity: RoutePerformanceDaily

```php
// src/Entity/RoutePerformanceDaily.php
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'route_date_unique', columns: ['route_id', 'date'])]
class RoutePerformanceDaily
{
    private Route $route;              // ManyToOne: Route
    private \DateTimeImmutable $date;  // Daily bucket

    private int $totalPredictions;
    private ?string $onTimePercentage;
    private ?int $avgDelaySec;
    private ?int $medianDelaySec;
    // ... other metrics
}
```

**Constraint:** `route_id + date` is unique → **only one record per route per day**

### Aggregation Flow

```
ArrivalLog (raw predictions)
    ↓
PerformanceAggregator::aggregateDate($date)
    ↓
RoutePerformanceDaily (daily aggregates, both directions combined)
    ↓
OverviewService::getHistoricalWorstPerformers()
    ↓
Dashboard "Historical Performance" card
```

---

## 3. Proposed Solution

### Three-Tier Metrics Hierarchy

1. **Directional Metrics** (new) → Most granular
   - `RouteDirectionalPerformanceDaily`: per route, per direction, per date
   - Unique constraint: `(route_id, direction, date)`

2. **Route Metrics** (existing) → Aggregate of both directions
   - `RoutePerformanceDaily`: per route, per date
   - Backward compatible, computed from directional metrics

3. **System Metrics** (existing) → Aggregate of all routes
   - `SystemMetricsDto`: system-wide grade

### Data Flow

```
ArrivalLog
    ↓
PerformanceAggregator::aggregateDate($date)
    ├─→ RouteDirectionalPerformanceDaily (NEW)
    │       - Direction 0: 24.8% on-time
    │       - Direction 1: 3.0% on-time
    │
    └─→ RoutePerformanceDaily (EXISTING, computed from directional)
            - Combined: 13.9% on-time
```

### Backward Compatibility

- Existing queries continue to work unchanged
- `RoutePerformanceDaily` becomes a **computed aggregate** of directional records
- Can be computed on-the-fly OR materialized for performance

---

## 4. Database Schema Changes

### New Table: `route_directional_performance_daily`

```sql
CREATE TABLE route_directional_performance_daily (
    id                      SERIAL PRIMARY KEY,

    -- Composite key: route + direction + date
    route_id                INTEGER NOT NULL REFERENCES route(id) ON DELETE CASCADE,
    direction               SMALLINT NOT NULL,  -- 0 or 1 (DirectionEnum)
    date                    DATE NOT NULL,

    -- Performance metrics (same as RoutePerformanceDaily)
    total_predictions       INTEGER NOT NULL DEFAULT 0,
    high_confidence_count   INTEGER NOT NULL DEFAULT 0,
    medium_confidence_count INTEGER NOT NULL DEFAULT 0,
    low_confidence_count    INTEGER NOT NULL DEFAULT 0,

    avg_delay_sec           INTEGER,
    median_delay_sec        INTEGER,
    on_time_percentage      NUMERIC(5,2),
    late_percentage         NUMERIC(5,2),
    early_percentage        NUMERIC(5,2),
    bunching_incidents      INTEGER NOT NULL DEFAULT 0,

    -- Weather link (same observation for both directions on same day)
    weather_observation_id  INTEGER REFERENCES weather_observation(id) ON DELETE SET NULL,

    -- Timestamps
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    CONSTRAINT route_direction_date_unique UNIQUE (route_id, direction, date)
);

-- Indexes for common queries
CREATE INDEX idx_directional_perf_route ON route_directional_performance_daily(route_id);
CREATE INDEX idx_directional_perf_date ON route_directional_performance_daily(date);
CREATE INDEX idx_directional_perf_direction ON route_directional_performance_daily(direction);
CREATE INDEX idx_directional_perf_route_date ON route_directional_performance_daily(route_id, date);
```

### Migration Strategy

**Option A: Dual Write (Recommended)**
- Keep `route_performance_daily` table
- Write to both tables during aggregation
- `route_performance_daily` becomes weighted average of directional records
- Allows gradual migration and rollback

**Option B: Pure Computed (Future)**
- Drop `route_performance_daily` table
- Compute aggregates on-the-fly from directional records
- Requires query optimization and caching

**Decision:** Use Option A for Phase 1

---

## 5. Backend Implementation

### Phase 1: Entity and Repository

#### New Entity: `RouteDirectionalPerformanceDaily`

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\Timestampable;
use App\Enum\DirectionEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores daily performance metrics for routes by direction.
 *
 * Enables directional analysis: "Route 16 inbound is 3% on-time, outbound is 25%".
 */
#[ORM\Entity(repositoryClass: \App\Repository\RouteDirectionalPerformanceDailyRepository::class)]
#[ORM\Table(name: 'route_directional_performance_daily')]
#[ORM\UniqueConstraint(name: 'route_direction_date_unique', columns: ['route_id', 'direction', 'date'])]
#[ORM\Index(columns: ['route_id'], name: 'idx_directional_perf_route')]
#[ORM\Index(columns: ['date'], name: 'idx_directional_perf_date')]
#[ORM\Index(columns: ['direction'], name: 'idx_directional_perf_direction')]
#[ORM\HasLifecycleCallbacks]
class RouteDirectionalPerformanceDaily
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column(type: Types::SMALLINT, enumType: DirectionEnum::class)]
    private DirectionEnum $direction;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalPredictions = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $highConfidenceCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $mediumConfidenceCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $lowConfidenceCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $avgDelaySec = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $medianDelaySec = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $onTimePercentage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $latePercentage = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $earlyPercentage = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $bunchingIncidents = 0;

    #[ORM\ManyToOne(targetEntity: WeatherObservation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WeatherObservation $weatherObservation = null;

    // Getters and setters (same pattern as RoutePerformanceDaily)
    // ... omitted for brevity
}
```

#### New Repository: `RouteDirectionalPerformanceDailyRepository`

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RouteDirectionalPerformanceDaily;
use App\Enum\DirectionEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RouteDirectionalPerformanceDailyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteDirectionalPerformanceDaily::class);
    }

    /**
     * Find or create a directional performance record.
     */
    public function findOrCreate(
        int $routeId,
        DirectionEnum $direction,
        \DateTimeImmutable $date
    ): RouteDirectionalPerformanceDaily {
        $existing = $this->findOneBy([
            'route'     => $routeId,
            'direction' => $direction,
            'date'      => $date,
        ]);

        if ($existing !== null) {
            return $existing;
        }

        $performance = new RouteDirectionalPerformanceDaily();
        $performance->setRoute($this->getEntityManager()->getReference(\App\Entity\Route::class, $routeId));
        $performance->setDirection($direction);
        $performance->setDate($date);

        return $performance;
    }

    /**
     * Find performance records for a route across date range, grouped by direction.
     *
     * @return array<int, list<RouteDirectionalPerformanceDaily>> Keyed by direction (0 or 1)
     */
    public function findByRouteAndDateRangeGrouped(
        int $routeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $records = $this->createQueryBuilder('p')
            ->where('p.route = :route')
            ->andWhere('p.date >= :start')
            ->andWhere('p.date < :end')
            ->setParameter('route', $routeId)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('p.date', 'ASC')
            ->getQuery()
            ->getResult();

        // Group by direction
        $grouped = [
            DirectionEnum::Outbound->value => [],
            DirectionEnum::Inbound->value  => [],
        ];

        foreach ($records as $record) {
            $grouped[$record->getDirection()->value][] = $record;
        }

        return $grouped;
    }

    /**
     * Calculate average on-time percentage by direction for a route.
     *
     * @return array{0: float|null, 1: float|null} Keyed by direction
     */
    public function getAverageOnTimeByDirection(
        int $routeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $qb = $this->createQueryBuilder('p');
        $qb->select('p.direction', 'AVG(p.onTimePercentage) as avg_on_time')
            ->where('p.route = :route')
            ->andWhere('p.date >= :start')
            ->andWhere('p.date < :end')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('route', $routeId)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('p.direction');

        $results = $qb->getQuery()->getResult();

        $avgByDirection = [0 => null, 1 => null];
        foreach ($results as $row) {
            $direction = $row['direction'];
            $avgByDirection[$direction instanceof DirectionEnum ? $direction->value : (int) $direction] = (float) $row['avg_on_time'];
        }

        return $avgByDirection;
    }

    public function save(RouteDirectionalPerformanceDaily $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

### Phase 2: Update PerformanceAggregator

Modify `src/Service/History/PerformanceAggregator.php` to aggregate by direction:

```php
<?php

declare(strict_types=1);

namespace App\Service\History;

use App\Dto\RoutePerformanceDto;
use App\Enum\DirectionEnum;
use App\Repository\ArrivalLogRepository;
use App\Repository\RouteDirectionalPerformanceDailyRepository;
use App\Repository\RoutePerformanceDailyRepository;
use App\Repository\RouteRepository;
use App\Repository\WeatherObservationRepository;
use Psr\Log\LoggerInterface;

final readonly class PerformanceAggregator
{
    private const ON_TIME_THRESHOLD_SEC = 180;

    public function __construct(
        private ArrivalLogRepository $arrivalLogRepo,
        private RoutePerformanceDailyRepository $performanceRepo,
        private RouteDirectionalPerformanceDailyRepository $directionalPerformanceRepo, // NEW
        private RouteRepository $routeRepo,
        private WeatherObservationRepository $weatherRepo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Aggregate performance metrics for all routes for a given date.
     *
     * @return array{success: int, failed: int}
     */
    public function aggregateDate(\DateTimeImmutable $date): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay   = $date->setTime(23, 59, 59);

        $weather = $this->weatherRepo->findClosestTo($date->setTime(12, 0, 0));
        $routes  = $this->routeRepo->findAll();
        $success = 0;
        $failed  = 0;

        foreach ($routes as $route) {
            try {
                // Aggregate by direction first
                $directionalMetrics = $this->aggregateByDirection($route->getId(), $startOfDay, $endOfDay, $weather);

                if (empty($directionalMetrics)) {
                    continue; // No activity for this route
                }

                // Compute combined metrics for backward compatibility
                $combinedMetrics = $this->combineDirectionalMetrics($directionalMetrics);

                // Save directional records
                foreach ($directionalMetrics as $direction => $metrics) {
                    $performance = $this->directionalPerformanceRepo->findOrCreate(
                        $route->getId(),
                        DirectionEnum::from($direction),
                        $date
                    );
                    $this->setMetrics($performance, $metrics);
                    $performance->setWeatherObservation($weather);
                    $this->directionalPerformanceRepo->save($performance, flush: true);
                }

                // Save combined record (existing table)
                $performance = $this->performanceRepo->findOrCreate($route->getId(), $date);
                $this->setMetrics($performance, $combinedMetrics);
                $performance->setWeatherObservation($weather);
                $this->performanceRepo->save($performance, flush: true);

                ++$success;

                $this->logger->info('Aggregated directional performance for route', [
                    'route_id'         => $route->getId(),
                    'route_short_name' => $route->getShortName(),
                    'date'             => $date->format('Y-m-d'),
                    'direction_0'      => $directionalMetrics[0] ?? null,
                    'direction_1'      => $directionalMetrics[1] ?? null,
                ]);
            } catch (\Exception $e) {
                ++$failed;
                $this->logger->error('Failed to aggregate performance', [
                    'route_id' => $route->getId(),
                    'date'     => $date->format('Y-m-d'),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Aggregate metrics separately for each direction.
     *
     * @return array<int, RoutePerformanceDto> Keyed by direction (0 or 1)
     */
    private function aggregateByDirection(
        int $routeId,
        \DateTimeImmutable $startOfDay,
        \DateTimeImmutable $endOfDay,
        ?\App\Entity\WeatherObservation $weather
    ): array {
        $allLogs = $this->arrivalLogRepo->findByRouteAndDateRange($routeId, $startOfDay, $endOfDay);

        if (empty($allLogs)) {
            return [];
        }

        // Group logs by direction (via trip entity)
        $logsByDirection = [0 => [], 1 => []];
        foreach ($allLogs as $log) {
            $trip = $log->getTrip();
            if ($trip === null) {
                continue; // Skip logs without trip association
            }

            $direction = $trip->getDirection();
            if ($direction !== null) {
                $logsByDirection[$direction->value][] = $log;
            }
        }

        // Calculate metrics for each direction
        $metrics = [];
        foreach ($logsByDirection as $direction => $logs) {
            if (count($logs) > 0) {
                $metrics[$direction] = $this->calculateMetrics($logs);
            }
        }

        return $metrics;
    }

    /**
     * Combine directional metrics into a single aggregate.
     *
     * Uses weighted average based on prediction counts.
     *
     * @param array<int, RoutePerformanceDto> $directionalMetrics
     */
    private function combineDirectionalMetrics(array $directionalMetrics): RoutePerformanceDto
    {
        $totalPredictions       = 0;
        $highConfidenceCount   = 0;
        $mediumConfidenceCount = 0;
        $lowConfidenceCount    = 0;
        $weightedDelaySum      = 0;
        $allDelays             = [];
        $weightedOnTime        = 0;
        $weightedLate          = 0;
        $weightedEarly         = 0;

        foreach ($directionalMetrics as $metrics) {
            $count = $metrics->totalPredictions;
            $totalPredictions       += $count;
            $highConfidenceCount   += $metrics->highConfidenceCount;
            $mediumConfidenceCount += $metrics->mediumConfidenceCount;
            $lowConfidenceCount    += $metrics->lowConfidenceCount;

            if ($metrics->avgDelaySec !== null) {
                $weightedDelaySum += $metrics->avgDelaySec * $count;
            }

            // Collect all delays for median calculation
            // Note: We don't have individual delays here, so median will be approximated
            // from directional medians (weighted by count)

            if ($metrics->onTimePercentage !== null) {
                $weightedOnTime += $metrics->onTimePercentage * $count;
            }
            if ($metrics->latePercentage !== null) {
                $weightedLate += $metrics->latePercentage * $count;
            }
            if ($metrics->earlyPercentage !== null) {
                $weightedEarly += $metrics->earlyPercentage * $count;
            }
        }

        $avgDelaySec = $totalPredictions > 0 ? (int) round($weightedDelaySum / $totalPredictions) : null;

        // For median, take weighted average of directional medians (approximation)
        $medianDelaySec = null;
        if (count($directionalMetrics) > 0) {
            $weightedMedianSum = 0;
            $medianWeight      = 0;
            foreach ($directionalMetrics as $metrics) {
                if ($metrics->medianDelaySec !== null) {
                    $weightedMedianSum += $metrics->medianDelaySec * $metrics->totalPredictions;
                    $medianWeight      += $metrics->totalPredictions;
                }
            }
            $medianDelaySec = $medianWeight > 0 ? (int) round($weightedMedianSum / $medianWeight) : null;
        }

        $onTimePercentage = $totalPredictions > 0 ? round($weightedOnTime / $totalPredictions, 2) : null;
        $latePercentage   = $totalPredictions > 0 ? round($weightedLate / $totalPredictions, 2) : null;
        $earlyPercentage  = $totalPredictions > 0 ? round($weightedEarly / $totalPredictions, 2) : null;

        return new RoutePerformanceDto(
            totalPredictions: $totalPredictions,
            highConfidenceCount: $highConfidenceCount,
            mediumConfidenceCount: $mediumConfidenceCount,
            lowConfidenceCount: $lowConfidenceCount,
            avgDelaySec: $avgDelaySec,
            medianDelaySec: $medianDelaySec,
            onTimePercentage: $onTimePercentage,
            latePercentage: $latePercentage,
            earlyPercentage: $earlyPercentage,
        );
    }

    /**
     * Helper to set metrics on a performance entity.
     */
    private function setMetrics($performance, RoutePerformanceDto $metrics): void
    {
        $performance->setTotalPredictions($metrics->totalPredictions);
        $performance->setHighConfidenceCount($metrics->highConfidenceCount);
        $performance->setMediumConfidenceCount($metrics->mediumConfidenceCount);
        $performance->setLowConfidenceCount($metrics->lowConfidenceCount);
        $performance->setAvgDelaySec($metrics->avgDelaySec);
        $performance->setMedianDelaySec($metrics->medianDelaySec);
        $performance->setOnTimePercentage($metrics->onTimePercentage !== null ? (string) $metrics->onTimePercentage : null);
        $performance->setLatePercentage($metrics->latePercentage !== null ? (string) $metrics->latePercentage : null);
        $performance->setEarlyPercentage($metrics->earlyPercentage !== null ? (string) $metrics->earlyPercentage : null);
    }

    // Keep existing calculateMetrics() method unchanged
    private function calculateMetrics(array $logs): RoutePerformanceDto
    {
        // ... existing implementation from current code
    }

    // Keep existing calculateMedian() method unchanged
    private function calculateMedian(array $values): ?int
    {
        // ... existing implementation
    }
}
```

### Phase 3: New Service for Directional Analytics

Create `src/Service/Dashboard/DirectionalAnalysisService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Repository\RouteDirectionalPerformanceDailyRepository;
use App\Repository\RouteRepository;

/**
 * Service for analyzing directional performance differences.
 */
final readonly class DirectionalAnalysisService
{
    public function __construct(
        private RouteDirectionalPerformanceDailyRepository $directionalPerfRepo,
        private RouteRepository $routeRepo,
    ) {
    }

    /**
     * Get directional performance comparison for a route.
     *
     * @return array{
     *     outbound: array{avg_on_time: float, grade: string, headsign: string},
     *     inbound: array{avg_on_time: float, grade: string, headsign: string},
     *     difference: float,
     *     worse_direction: int|null
     * }
     */
    public function getDirectionalComparison(
        int $routeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $avgByDirection = $this->directionalPerfRepo->getAverageOnTimeByDirection(
            $routeId,
            $startDate,
            $endDate
        );

        $outboundAvg = $avgByDirection[0] ?? null;
        $inboundAvg  = $avgByDirection[1] ?? null;

        // Get representative headsigns from trips
        $route = $this->routeRepo->find($routeId);
        $trips = $route?->getTrips();

        $outboundHeadsign = 'Outbound';
        $inboundHeadsign  = 'Inbound';

        if ($trips !== null) {
            foreach ($trips as $trip) {
                if ($trip->getDirection()?->value === 0 && $trip->getHeadsign() !== null) {
                    $outboundHeadsign = $trip->getHeadsign();
                    break;
                }
            }
            foreach ($trips as $trip) {
                if ($trip->getDirection()?->value === 1 && $trip->getHeadsign() !== null) {
                    $inboundHeadsign = $trip->getHeadsign();
                    break;
                }
            }
        }

        $difference = null;
        $worseDirection = null;
        if ($outboundAvg !== null && $inboundAvg !== null) {
            $difference = abs($outboundAvg - $inboundAvg);
            $worseDirection = $outboundAvg < $inboundAvg ? 0 : 1;
        }

        return [
            'outbound' => [
                'avg_on_time' => $outboundAvg,
                'grade'       => $this->onTimeToGrade($outboundAvg),
                'headsign'    => $outboundHeadsign,
            ],
            'inbound' => [
                'avg_on_time' => $inboundAvg,
                'grade'       => $this->onTimeToGrade($inboundAvg),
                'headsign'    => $inboundHeadsign,
            ],
            'difference'       => $difference,
            'worse_direction'  => $worseDirection,
        ];
    }

    private function onTimeToGrade(?float $onTimePercentage): string
    {
        if ($onTimePercentage === null) {
            return 'N/A';
        }

        return match (true) {
            $onTimePercentage >= 90 => 'A',
            $onTimePercentage >= 80 => 'B',
            $onTimePercentage >= 70 => 'C',
            $onTimePercentage >= 60 => 'D',
            default                 => 'F',
        };
    }
}
```

---

## 6. Frontend Implementation

### Phase 1: Route Detail Page Enhancement

Update `templates/route/detail.html.twig` to show directional breakdown:

```twig
{# After existing 30-day performance chart #}

{% if directional_comparison %}
<div class="card mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Directional Performance Comparison</h2>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="directional-stat">
                    <div class="direction-label">
                        {{ directional_comparison.outbound.headsign }}
                    </div>
                    <div class="grade-badge grade-{{ directional_comparison.outbound.grade }}">
                        {{ directional_comparison.outbound.grade }}
                    </div>
                    <div class="percentage">
                        {{ directional_comparison.outbound.avg_on_time|number_format(1) }}% on-time
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="directional-stat">
                    <div class="direction-label">
                        {{ directional_comparison.inbound.headsign }}
                    </div>
                    <div class="grade-badge grade-{{ directional_comparison.inbound.grade }}">
                        {{ directional_comparison.inbound.grade }}
                    </div>
                    <div class="percentage">
                        {{ directional_comparison.inbound.avg_on_time|number_format(1) }}% on-time
                    </div>
                </div>
            </div>
        </div>

        {% if directional_comparison.difference > 10 %}
        <div class="alert alert-warning mt-3">
            <strong>Significant directional difference detected:</strong>
            {{ directional_comparison.worse_direction == 0 ? directional_comparison.outbound.headsign : directional_comparison.inbound.headsign }}
            performs {{ directional_comparison.difference|number_format(1) }}% worse than the opposite direction.
        </div>
        {% endif %}
    </div>
</div>

{# Directional performance chart #}
<div class="card mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">30-Day Performance by Direction</h2>
    </div>
    <div class="card-body">
        <div
            data-controller="chart"
            data-chart-options-value="{{ directional_chart_options|json_encode }}"
            style="height: 400px;"
        ></div>
    </div>
</div>
{% endif %}
```

### Phase 2: Update RouteController

```php
<?php

// src/Controller/RouteController.php

use App\Service\Dashboard\DirectionalAnalysisService;

class RouteController extends AbstractController
{
    public function __construct(
        // ... existing dependencies
        private readonly DirectionalAnalysisService $directionalAnalysis,
    ) {
    }

    #[Route('/routes/{id}', name: 'route_detail', methods: ['GET'])]
    public function detail(Route $route): Response
    {
        // ... existing code

        // Get directional comparison
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        $directionalComparison = $this->directionalAnalysis->getDirectionalComparison(
            $route->getId(),
            $startDate,
            $endDate
        );

        // Build directional chart
        $directionalChartOptions = $this->buildDirectionalPerformanceChart(
            $route,
            $startDate,
            $endDate
        );

        return $this->render('route/detail.html.twig', [
            // ... existing variables
            'directional_comparison'    => $directionalComparison,
            'directional_chart_options' => $directionalChartOptions,
        ]);
    }

    private function buildDirectionalPerformanceChart(
        Route $route,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $groupedData = $this->directionalPerformanceRepo->findByRouteAndDateRangeGrouped(
            $route->getId(),
            $startDate,
            $endDate
        );

        $outboundData = [];
        $inboundData  = [];
        $dates        = [];

        // Build series data
        foreach ($groupedData[0] as $record) {
            $dates[]        = $record->getDate()->format('Y-m-d');
            $outboundData[] = $record->getOnTimePercentage() !== null ? (float) $record->getOnTimePercentage() : null;
        }

        foreach ($groupedData[1] as $record) {
            $inboundData[] = $record->getOnTimePercentage() !== null ? (float) $record->getOnTimePercentage() : null;
        }

        // Get headsigns
        $outboundHeadsign = 'Outbound';
        $inboundHeadsign  = 'Inbound';
        foreach ($route->getTrips() as $trip) {
            if ($trip->getDirection()?->value === 0 && $trip->getHeadsign() !== null) {
                $outboundHeadsign = $trip->getHeadsign();
                break;
            }
        }
        foreach ($route->getTrips() as $trip) {
            if ($trip->getDirection()?->value === 1 && $trip->getHeadsign() !== null) {
                $inboundHeadsign = $trip->getHeadsign();
                break;
            }
        }

        return [
            'xAxis' => [
                'type' => 'category',
                'data' => $dates,
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'On-Time %',
                'min'  => 0,
                'max'  => 100,
            ],
            'series' => [
                [
                    'name'      => $outboundHeadsign,
                    'type'      => 'line',
                    'data'      => $outboundData,
                    'smooth'    => true,
                    'itemStyle' => ['color' => '#3b82f6'], // Blue
                ],
                [
                    'name'      => $inboundHeadsign,
                    'type'      => 'line',
                    'data'      => $inboundData,
                    'smooth'    => true,
                    'itemStyle' => ['color' => '#f59e0b'], // Orange
                ],
            ],
            'tooltip' => [
                'trigger'   => 'axis',
                'formatter' => '{b}<br/>{a0}: {c0}%<br/>{a1}: {c1}%',
            ],
            'legend' => [
                'data' => [$outboundHeadsign, $inboundHeadsign],
            ],
            'grid' => [
                'left'   => '3%',
                'right'  => '4%',
                'bottom' => '3%',
                'containLabel' => true,
            ],
        ];
    }
}
```

### Phase 3: CSS Styling

```css
/* assets/styles/directional-performance.css */

.directional-stat {
    text-align: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.direction-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.grade-badge {
    display: inline-block;
    font-size: 3rem;
    font-weight: bold;
    width: 80px;
    height: 80px;
    line-height: 80px;
    border-radius: 50%;
    margin: 0.5rem auto;
}

.percentage {
    font-size: 1.25rem;
    color: #495057;
    margin-top: 0.5rem;
}

/* Grade colors */
.grade-A { background: #d4edda; color: #155724; }
.grade-B { background: #d1ecf1; color: #0c5460; }
.grade-C { background: #fff3cd; color: #856404; }
.grade-D { background: #f8d7da; color: #721c24; }
.grade-F { background: #f5c6cb; color: #721c24; }
```

---

## 7. API Changes

### New Endpoint: `/api/routes/{id}/directional-performance`

```php
<?php

// src/Controller/Api/RouteApiController.php

#[Route('/api/routes/{id}/directional-performance', name: 'api_route_directional_performance', methods: ['GET'])]
public function directionalPerformance(
    Route $route,
    Request $request,
    RouteDirectionalPerformanceDailyRepository $directionalPerfRepo
): JsonResponse {
    $days = (int) ($request->query->get('days', 30));

    $endDate   = new \DateTimeImmutable('today');
    $startDate = $endDate->modify(sprintf('-%d days', $days));

    $groupedData = $directionalPerfRepo->findByRouteAndDateRangeGrouped(
        $route->getId(),
        $startDate,
        $endDate
    );

    $outbound = array_map(fn($record) => [
        'date'           => $record->getDate()->format('Y-m-d'),
        'on_time_pct'    => $record->getOnTimePercentage() !== null ? (float) $record->getOnTimePercentage() : null,
        'avg_delay_sec'  => $record->getAvgDelaySec(),
        'total_predictions' => $record->getTotalPredictions(),
    ], $groupedData[0]);

    $inbound = array_map(fn($record) => [
        'date'           => $record->getDate()->format('Y-m-d'),
        'on_time_pct'    => $record->getOnTimePercentage() !== null ? (float) $record->getOnTimePercentage() : null,
        'avg_delay_sec'  => $record->getAvgDelaySec(),
        'total_predictions' => $record->getTotalPredictions(),
    ], $groupedData[1]);

    return $this->json([
        'route_id'   => $route->getGtfsId(),
        'short_name' => $route->getShortName(),
        'long_name'  => $route->getLongName(),
        'outbound'   => $outbound,
        'inbound'    => $inbound,
    ]);
}
```

### Example Response

```json
{
  "route_id": "16",
  "short_name": "16",
  "long_name": "Eastview / City Centre",
  "outbound": [
    {
      "date": "2025-10-01",
      "on_time_pct": 24.8,
      "avg_delay_sec": 420,
      "total_predictions": 156
    },
    ...
  ],
  "inbound": [
    {
      "date": "2025-10-01",
      "on_time_pct": 3.0,
      "avg_delay_sec": 680,
      "total_predictions": 142
    },
    ...
  ]
}
```

---

## 8. Migration Strategy

### Database Migration

```php
<?php

// migrations/Version20251018000000.php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251018000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add route_directional_performance_daily table for directional performance tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE route_directional_performance_daily (
                id                      SERIAL PRIMARY KEY,
                route_id                INTEGER NOT NULL REFERENCES route(id) ON DELETE CASCADE,
                direction               SMALLINT NOT NULL,
                date                    DATE NOT NULL,
                total_predictions       INTEGER NOT NULL DEFAULT 0,
                high_confidence_count   INTEGER NOT NULL DEFAULT 0,
                medium_confidence_count INTEGER NOT NULL DEFAULT 0,
                low_confidence_count    INTEGER NOT NULL DEFAULT 0,
                avg_delay_sec           INTEGER,
                median_delay_sec        INTEGER,
                on_time_percentage      NUMERIC(5,2),
                late_percentage         NUMERIC(5,2),
                early_percentage        NUMERIC(5,2),
                bunching_incidents      INTEGER NOT NULL DEFAULT 0,
                weather_observation_id  INTEGER REFERENCES weather_observation(id) ON DELETE SET NULL,
                created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT route_direction_date_unique UNIQUE (route_id, direction, date)
            )
        ');

        $this->addSql('CREATE INDEX idx_directional_perf_route ON route_directional_performance_daily(route_id)');
        $this->addSql('CREATE INDEX idx_directional_perf_date ON route_directional_performance_daily(date)');
        $this->addSql('CREATE INDEX idx_directional_perf_direction ON route_directional_performance_daily(direction)');
        $this->addSql('CREATE INDEX idx_directional_perf_route_date ON route_directional_performance_daily(route_id, date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS route_directional_performance_daily');
    }
}
```

### Backfill Command

Create `src/Command/BackfillDirectionalPerformanceCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\History\PerformanceAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfill directional performance data from arrival logs.
 */
#[AsCommand(
    name: 'app:backfill:directional-performance',
    description: 'Backfill directional performance metrics from arrival logs',
)]
final class BackfillDirectionalPerformanceCommand extends Command
{
    public function __construct(
        private readonly PerformanceAggregator $aggregator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to backfill', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title(sprintf('Backfilling %d days of directional performance data', $days));

        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify(sprintf('-%d days', $days));

        $totalSuccess = 0;
        $totalFailed  = 0;

        $io->progressStart($days);

        for ($date = clone $startDate; $date < $endDate; $date = $date->modify('+1 day')) {
            $result        = $this->aggregator->aggregateDate($date);
            $totalSuccess += $result['success'];
            $totalFailed  += $result['failed'];

            $io->progressAdvance();
        }

        $io->progressFinish();

        if ($totalFailed > 0) {
            $io->warning(sprintf(
                'Backfilled %d route-days successfully, %d failed. Check logs for details.',
                $totalSuccess,
                $totalFailed
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf('Successfully backfilled %d route-days of directional performance data.', $totalSuccess));

        return Command::SUCCESS;
    }
}
```

### Backfill Process

```bash
# Run migration
docker compose exec php bin/console doctrine:migrations:migrate

# Backfill 30 days of historical data
docker compose exec php bin/console app:backfill:directional-performance --days=30

# Verify data
docker compose exec php bin/console dbal:run-sql "
    SELECT
        r.short_name,
        d.direction,
        COUNT(*) as days,
        AVG(d.on_time_percentage) as avg_on_time
    FROM route_directional_performance_daily d
    JOIN route r ON d.route_id = r.id
    GROUP BY r.short_name, d.direction
    ORDER BY r.short_name, d.direction
    LIMIT 10
"
```

---

## 9. Testing Strategy

### Unit Tests

```php
<?php

// tests/Service/History/PerformanceAggregatorDirectionalTest.php

namespace App\Tests\Service\History;

use App\Service\History\PerformanceAggregator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PerformanceAggregatorDirectionalTest extends KernelTestCase
{
    public function testAggregatesDirectionalMetricsSeparately(): void
    {
        // Arrange: Create arrival logs for route with both directions
        $route = $this->createRoute('16', 'Eastview / City Centre');

        // Direction 0: 10 logs, 8 on-time (80%)
        $this->createArrivalLogs($route, direction: 0, count: 10, onTimeCount: 8);

        // Direction 1: 10 logs, 2 on-time (20%)
        $this->createArrivalLogs($route, direction: 1, count: 10, onTimeCount: 2);

        // Act
        $aggregator = static::getContainer()->get(PerformanceAggregator::class);
        $result = $aggregator->aggregateDate(new \DateTimeImmutable('today'));

        // Assert
        $this->assertEquals(1, $result['success']);

        $directionalRecords = $this->directionalPerfRepo->findBy([
            'route' => $route->getId(),
            'date'  => new \DateTimeImmutable('today'),
        ]);

        $this->assertCount(2, $directionalRecords);

        // Check direction 0 (outbound)
        $outbound = array_filter($directionalRecords, fn($r) => $r->getDirection()->value === 0)[0];
        $this->assertEquals(10, $outbound->getTotalPredictions());
        $this->assertEquals(80.0, (float) $outbound->getOnTimePercentage());

        // Check direction 1 (inbound)
        $inbound = array_filter($directionalRecords, fn($r) => $r->getDirection()->value === 1)[0];
        $this->assertEquals(10, $inbound->getTotalPredictions());
        $this->assertEquals(20.0, (float) $inbound->getOnTimePercentage());

        // Check combined record (backward compatibility)
        $combined = $this->performanceRepo->findOneBy([
            'route' => $route->getId(),
            'date'  => new \DateTimeImmutable('today'),
        ]);

        $this->assertEquals(20, $combined->getTotalPredictions());
        $this->assertEquals(50.0, (float) $combined->getOnTimePercentage()); // Weighted average
    }
}
```

### Integration Tests

```php
<?php

// tests/Controller/RouteControllerDirectionalTest.php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RouteControllerDirectionalTest extends WebTestCase
{
    public function testDetailPageShowsDirectionalBreakdown(): void
    {
        $client = static::createClient();

        // Create test data
        $route = $this->createRouteWithDirectionalPerformance();

        $crawler = $client->request('GET', '/routes/' . $route->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Directional Performance Comparison');
        $this->assertSelectorExists('.grade-badge.grade-A'); // Outbound
        $this->assertSelectorExists('.grade-badge.grade-F'); // Inbound
    }

    public function testApiReturnsDirectionalData(): void
    {
        $client = static::createClient();

        $route = $this->createRouteWithDirectionalPerformance();

        $client->request('GET', '/api/routes/' . $route->getId() . '/directional-performance?days=7');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('outbound', $data);
        $this->assertArrayHasKey('inbound', $data);
        $this->assertCount(7, $data['outbound']);
        $this->assertCount(7, $data['inbound']);
    }
}
```

---

## 10. Rollout Plan

### Week 1: Backend Implementation

**Day 1-2:** Database schema and entities
- [ ] Create migration
- [ ] Create `RouteDirectionalPerformanceDaily` entity
- [ ] Create `RouteDirectionalPerformanceDailyRepository`
- [ ] Write unit tests for entity and repository

**Day 3-4:** Aggregation logic
- [ ] Update `PerformanceAggregator` to aggregate by direction
- [ ] Implement `combineDirectionalMetrics()` weighted averaging
- [ ] Write unit tests for aggregation
- [ ] Test backward compatibility with existing `RoutePerformanceDaily`

**Day 5:** Service layer and backfill
- [ ] Create `DirectionalAnalysisService`
- [ ] Create `BackfillDirectionalPerformanceCommand`
- [ ] Run backfill on staging environment
- [ ] Validate data consistency

### Week 2: Frontend Implementation

**Day 1-2:** Route detail page
- [ ] Update `RouteController` to fetch directional data
- [ ] Create directional comparison card UI
- [ ] Build directional performance chart
- [ ] Add CSS styling

**Day 3:** API endpoints
- [ ] Create `/api/routes/{id}/directional-performance` endpoint
- [ ] Write integration tests
- [ ] Update API documentation

**Day 4:** Testing and QA
- [ ] Manual testing on staging
- [ ] Cross-browser testing (Chrome, Firefox, Safari)
- [ ] Mobile responsive testing
- [ ] Performance testing (query optimization if needed)

**Day 5:** Documentation and deployment
- [ ] Update user documentation
- [ ] Write deployment runbook
- [ ] Deploy to production
- [ ] Monitor logs and performance

---

## 11. Future Enhancements

### Phase 2: Time-of-Day Analysis

Break down directional performance by time of day:

```sql
-- Example: Peak vs off-peak by direction
SELECT
    direction,
    CASE
        WHEN EXTRACT(HOUR FROM predicted_at) BETWEEN 7 AND 9 THEN 'AM Peak'
        WHEN EXTRACT(HOUR FROM predicted_at) BETWEEN 16 AND 18 THEN 'PM Peak'
        ELSE 'Off-Peak'
    END as time_period,
    AVG(delay_sec) as avg_delay
FROM arrival_log
WHERE route_id = 16
GROUP BY direction, time_period
ORDER BY direction, time_period;
```

**Insight:** "Route 16 inbound is 15 min late during AM peak (7-9 AM) but on-time during off-peak"

### Phase 3: Stop-Level Directional Analysis

Combine with stop-level reliability (from `ENHANCED_ANALYTICS_FEATURES.md`):

```
Stop Sequence | Outbound Avg Delay | Inbound Avg Delay
------------- | ------------------ | -----------------
1             | +30 sec            | -15 sec
2             | +45 sec            | -10 sec
3             | +120 sec (!)       | +5 sec   <- Outbound bottleneck
4             | +135 sec           | +10 sec
```

**Insight:** "Stop 3 (Main St & 5th Ave) causes 90-second delay for outbound buses but not inbound"

### Phase 4: Directional Bunching Analysis

Track bunching incidents by direction:

```php
// Example: Bunching more common in one direction?
$bunchingByDirection = $bunchingRepo->countByDirection($routeId, $startDate, $endDate);
// Result: Direction 0: 45 incidents, Direction 1: 12 incidents
```

**Insight:** "Outbound bunching is 3.75x more common than inbound → investigate schedule"

### Phase 5: Predictive Alerts

Alert system operators when directional asymmetry exceeds threshold:

```php
if ($directionalDifference > 20.0) {
    $alertService->notify(
        "Route {$route->getShortName()} has {$directionalDifference}% directional performance gap. " .
        "Investigate scheduling or infrastructure issues."
    );
}
```

---

## Appendix A: Sample Queries

### Find routes with largest directional asymmetry

```sql
WITH directional_avg AS (
    SELECT
        route_id,
        direction,
        AVG(on_time_percentage) as avg_on_time
    FROM route_directional_performance_daily
    WHERE date >= CURRENT_DATE - INTERVAL '30 days'
      AND on_time_percentage IS NOT NULL
    GROUP BY route_id, direction
),
asymmetry AS (
    SELECT
        route_id,
        ABS(
            MAX(CASE WHEN direction = 0 THEN avg_on_time END) -
            MAX(CASE WHEN direction = 1 THEN avg_on_time END)
        ) as directional_difference
    FROM directional_avg
    GROUP BY route_id
)
SELECT
    r.short_name,
    r.long_name,
    a.directional_difference
FROM asymmetry a
JOIN route r ON a.route_id = r.id
ORDER BY a.directional_difference DESC
LIMIT 10;
```

### Directional performance trend over time

```sql
SELECT
    DATE_TRUNC('week', date) as week,
    direction,
    AVG(on_time_percentage) as avg_on_time
FROM route_directional_performance_daily
WHERE route_id = (SELECT id FROM route WHERE gtfs_id = '16')
  AND date >= CURRENT_DATE - INTERVAL '90 days'
GROUP BY week, direction
ORDER BY week, direction;
```

---

## Appendix B: Storage Impact

**Estimate for 30 days, 50 routes:**

Current storage:
- `route_performance_daily`: 50 routes × 30 days = 1,500 records

New storage:
- `route_directional_performance_daily`: 50 routes × 2 directions × 30 days = 3,000 records
- `route_performance_daily` (kept for compatibility): 1,500 records

**Total:** 4,500 records vs 1,500 records = **3x storage**

With PostgreSQL row size ~200 bytes:
- Current: 1,500 × 200 = 300 KB
- New: 4,500 × 200 = 900 KB

**Negligible impact** for typical transit systems (<1 MB for 30 days of data)

---

## Appendix C: Query Performance

**Indexed queries (directional):**
```sql
EXPLAIN ANALYZE
SELECT * FROM route_directional_performance_daily
WHERE route_id = 16 AND date >= '2025-09-01';
```

**Expected:** Index scan on `idx_directional_perf_route_date`, <10ms

**Aggregation query (system-wide):**
```sql
EXPLAIN ANALYZE
SELECT direction, AVG(on_time_percentage)
FROM route_directional_performance_daily
WHERE date >= CURRENT_DATE - 30
GROUP BY direction;
```

**Expected:** Index scan on `idx_directional_perf_date`, <50ms for 3,000 records

---

## Summary

This implementation provides:

✅ **Granular directional tracking** without breaking existing features
✅ **Backward compatibility** via dual-write strategy
✅ **Actionable insights** for operators and riders
✅ **Scalable architecture** for future enhancements
✅ **Minimal performance impact** (<1 MB storage, <50ms queries)

**Next Steps:**
1. Review and approve plan
2. Create Jira tickets for Week 1 and Week 2
3. Schedule kickoff meeting
4. Begin implementation with database migration
