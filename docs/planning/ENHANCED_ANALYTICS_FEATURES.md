# Enhanced Route Analytics Features

> **📋 STATUS: PLANNING** | This document describes 7 analytics features that have NOT been implemented yet.
>
> **Implementation Status:** Not started (documentation updated with coding standards on 2025-10-19)
> **Priority:** Medium (nice-to-have diagnostic tools)
> **Estimated Effort:** 3 weeks total (7 features × ~2-3 days each)
> **Last Updated:** 2025-10-19

## Overview

This document outlines the implementation plan for seven new route analytics features that transform mind-the-wait from basic headway monitoring into a comprehensive transit performance diagnostic tool.

**Philosophy:** The value of mind-the-wait.ca isn't just pattern-finding — it's pattern-making visible. Even if the data is noisy or early, the site can structure the questions that the city, riders, or planners can later test.

**Goal:** Build the scaffolding for insight with features that provide actionable value even with limited data (1 day of GTFS-RT).

---

## Coding Standards (Updated 2025-10-19)

All implementations must follow these established patterns:

### 1. Use DTOs (Data Transfer Objects)
```php
// ✅ GOOD: Readonly DTO with typed properties
final readonly class StopReliabilityDataDto
{
    public function __construct(
        public string $stopName,
        public int $stopSequence,
        public float $avgDelay,
        public float $delayVariance,
        public int $sampleSize,
    ) {}
}

// ❌ BAD: Raw associative arrays
$data = ['stop_name' => 'foo', 'avg_delay' => 120];
```

### 2. Use Enums Instead of String Literals
```php
// ✅ GOOD: Type-safe enum
enum ScheduleRealismGrade: string
{
    case SEVERELY_UNDER_SCHEDULED = 'Severely Under-scheduled';
    case UNDER_SCHEDULED = 'Under-scheduled';
    case REALISTIC = 'Realistic';
    case OVER_SCHEDULED = 'Over-scheduled';
    case SEVERELY_OVER_SCHEDULED = 'Severely Over-scheduled (excessive padding)';
    case UNKNOWN = 'Unknown';
}

// ❌ BAD: Magic strings
return 'Severely Under-scheduled';
```

### 3. Repository Pattern (No Queries in Services)
```php
// ✅ GOOD: Query logic in repository
class ArrivalLogRepository
{
    public function findStopReliabilityData(
        int $routeId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        // SQL query here
    }
}

class RoutePerformanceService
{
    public function buildChart(Route $route): Chart
    {
        $data = $this->arrivalLogRepo->findStopReliabilityData(...);
        return $this->chartBuilder->build($data);
    }
}

// ❌ BAD: SQL in service
class RoutePerformanceService
{
    private function buildChart()
    {
        $conn = $this->em->getConnection();
        $sql = "SELECT ..."; // Don't do this!
    }
}
```

### 4. Use ChartBuilder and Chart Value Objects
```php
// ✅ GOOD: Fluent ChartBuilder API
return ChartBuilder::line()
    ->title('Stop-Level Reliability')
    ->categoryXAxis($stopNames)
    ->valueYAxis('Delay (seconds)')
    ->addSeries('Average Delay', $avgDelays)
    ->build();

// ❌ BAD: Raw ECharts arrays
return [
    'title' => ['text' => 'Stop-Level Reliability'],
    'xAxis' => ['type' => 'category', 'data' => $stopNames],
    // ... hundreds of lines of array configuration
];
```

### 5. Readonly Properties
```php
// ✅ GOOD: Immutable DTOs
final readonly class RouteDetailDto
{
    public function __construct(
        public Chart $performanceTrendChart,
        public Chart $stopReliabilityChart,
        public array $stats,
    ) {}
}

// ❌ BAD: Mutable properties
class RouteDetailDto
{
    public array $charts; // Can be modified
}
```

### 6. Typed Returns
```php
// ✅ GOOD: Explicit return types
public function getScheduleRealismGrade(float $ratio): ScheduleRealismGrade
{
    return match (true) {
        $ratio >= 1.15 => ScheduleRealismGrade::SEVERELY_UNDER_SCHEDULED,
        // ...
    };
}

// ❌ BAD: No type hints
public function getGrade($ratio)
{
    return 'Under-scheduled';
}
```

---

## Current State Assessment

### Existing Infrastructure

**Data Models:**
- ✅ `ArrivalLog` entity: Stores individual arrival predictions with route, stop, trip, delay_sec, predicted_at (~30K rows/day)
- ✅ `RoutePerformanceDaily` entity: Daily aggregated metrics (avg/median delay, on-time %)
- ✅ `BunchingIncident` entity: Schema exists but not actively populated
- ✅ `StopTime` entity: Static GTFS schedule data (stop_sequence, arrival_time, departure_time)

**Current Route Detail Page Features:**
- 30-day performance trend chart
- Weather impact comparison chart
- Time-of-day heatmap (day of week × hour bucket)
- Summary statistics (avg/best/worst performance, grade)

**Data Collection Pipeline:**
```
CollectArrivalLogsCommand (every 2-3 min)
    ↓
ArrivalLog table (~30K rows/day)
    ↓
CollectDailyPerformanceCommand (nightly)
    ↓
RoutePerformanceDaily table (aggregated metrics)
```

**Available Data Points:**
- arrival_log: route_id, stop_id, trip_id, delay_sec, predicted_at, scheduled_arrival_at
- stop_time: stop_sequence, arrival_time, departure_time
- Realtime vehicle positions in Redis

---

## Feature 1: Stop-Level Reliability Map

### Objective
Show which stops along a route consistently cause delays or recoveries.

### Business Value
- Identifies bottleneck locations for traffic engineering
- Shows where delays originate vs. where they recover
- Actionable even with 1 day of data (geographic patterns are stable)

### Implementation

#### 1. Create DTO

**File:** `src/Dto/StopReliabilityDataDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Stop-level reliability metrics for a single stop on a route.
 */
final readonly class StopReliabilityDataDto
{
    public function __construct(
        public string $stopName,
        public int $stopSequence,
        public float $avgDelay,
        public float $delayVariance,
        public int $sampleSize,
        public int $lateCount,
        public int $onTimeCount,
    ) {}
}
```

#### 2. Add Repository Method

**File:** `src/Repository/ArrivalLogRepository.php`

```php
/**
 * Find stop-level reliability metrics for a route.
 *
 * @return list<StopReliabilityDataDto>
 */
public function findStopReliabilityData(
    int $routeId,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array {
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            s.name as stop_name,
            st.stop_sequence,
            AVG(al.delay_sec) as avg_delay,
            STDDEV(al.delay_sec) as delay_variance,
            COUNT(*) as sample_size,
            SUM(CASE WHEN al.delay_sec > 180 THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN al.delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time_count
        FROM arrival_log al
        JOIN stop_time st ON al.trip_id = st.trip_id AND al.stop_id = st.stop_id
        JOIN stop s ON st.stop_id = s.id
        WHERE al.route_id = :route_id
          AND al.predicted_at >= :start_date
          AND al.predicted_at < :end_date
          AND al.delay_sec IS NOT NULL
        GROUP BY s.name, st.stop_sequence
        HAVING COUNT(*) >= 10
        ORDER BY st.stop_sequence ASC
    SQL;

    $results = $conn->executeQuery($sql, [
        'route_id'   => $routeId,
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date'   => $endDate->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();

    return array_map(
        fn (array $row) => new StopReliabilityDataDto(
            stopName: (string) $row['stop_name'],
            stopSequence: (int) $row['stop_sequence'],
            avgDelay: (float) $row['avg_delay'],
            delayVariance: (float) ($row['delay_variance'] ?? 0),
            sampleSize: (int) $row['sample_size'],
            lateCount: (int) $row['late_count'],
            onTimeCount: (int) $row['on_time_count'],
        ),
        $results
    );
}
```

#### 3. Create Chart Preset

**File:** `src/ValueObject/Chart/StopReliabilityChartPreset.php`

```php
<?php

declare(strict_types=1);

namespace App\ValueObject\Chart;

use App\Dto\StopReliabilityDataDto;

/**
 * Chart presets for stop-level reliability visualization.
 */
final class StopReliabilityChartPreset
{
    /**
     * Create stop reliability chart showing delay by stop sequence.
     *
     * @param list<StopReliabilityDataDto> $data
     */
    public static function create(array $data): Chart
    {
        $stopNames = [];
        $avgDelays = [];
        $variances = [];

        foreach ($data as $stop) {
            $stopNames[] = $stop->stopName;
            $avgDelays[] = round($stop->avgDelay, 0);
            $variances[] = round($stop->delayVariance, 0);
        }

        return ChartBuilder::line()
            ->title('Stop-Level Reliability', 'Average delay by stop (larger points = more variable)')
            ->categoryXAxis($stopNames, ['rotate' => 45, 'interval' => 0])
            ->valueYAxis('Delay (seconds)', min: null, max: null)
            ->addSeries('Average Delay', $avgDelays, [
                'smooth'    => false,
                'lineStyle' => ['width' => 2],
                'itemStyle' => ['color' => '#0284c7'],
                // Size points by variance (clamped to 5-20)
                'symbolSize' => 'function (value, params) {
                    var variances = ' . json_encode($variances) . ';
                    var variance = variances[params.dataIndex] || 0;
                    return Math.min(20, Math.max(5, variance / 10));
                }',
            ])
            ->grid(['left' => '40', 'right' => '4%', 'top' => '80', 'bottom' => '20%'])
            ->build();
    }
}
```

#### 4. Update Service

**File:** `src/Service/Dashboard/RoutePerformanceService.php`

```php
public function getRouteDetail(Route $route): RouteDetailDto
{
    $endDate   = new \DateTimeImmutable('today');
    $startDate = $endDate->modify('-30 days');

    // Build charts using presets and repository data
    $performanceTrend = $this->buildPerformanceTrendChart($route, $startDate, $endDate);
    $weatherImpact    = $this->buildWeatherImpactChart($route, $startDate, $endDate);
    $timeOfDay        = $this->buildTimeOfDayHeatmap($route, $startDate, $endDate);

    // NEW: Stop-level reliability
    $stopReliabilityData = $this->arrivalLogRepo->findStopReliabilityData(
        $route->getId(),
        $startDate,
        $endDate
    );
    $stopReliability = StopReliabilityChartPreset::create($stopReliabilityData);

    $stats = $this->buildStats($route, $startDate, $endDate);

    return new RouteDetailDto(
        performanceTrendChart: $performanceTrend,
        weatherImpactChart: $weatherImpact,
        timeOfDayHeatmap: $timeOfDay,
        stopReliabilityChart: $stopReliability, // NEW
        stats: $stats,
    );
}
```

#### 5. Update RouteDetailDto

**File:** `src/Dto/RouteDetailDto.php`

```php
final readonly class RouteDetailDto
{
    public function __construct(
        public Chart $performanceTrendChart,
        public Chart $weatherImpactChart,
        public Chart $timeOfDayHeatmap,
        public Chart $stopReliabilityChart,  // NEW
        public array $stats,
    ) {}
}
```

#### 6. Update Template

**File:** `templates/route/detail.html.twig`

```twig
{# Add after existing charts #}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-6 mb-8">
    <div
        data-controller="chart"
        data-chart-options-value="{{ routeDetail.stopReliabilityChart.toJson()|e('html_attr') }}"
        style="width: 100%; height: 350px; min-height: 350px; position: relative; z-index: 1;">
    </div>
</div>
```

#### 7. Database Migration

**File:** `migrations/VersionXXX_AddStopReliabilityIndexes.php`

```php
public function up(Schema $schema): void
{
    $this->addSql('
        CREATE INDEX idx_arrival_log_route_stop
        ON arrival_log (route_id, stop_id, predicted_at)
    ');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX IF EXISTS idx_arrival_log_route_stop');
}
```

---

## Feature 2: Delay Propagation Visualization

### Objective
Show how delays compound or self-correct along the route over the course of trips.

### Implementation

#### 1. Create DTO

**File:** `src/Dto/DelayPropagationDataDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Delay propagation metric for a specific stop sequence and hour combination.
 */
final readonly class DelayPropagationDataDto
{
    public function __construct(
        public int $stopSequence,
        public int $hour,
        public float $avgDelayDelta,
        public int $sampleSize,
    ) {}
}
```

#### 2. Add Repository Method

**File:** `src/Repository/ArrivalLogRepository.php`

```php
/**
 * Find delay propagation data (change in delay between consecutive stops).
 *
 * @return list<DelayPropagationDataDto>
 */
public function findDelayPropagationData(
    int $routeId,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array {
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        WITH trip_delays AS (
            SELECT
                al.trip_id,
                st.stop_sequence,
                al.delay_sec,
                EXTRACT(HOUR FROM al.predicted_at) as hour,
                LAG(al.delay_sec) OVER (
                    PARTITION BY al.trip_id
                    ORDER BY st.stop_sequence
                ) as prev_delay
            FROM arrival_log al
            JOIN stop_time st ON al.trip_id = st.trip_id AND al.stop_id = st.stop_id
            WHERE al.route_id = :route_id
              AND al.predicted_at >= :start_date
              AND al.predicted_at < :end_date
              AND al.delay_sec IS NOT NULL
        )
        SELECT
            stop_sequence,
            hour,
            AVG(delay_sec - COALESCE(prev_delay, 0)) as avg_delay_delta,
            COUNT(*) as sample_size
        FROM trip_delays
        WHERE prev_delay IS NOT NULL
        GROUP BY stop_sequence, hour
        HAVING COUNT(*) >= 5
        ORDER BY hour, stop_sequence
    SQL;

    $results = $conn->executeQuery($sql, [
        'route_id'   => $routeId,
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date'   => $endDate->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();

    return array_map(
        fn (array $row) => new DelayPropagationDataDto(
            stopSequence: (int) $row['stop_sequence'],
            hour: (int) $row['hour'],
            avgDelayDelta: (float) $row['avg_delay_delta'],
            sampleSize: (int) $row['sample_size'],
        ),
        $results
    );
}
```

#### 3. Create Chart Preset

**File:** `src/ValueObject/Chart/DelayPropagationChartPreset.php`

```php
<?php

declare(strict_types=1);

namespace App\ValueObject\Chart;

use App\Dto\DelayPropagationDataDto;

final class DelayPropagationChartPreset
{
    /**
     * Create heatmap showing delay propagation (change in delay between stops).
     *
     * @param list<DelayPropagationDataDto> $data
     */
    public static function create(array $data): Chart
    {
        // Build result map indexed by [hour][stopSequence]
        $resultMap = [];
        foreach ($data as $row) {
            $resultMap[$row->hour][$row->stopSequence] = round($row->avgDelayDelta, 0);
        }

        // Determine max stop sequence
        $maxStopSeq = count($resultMap) > 0
            ? max(array_map('max', array_map('array_keys', $resultMap)))
            : 20;

        // Fill heatmap data (24 hours × max stop sequence)
        $heatmapData = [];
        for ($h = 0; $h < 24; ++$h) {
            for ($s = 1; $s <= $maxStopSeq; ++$s) {
                $value = $resultMap[$h][$s] ?? null;
                $heatmapData[] = [$s - 1, $h, $value]; // X=stop, Y=hour, Value=delta
            }
        }

        return ChartBuilder::heatmap()
            ->title('Delay Propagation Pattern', 'How delays change between stops (red = delays growing, green = recovering)')
            ->categoryXAxis(range(1, $maxStopSeq), ['splitArea' => ['show' => true]])
            ->categoryYAxis(range(0, 23), ['splitArea' => ['show' => true]])
            ->addSeries('Delay Delta', $heatmapData)
            ->visualMap([
                'min'        => -60,
                'max'        => 60,
                'calculable' => true,
                'orient'     => 'horizontal',
                'left'       => 'center',
                'bottom'     => '0%',
                'inRange'    => [
                    'color' => ['#10b981', '#fbbf24', '#f97316', '#dc2626'],
                ],
            ])
            ->grid(['height' => '60%', 'top' => '80'])
            ->build();
    }
}
```

---

## Feature 3: Schedule Realism Index

### Objective
Identify routes with chronic under-scheduling or over-scheduling (excessive padding).

### Implementation

#### 1. Create Enum

**File:** `src/Enum/ScheduleRealismGrade.php`

```php
<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Schedule realism grades based on actual vs scheduled travel time ratio.
 */
enum ScheduleRealismGrade: string
{
    case SEVERELY_UNDER_SCHEDULED = 'Severely Under-scheduled';
    case UNDER_SCHEDULED = 'Under-scheduled';
    case REALISTIC = 'Realistic';
    case OVER_SCHEDULED = 'Over-scheduled';
    case SEVERELY_OVER_SCHEDULED = 'Severely Over-scheduled (excessive padding)';
    case UNKNOWN = 'Unknown';

    /**
     * Get grade from ratio of actual to scheduled time.
     */
    public static function fromRatio(?float $ratio): self
    {
        if ($ratio === null) {
            return self::UNKNOWN;
        }

        return match (true) {
            $ratio >= 1.15 => self::SEVERELY_UNDER_SCHEDULED,
            $ratio >= 1.10 => self::UNDER_SCHEDULED,
            $ratio >= 0.95 => self::REALISTIC,
            $ratio >= 0.85 => self::OVER_SCHEDULED,
            default        => self::SEVERELY_OVER_SCHEDULED,
        };
    }

    /**
     * Get color class for Tailwind CSS.
     */
    public function getColorClass(): string
    {
        return match ($this) {
            self::REALISTIC => 'text-success-600',
            self::UNDER_SCHEDULED, self::SEVERELY_UNDER_SCHEDULED => 'text-danger-600',
            self::OVER_SCHEDULED, self::SEVERELY_OVER_SCHEDULED => 'text-warning-600',
            self::UNKNOWN => 'text-gray-400',
        };
    }
}
```

#### 2. Add Entity Property

**File:** `src/Entity/RoutePerformanceDaily.php`

```php
#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
private ?string $scheduleRealismRatio = null;

public function getScheduleRealismRatio(): ?float
{
    return $this->scheduleRealismRatio !== null
        ? (float) $this->scheduleRealismRatio
        : null;
}

public function setScheduleRealismRatio(?float $ratio): self
{
    $this->scheduleRealismRatio = $ratio !== null
        ? (string) $ratio
        : null;

    return $this;
}
```

#### 3. Add Repository Method

**File:** `src/Repository/ArrivalLogRepository.php`

```php
/**
 * Calculate schedule realism ratio (actual vs scheduled travel time).
 */
public function calculateScheduleRealismRatio(
    int $routeId,
    \DateTimeImmutable $date
): ?float {
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        WITH trip_times AS (
            SELECT
                al.trip_id,
                MAX(al.predicted_arrival_at) - MIN(al.predicted_arrival_at) as actual_duration,
                MAX(al.scheduled_arrival_at) - MIN(al.scheduled_arrival_at) as scheduled_duration
            FROM arrival_log al
            JOIN stop_time st ON al.trip_id = st.trip_id AND al.stop_id = st.stop_id
            WHERE al.route_id = :route_id
              AND al.predicted_at >= :start
              AND al.predicted_at < :end
              AND al.scheduled_arrival_at IS NOT NULL
            GROUP BY al.trip_id
            HAVING COUNT(*) >= 5
        )
        SELECT
            AVG(EXTRACT(EPOCH FROM actual_duration)) as avg_actual_sec,
            AVG(EXTRACT(EPOCH FROM scheduled_duration)) as avg_scheduled_sec
        FROM trip_times
        WHERE scheduled_duration > INTERVAL '0 seconds'
    SQL;

    $start = $date->setTime(0, 0);
    $end   = $date->setTime(23, 59, 59);

    $result = $conn->executeQuery($sql, [
        'route_id' => $routeId,
        'start'    => $start->format('Y-m-d H:i:s'),
        'end'      => $end->format('Y-m-d H:i:s'),
    ])->fetchAssociative();

    $avgActual    = $result['avg_actual_sec'] ?? null;
    $avgScheduled = $result['avg_scheduled_sec'] ?? null;

    if ($avgActual === null || $avgScheduled === null || $avgScheduled == 0) {
        return null;
    }

    return round($avgActual / $avgScheduled, 3);
}
```

#### 4. Update Stats Building

**File:** `src/Service/Dashboard/RoutePerformanceService.php`

```php
private function buildStats(
    Route $route,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array {
    $performances = $this->performanceRepo->findByRouteAndDateRange(
        $route->getId(),
        $startDate,
        $endDate
    );

    // ... existing stats calculation ...

    // Calculate average schedule realism ratio
    $totalRatio = 0.0;
    $ratioCount = 0;

    foreach ($performances as $perf) {
        $ratio = $perf->getScheduleRealismRatio();
        if ($ratio !== null) {
            $totalRatio += $ratio;
            ++$ratioCount;
        }
    }

    $avgRatio = $ratioCount > 0 ? $totalRatio / $ratioCount : null;
    $realismGrade = ScheduleRealismGrade::fromRatio($avgRatio);

    return [
        // ... existing stats ...
        'scheduleRealism' => $avgRatio !== null ? round($avgRatio, 2) : null,
        'scheduleRealismGrade' => $realismGrade,
    ];
}
```

#### 5. Update Template

**File:** `templates/route/detail.html.twig`

```twig
{# Add to summary statistics grid #}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
    <div class="text-xs sm:text-sm font-medium text-gray-600 mb-2">Schedule Realism</div>
    {% if routeDetail.stats.scheduleRealism %}
        <div class="text-2xl sm:text-3xl font-bold {{ routeDetail.stats.scheduleRealismGrade.getColorClass() }}">
            {{ (routeDetail.stats.scheduleRealism * 100)|round(0) }}%
        </div>
        <div class="text-xs text-gray-500 mt-1">
            {{ routeDetail.stats.scheduleRealismGrade.value }}
        </div>
    {% else %}
        <div class="text-xl text-gray-400">Insufficient data</div>
    {% endif %}
</div>
```

#### 6. Database Migration

```php
public function up(Schema $schema): void
{
    $this->addSql('
        ALTER TABLE route_performance_daily
        ADD COLUMN schedule_realism_ratio NUMERIC(5, 3) DEFAULT NULL
    ');

    $this->addSql("
        COMMENT ON COLUMN route_performance_daily.schedule_realism_ratio
        IS 'Actual/scheduled travel time ratio (1.0 = perfect, >1.1 = under-scheduled)'
    ");
}
```

---

## Feature 4: Temporal Delay Curve

### Implementation (Following Same Pattern)

1. **DTO**: `TemporalDelayDataDto` (hour, avgDelay, stddev, sampleSize)
2. **Repository Method**: `ArrivalLogRepository::findTemporalDelayData()`
3. **Chart Preset**: `TemporalDelayChartPreset::create()`
4. **Service**: Call repository, use preset
5. **Template**: Display chart

---

## Feature 5: Reliability Context Panel

### Implementation

#### 1. Create DTO

**File:** `src/Dto/SystemComparisonDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * System-wide performance comparison metrics.
 */
final readonly class SystemComparisonDto
{
    public function __construct(
        public float $systemMedianOnTime,
        public int $routeRank,
        public int $totalRoutes,
        public int $percentile,
    ) {}
}
```

#### 2. Add Repository Method

**File:** `src/Repository/RoutePerformanceDailyRepository.php`

```php
/**
 * Calculate system-wide median on-time percentage.
 */
public function getSystemMedianPerformance(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): float {
    $qb = $this->createQueryBuilder('p');

    $results = $qb
        ->select('p.onTimePercentage')
        ->where('p.date >= :start')
        ->andWhere('p.date < :end')
        ->andWhere('p.onTimePercentage IS NOT NULL')
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
        ->getQuery()
        ->getScalarResult();

    if (count($results) === 0) {
        return 0.0;
    }

    $values = array_map(fn (array $r) => (float) $r['onTimePercentage'], $results);
    sort($values);

    $count = count($values);
    $mid   = (int) floor($count / 2);

    return $count % 2 === 0
        ? ($values[$mid - 1] + $values[$mid]) / 2
        : $values[$mid];
}

/**
 * Get route performance ranking.
 *
 * @return array<int, float> Route ID => average on-time percentage
 */
public function getRoutePerformanceRanking(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array {
    $qb = $this->createQueryBuilder('p');

    $results = $qb
        ->select('IDENTITY(p.route) as route_id, AVG(p.onTimePercentage) as avg_on_time')
        ->where('p.date >= :start')
        ->andWhere('p.date < :end')
        ->andWhere('p.onTimePercentage IS NOT NULL')
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
        ->groupBy('p.route')
        ->getQuery()
        ->getArrayResult();

    $ranking = [];
    foreach ($results as $row) {
        $ranking[(int) $row['route_id']] = (float) $row['avg_on_time'];
    }

    arsort($ranking); // Sort descending by performance

    return $ranking;
}
```

#### 3. Update Service

**File:** `src/Service/Dashboard/RoutePerformanceService.php`

```php
private function buildSystemComparison(
    Route $route,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): SystemComparisonDto {
    $systemMedian = $this->performanceRepo->getSystemMedianPerformance($startDate, $endDate);
    $ranking = $this->performanceRepo->getRoutePerformanceRanking($startDate, $endDate);

    // Find rank of current route
    $rank = 1;
    foreach (array_keys($ranking) as $routeId) {
        if ($routeId === $route->getId()) {
            break;
        }
        ++$rank;
    }

    $totalRoutes = count($ranking);
    $percentile = $totalRoutes > 0
        ? (int) round((($totalRoutes - $rank) / $totalRoutes) * 100)
        : 0;

    return new SystemComparisonDto(
        systemMedianOnTime: round($systemMedian, 1),
        routeRank: $rank,
        totalRoutes: $totalRoutes,
        percentile: $percentile,
    );
}

private function buildStats(...): array
{
    // ... existing code ...

    $systemComparison = $this->buildSystemComparison($route, $startDate, $endDate);

    return [
        // ... existing stats ...
        'systemComparison' => $systemComparison,
    ];
}
```

---

## Feature 6: Live Route Health Gauge

### Implementation

#### 1. Create Enum

**File:** `src/Enum/RouteHealthGrade.php`

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum RouteHealthGrade: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';

    public static function fromPercentage(float $percent): self
    {
        return match (true) {
            $percent >= 90 => self::EXCELLENT,
            $percent >= 70 => self::GOOD,
            $percent >= 50 => self::FAIR,
            default        => self::POOR,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EXCELLENT => '#10b981',
            self::GOOD      => '#84cc16',
            self::FAIR      => '#fbbf24',
            self::POOR      => '#dc2626',
        };
    }
}
```

#### 2. Create DTO

**File:** `src/Dto/RouteHealthDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\RouteHealthGrade;

final readonly class RouteHealthDto
{
    public function __construct(
        public string $routeId,
        public float $healthPercent,
        public RouteHealthGrade $healthGrade,
        public int $activeVehicles,
        public int $onTimeVehicles,
        public int $lateVehicles,
        public int $earlyVehicles,
        public int $timestamp,
    ) {}

    public function toArray(): array
    {
        return [
            'route_id'         => $this->routeId,
            'health_percent'   => $this->healthPercent,
            'health_grade'     => $this->healthGrade->value,
            'active_vehicles'  => $this->activeVehicles,
            'on_time_vehicles' => $this->onTimeVehicles,
            'late_vehicles'    => $this->lateVehicles,
            'early_vehicles'   => $this->earlyVehicles,
            'timestamp'        => $this->timestamp,
        ];
    }
}
```

#### 3. Create Service

**File:** `src/Service/Dashboard/RouteHealthService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\RouteHealthDto;
use App\Enum\RouteHealthGrade;
use App\Repository\RealtimeRepository;

final readonly class RouteHealthService
{
    public function __construct(
        private RealtimeRepository $realtimeRepo,
    ) {}

    public function getRouteHealth(string $gtfsId): RouteHealthDto
    {
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicles = array_filter(
            $snapshot['vehicles'],
            fn (array $v) => ($v['route'] ?? null) === $gtfsId
        );

        $onTimeCount = 0;
        $lateCount   = 0;
        $earlyCount  = 0;
        $totalCount  = count($vehicles);

        foreach ($vehicles as $vehicle) {
            $delay = $vehicle['delay_sec'] ?? null;

            if ($delay === null) {
                continue;
            }

            if ($delay > 120) {
                ++$lateCount;
            } elseif ($delay < -120) {
                ++$earlyCount;
            } else {
                ++$onTimeCount;
            }
        }

        $healthPercent = $totalCount > 0
            ? round(($onTimeCount / $totalCount) * 100, 1)
            : 0.0;
        $healthGrade = RouteHealthGrade::fromPercentage($healthPercent);

        return new RouteHealthDto(
            routeId: $gtfsId,
            healthPercent: $healthPercent,
            healthGrade: $healthGrade,
            activeVehicles: $totalCount,
            onTimeVehicles: $onTimeCount,
            lateVehicles: $lateCount,
            earlyVehicles: $earlyCount,
            timestamp: $snapshot['timestamp'] ?? time(),
        );
    }
}
```

#### 4. Create API Endpoint

**File:** `src/Controller/RouteController.php`

```php
#[Route('/routes/{gtfsId}/health', name: 'health', methods: ['GET'])]
public function health(
    string $gtfsId,
    RouteRepository $routeRepo,
    RouteHealthService $healthService
): JsonResponse {
    $route = $routeRepo->findOneBy(['gtfsId' => $gtfsId]);

    if ($route === null) {
        return $this->json(['error' => 'Route not found'], 404);
    }

    $health = $healthService->getRouteHealth($gtfsId);

    return $this->json($health->toArray());
}
```

---

## Feature 7: Data Integrity / Coverage Diagnostics

### Implementation

#### 1. Create Enum

**File:** `src/Enum/DataQualityGrade.php`

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum DataQualityGrade: string
{
    case EXCELLENT = 'Excellent';
    case GOOD = 'Good';
    case FAIR = 'Fair';
    case LIMITED = 'Limited';

    public static function calculate(float $coverage, int $latency): self
    {
        return match (true) {
            $coverage >= 80 && $latency < 60  => self::EXCELLENT,
            $coverage >= 60 && $latency < 120 => self::GOOD,
            $coverage >= 40 && $latency < 300 => self::FAIR,
            default                            => self::LIMITED,
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::EXCELLENT => 'bg-green-100 text-green-800',
            self::GOOD      => 'bg-blue-100 text-blue-800',
            self::FAIR      => 'bg-yellow-100 text-yellow-800',
            self::LIMITED   => 'bg-red-100 text-red-800',
        };
    }
}
```

#### 2. Create DTO

**File:** `src/Dto/DataQualityMetricsDto.php`

```php
<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\DataQualityGrade;

final readonly class DataQualityMetricsDto
{
    public function __construct(
        public float $tripCoveragePct,
        public float $stopCoveragePct,
        public int $feedLatencySec,
        public int $recentPredictions,
        public int $activeRoutes,
        public int $totalRoutes,
        public DataQualityGrade $dataQualityGrade,
    ) {}
}
```

#### 3. Add Repository Methods

**File:** `src/Repository/ArrivalLogRepository.php`

```php
/**
 * Count unique trips tracked on a specific date.
 */
public function countUniqueTrips(\DateTimeImmutable $date): int
{
    $start = $date->setTime(0, 0);
    $end   = $date->setTime(23, 59, 59);

    return (int) $this->createQueryBuilder('a')
        ->select('COUNT(DISTINCT a.tripId)')
        ->where('a.predictedAt >= :start')
        ->andWhere('a.predictedAt <= :end')
        ->setParameter('start', $start)
        ->setParameter('end', $end)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Count unique stops with predictions on a specific date.
 */
public function countUniqueStops(\DateTimeImmutable $date): int
{
    $start = $date->setTime(0, 0);
    $end   = $date->setTime(23, 59, 59);

    return (int) $this->createQueryBuilder('a')
        ->select('COUNT(DISTINCT IDENTITY(a.stop))')
        ->where('a.predictedAt >= :start')
        ->andWhere('a.predictedAt <= :end')
        ->setParameter('start', $start)
        ->setParameter('end', $end)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Count arrival logs since a specific time.
 */
public function countSince(\DateTimeInterface $since): int
{
    return (int) $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.predictedAt >= :since')
        ->setParameter('since', $since)
        ->getQuery()
        ->getSingleScalarResult();
}
```

#### 4. Create Service

**File:** `src/Service/Dashboard/DataIntegrityService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Dto\DataQualityMetricsDto;
use App\Enum\DataQualityGrade;
use App\Repository\ArrivalLogRepository;
use App\Repository\RealtimeRepository;
use App\Repository\RouteRepository;
use App\Repository\StopRepository;
use App\Repository\TripRepository;

final readonly class DataIntegrityService
{
    public function __construct(
        private ArrivalLogRepository $arrivalLogRepo,
        private TripRepository $tripRepo,
        private StopRepository $stopRepo,
        private RouteRepository $routeRepo,
        private RealtimeRepository $realtimeRepo,
    ) {}

    /**
     * Get data quality metrics for today.
     */
    public function getDataQualityMetrics(): DataQualityMetricsDto
    {
        $today = new \DateTimeImmutable('today');

        // Trip coverage
        $scheduledTrips = $this->tripRepo->count([]);
        $trackedTrips   = $this->arrivalLogRepo->countUniqueTrips($today);
        $tripCoverage   = $scheduledTrips > 0 ? ($trackedTrips / $scheduledTrips) * 100 : 0;

        // Stop coverage
        $totalStops  = $this->stopRepo->count([]);
        $activeStops = $this->arrivalLogRepo->countUniqueStops($today);
        $stopCoverage = $totalStops > 0 ? ($activeStops / $totalStops) * 100 : 0;

        // Feed latency
        $snapshot     = $this->realtimeRepo->snapshot();
        $latestUpdate = $snapshot['timestamp'] ?? time();
        $latencySec   = time() - $latestUpdate;

        // Data freshness
        $recentLogs = $this->arrivalLogRepo->countSince(new \DateTimeImmutable('-1 hour'));

        // Active routes
        $activeRoutes = count(array_unique(array_map(
            fn (array $v) => $v['route'] ?? null,
            $snapshot['vehicles'] ?? []
        )));
        $totalRoutes = $this->routeRepo->count([]);

        $grade = DataQualityGrade::calculate($tripCoverage, $latencySec);

        return new DataQualityMetricsDto(
            tripCoveragePct: round($tripCoverage, 1),
            stopCoveragePct: round($stopCoverage, 1),
            feedLatencySec: $latencySec,
            recentPredictions: $recentLogs,
            activeRoutes: $activeRoutes,
            totalRoutes: $totalRoutes,
            dataQualityGrade: $grade,
        );
    }
}
```

---

## Implementation Roadmap

### Week 1: Backend Foundation

**Day 1-2: Core DTOs and Enums**
- Create all DTOs (StopReliabilityDataDto, DelayPropagationDataDto, etc.)
- Create all Enums (ScheduleRealismGrade, RouteHealthGrade, DataQualityGrade)
- Run `make cs-fix`

**Day 3-4: Repository Methods**
- Add all query methods to ArrivalLogRepository
- Add ranking methods to RoutePerformanceDailyRepository
- Test with EXPLAIN ANALYZE

**Day 5: Database Migrations**
- Create migration for schedule_realism_ratio column
- Create migration for new indexes
- Run migrations and verify

### Week 2: Chart Presets and Services

**Day 6-7: Chart Presets**
- Create StopReliabilityChartPreset
- Create DelayPropagationChartPreset
- Create TemporalDelayChartPreset
- Test chart rendering

**Day 8-9: Services**
- Implement RouteHealthService
- Implement DataIntegrityService
- Update RoutePerformanceService with new chart methods
- Create API endpoint for route health

**Day 10: Frontend**
- Update RouteDetailDto with new Chart properties
- Update templates with new charts
- Create Stimulus controller for live health gauge
- Test mobile responsiveness

### Week 3: Testing and Polish

**Day 11-12: Testing**
- Write PHPUnit tests for all new methods
- Test with limited data scenarios
- Verify query performance with EXPLAIN

**Day 13-14: Documentation and Deployment**
- Run `make cs-fix`
- Update CLAUDE.md
- Create user documentation
- Deploy to staging

---

## Testing Checklist

### Unit Tests Required

**Repository Tests:**
- [ ] `ArrivalLogRepository::findStopReliabilityData()` returns valid DTOs
- [ ] `ArrivalLogRepository::findDelayPropagationData()` calculates deltas correctly
- [ ] `ArrivalLogRepository::calculateScheduleRealismRatio()` handles null data
- [ ] `RoutePerformanceDailyRepository::getSystemMedianPerformance()` calculates median

**Service Tests:**
- [ ] `RouteHealthService::getRouteHealth()` returns correct DTO
- [ ] `DataIntegrityService::getDataQualityMetrics()` calculates coverage

**Enum Tests:**
- [ ] `ScheduleRealismGrade::fromRatio()` assigns correct grades
- [ ] `RouteHealthGrade::fromPercentage()` assigns correct grades
- [ ] `DataQualityGrade::calculate()` uses correct thresholds

**Chart Preset Tests:**
- [ ] `StopReliabilityChartPreset::create()` builds valid Chart object
- [ ] Chart presets handle empty data gracefully

### Integration Tests

- [ ] Route detail page loads all 7 charts without errors
- [ ] Live health gauge API endpoint returns JSON
- [ ] Charts render correctly with 1 day vs 30 days of data

### Manual Testing

- [ ] All charts are mobile-responsive (test at 320px width)
- [ ] Charts show "Insufficient data" messages when appropriate
- [ ] Enums display correct colors and labels
- [ ] Page load performance <2 seconds

---

## Performance Considerations

### Query Optimization

All queries use:
1. ✅ Indexed columns (route_id, predicted_at, stop_id)
2. ✅ Minimum sample size filters (`HAVING COUNT(*) >= N`)
3. ✅ Native SQL for complex aggregations (faster than DQL)

Verify index usage:
```sql
EXPLAIN ANALYZE
SELECT /* your query */;
-- Should show "Index Scan" not "Seq Scan"
```

### Expected Database Load

- 7 new queries per route detail page load
- Each query scans ~1K-10K rows (indexed)
- Live health endpoint: 1 Redis read per request

### Caching Strategy

- Chart data generated once per page load (no additional caching needed)
- Live health polled every 30 seconds (acceptable load)
- Consider Redis caching if page load exceeds 2 seconds

---

## Summary of Best Practices

1. ✅ **DTOs**: All data transfer uses readonly DTOs, not arrays
2. ✅ **Enums**: All string constants replaced with type-safe enums
3. ✅ **Repository Pattern**: Zero SQL in services, all queries in repositories
4. ✅ **Value Objects**: Chart objects built with ChartBuilder, not raw arrays
5. ✅ **Type Safety**: Explicit return types on all methods
6. ✅ **Immutability**: Readonly properties on DTOs
7. ✅ **Code Style**: Run `make cs-fix` before committing

---

**Last Updated:** 2025-10-19
**Status:** Implementation Plan (Updated with Code Quality Standards)
**Owner:** @samuelwilk
**Estimated Effort:** 3 weeks (1 developer)
