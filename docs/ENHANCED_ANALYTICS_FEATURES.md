# Enhanced Route Analytics Features

## Overview

This document outlines the implementation plan for seven new route analytics features that transform mind-the-wait from basic headway monitoring into a comprehensive transit performance diagnostic tool.

**Philosophy:** The value of mind-the-wait.ca isn't just pattern-finding — it's pattern-making visible. Even if the data is noisy or early, the site can structure the questions that the city, riders, or planners can later test.

**Goal:** Build the scaffolding for insight with features that provide actionable value even with limited data (1 day of GTFS-RT).

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

#### Database Changes
**Migration:**
```php
// New index for performance
CREATE INDEX idx_arrival_log_route_stop
ON arrival_log (route_id, stop_id, predicted_at);
```

#### Backend Service (RoutePerformanceService.php)

**New method:**
```php
/**
 * Build stop-level reliability chart showing delay patterns by stop sequence.
 *
 * @return array<string, mixed> ECharts configuration
 */
private function buildStopLevelReliabilityChart(
    Route $route,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array
{
    $conn = $this->performanceRepo->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            st.stop_id,
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
        GROUP BY st.stop_id, s.name, st.stop_sequence
        HAVING COUNT(*) >= 10  -- Require minimum sample size
        ORDER BY st.stop_sequence ASC
    SQL;

    $results = $conn->executeQuery($sql, [
        'route_id'   => $route->getId(),
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date'   => $endDate->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();

    // Build data arrays
    $stopNames = [];
    $avgDelays = [];
    $variances = [];

    foreach ($results as $row) {
        $stopNames[] = $row['stop_name'];
        $avgDelays[] = round((float) $row['avg_delay'], 0);
        $variances[] = round((float) ($row['delay_variance'] ?? 0), 0);
    }

    return [
        'title' => [
            'text'      => 'Stop-Level Reliability',
            'subtext'   => 'Average delay by stop (larger points = more variable)',
            'left'      => 'center',
            'top'       => '5',
            'textStyle' => ['fontSize' => 12, 'fontWeight' => 'bold'],
        ],
        'tooltip' => [
            'trigger'   => 'axis',
            'formatter' => '{b}<br/>Avg Delay: {c} sec<br/>Variance: {a}',
        ],
        'xAxis' => [
            'type'      => 'category',
            'data'      => $stopNames,
            'axisLabel' => [
                'rotate'   => 45,
                'interval' => 0, // Show all labels
            ],
        ],
        'yAxis' => [
            'type'          => 'value',
            'name'          => 'Delay (seconds)',
            'nameLocation'  => 'middle',
            'nameGap'       => 40,
            'nameTextStyle' => ['fontSize' => 11],
            'axisLine'      => ['show' => true],
            'splitLine'     => ['lineStyle' => ['type' => 'dashed']],
        ],
        'series' => [
            [
                'name'      => 'Average Delay',
                'type'      => 'line',
                'data'      => $avgDelays,
                'smooth'    => false,
                'lineStyle' => ['width' => 2],
                'itemStyle' => ['color' => '#0284c7'],
                'symbolSize' => function ($value, $params) use ($variances) {
                    // Size points by variance (clamped to 5-20)
                    $variance = $variances[$params['dataIndex']] ?? 0;
                    return min(20, max(5, $variance / 10));
                },
            ],
        ],
        'grid' => [
            'left'         => '40',
            'right'        => '4%',
            'top'          => '80',
            'bottom'       => '20%',
            'containLabel' => true,
        ],
    ];
}
```

#### Update RouteDetailDto

```php
public function __construct(
    public array $performanceTrendChart,
    public array $weatherImpactChart,
    public array $timeOfDayHeatmap,
    public array $stopLevelReliabilityChart,  // NEW
    public array $stats,
) {
}
```

#### Update Template (route_detail.html.twig)

```twig
{# Add after existing charts #}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-6 mb-8">
    <div
        data-controller="chart"
        data-chart-options-value="{{ routeDetail.stopLevelReliabilityChart|json_encode|e('html_attr') }}"
        style="width: 100%; height: 350px; min-height: 350px; position: relative; z-index: 1;">
    </div>
</div>
```

### Expected Output
- Line chart with stop names on X-axis, delay in seconds on Y-axis
- Point size indicates variance (bigger = more unstable)
- Identifies problem stops (e.g., "Stop #5: Idylwyld Dr & 22nd St consistently +120 sec late")

---

## Feature 2: Delay Propagation Visualization

### Objective
Show how delays compound or self-correct along the route over the course of trips.

### Business Value
- Reveals whether delays are systemic (additive) or self-correcting
- Shows if schedule padding is working
- Identifies which time periods have runaway delay propagation

### Implementation

#### Backend Service Method

```php
/**
 * Build delay propagation heatmap showing how delays change between consecutive stops.
 *
 * @return array<string, mixed> ECharts heatmap configuration
 */
private function buildDelayPropagationHeatmap(
    Route $route,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array
{
    $conn = $this->performanceRepo->getEntityManager()->getConnection();

    // Calculate delay delta (change in delay) between consecutive stops
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
        HAVING COUNT(*) >= 5  -- Minimum sample size
        ORDER BY hour, stop_sequence
    SQL;

    $results = $conn->executeQuery($sql, [
        'route_id'   => $route->getId(),
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date'   => $endDate->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();

    // Build heatmap data
    $data      = [];
    $resultMap = [];

    // Index results by [hour][stop_sequence]
    foreach ($results as $row) {
        $hour         = (int) $row['hour'];
        $stopSequence = (int) $row['stop_sequence'];
        $delta        = round((float) $row['avg_delay_delta'], 0);

        $resultMap[$hour][$stopSequence] = $delta;
    }

    // Fill heatmap data (24 hours × max stop sequence)
    $maxStopSeq = count($resultMap) > 0 ? max(array_map('max', array_map('array_keys', $resultMap))) : 20;

    for ($h = 0; $h < 24; ++$h) {
        for ($s = 1; $s <= $maxStopSeq; ++$s) {
            $value  = $resultMap[$h][$s] ?? null;
            $data[] = [$s - 1, $h, $value]; // X=stop, Y=hour, Value=delta
        }
    }

    return [
        'title' => [
            'text'      => 'Delay Propagation Pattern',
            'subtext'   => 'How delays change between stops (red = delays growing, green = recovering)',
            'left'      => 'center',
            'top'       => '5',
            'textStyle' => ['fontSize' => 12, 'fontWeight' => 'bold'],
        ],
        'tooltip' => [
            'position'  => 'top',
            'formatter' => 'Stop {c0}<br/>Hour {c1}:00<br/>Delay Change: {c2}s',
        ],
        'xAxis' => [
            'type'      => 'category',
            'data'      => range(1, $maxStopSeq),
            'name'      => 'Stop Sequence',
            'splitArea' => ['show' => true],
        ],
        'yAxis' => [
            'type'      => 'category',
            'data'      => range(0, 23),
            'name'      => 'Hour of Day',
            'splitArea' => ['show' => true],
        ],
        'visualMap' => [
            'min'        => -60,  // Recovering by 1 min
            'max'        => 60,   // Degrading by 1 min
            'calculable' => true,
            'orient'     => 'horizontal',
            'left'       => 'center',
            'bottom'     => '0%',
            'inRange'    => [
                'color' => ['#10b981', '#fbbf24', '#f97316', '#dc2626'], // green → red
            ],
        ],
        'series' => [
            [
                'name'  => 'Delay Delta',
                'type'  => 'heatmap',
                'data'  => $data,
                'label' => [
                    'show'     => false, // Too cluttered for small cells
                    'fontSize' => 9,
                ],
            ],
        ],
        'grid' => [
            'height' => '60%',
            'top'    => '80',
        ],
    ];
}
```

#### Add to RouteDetailDto
```php
public array $delayPropagationHeatmap,  // NEW
```

### Expected Output
- Heatmap: X-axis = stop sequence, Y-axis = hour of day
- Color: Green (delays recovering), Yellow (stable), Red (delays growing)
- **Insight Example:** "Stop #8 at 5 PM consistently shows +45 sec delay growth → traffic bottleneck"

---

## Feature 3: Schedule Realism Index

### Objective
Identify routes with chronic under-scheduling or over-scheduling (excessive padding).

### Business Value
- Provides evidence for schedule adjustments
- Shows if operators are sitting idle (over-scheduled) or chronically behind (under-scheduled)
- Helps optimize scheduling for efficiency and rider satisfaction

### Implementation

#### Database Changes

**Migration:**
```php
public function up(Schema $schema): void
{
    $this->addSql('
        ALTER TABLE route_performance_daily
        ADD COLUMN schedule_realism_ratio NUMERIC(5, 3) DEFAULT NULL
    ');

    $this->addSql("
        COMMENT ON COLUMN route_performance_daily.schedule_realism_ratio
        IS 'Ratio of actual mean travel time to scheduled travel time (1.0 = perfect)'
    ");
}
```

#### Update PerformanceAggregator Service

```php
// In src/Service/History/PerformanceAggregator.php

public function aggregateDate(\DateTimeImmutable $date): array
{
    // ... existing code ...

    // NEW: Calculate schedule realism ratio
    $realismRatio = $this->calculateScheduleRealismRatio($route, $date);
    $performance->setScheduleRealismRatio($realismRatio !== null ? (string) $realismRatio : null);

    // ... rest of existing code ...
}

/**
 * Calculate ratio of actual travel time to scheduled travel time.
 *
 * @return float|null Ratio (1.0 = perfect, >1.1 = under-scheduled, <0.9 = over-scheduled)
 */
private function calculateScheduleRealismRatio(Route $route, \DateTimeImmutable $date): ?float
{
    $conn = $this->em->getConnection();

    // Get actual vs scheduled trip durations
    $sql = <<<'SQL'
        WITH trip_times AS (
            SELECT
                al.trip_id,
                MIN(st.stop_sequence) as first_stop,
                MAX(st.stop_sequence) as last_stop,
                MAX(al.predicted_arrival_at) - MIN(al.predicted_arrival_at) as actual_duration,
                MAX(al.scheduled_arrival_at) - MIN(al.scheduled_arrival_at) as scheduled_duration
            FROM arrival_log al
            JOIN stop_time st ON al.trip_id = st.trip_id AND al.stop_id = st.stop_id
            WHERE al.route_id = :route_id
              AND al.predicted_at >= :start
              AND al.predicted_at < :end
              AND al.scheduled_arrival_at IS NOT NULL
            GROUP BY al.trip_id
            HAVING COUNT(*) >= 5  -- Need multiple stops per trip
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
        'route_id' => $route->getId(),
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

#### Update RoutePerformanceService Stats

```php
private function buildStats(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
{
    $performances = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

    // ... existing stats calculation ...

    // NEW: Calculate average schedule realism ratio
    $totalRatio = 0.0;
    $ratioCount = 0;

    foreach ($performances as $perf) {
        $ratio = $perf->getScheduleRealismRatio();
        if ($ratio !== null) {
            $totalRatio += (float) $ratio;
            ++$ratioCount;
        }
    }

    $avgRatio = $ratioCount > 0 ? $totalRatio / $ratioCount : null;

    return [
        // ... existing stats ...
        'scheduleRealism' => $avgRatio !== null ? round($avgRatio, 2) : null,
        'scheduleRealismGrade' => $this->getScheduleRealismGrade($avgRatio),
    ];
}

private function getScheduleRealismGrade(?float $ratio): string
{
    if ($ratio === null) {
        return 'Unknown';
    }

    return match (true) {
        $ratio >= 1.15 => 'Severely Under-scheduled',
        $ratio >= 1.10 => 'Under-scheduled',
        $ratio >= 0.95 => 'Realistic',
        $ratio >= 0.85 => 'Over-scheduled',
        default        => 'Severely Over-scheduled (excessive padding)',
    };
}
```

#### Update Template

```twig
{# Add to summary statistics grid #}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
    <div class="text-xs sm:text-sm font-medium text-gray-600 mb-2">Schedule Realism</div>
    {% if routeDetail.stats.scheduleRealism %}
        <div class="text-2xl sm:text-3xl font-bold
            {% if routeDetail.stats.scheduleRealismGrade == 'Realistic' %}text-success-600
            {% elseif routeDetail.stats.scheduleRealismGrade starts with 'Under' %}text-danger-600
            {% else %}text-warning-600{% endif %}">
            {{ (routeDetail.stats.scheduleRealism * 100)|round(0) }}%
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ routeDetail.stats.scheduleRealismGrade }}</div>
    {% else %}
        <div class="text-xl text-gray-400">Insufficient data</div>
    {% endif %}
</div>
```

### Expected Output
- Metric card showing ratio (e.g., "115%")
- Color-coded interpretation (Red: Under-scheduled, Green: Realistic, Yellow: Over-scheduled)
- **Example:** "Route 27 runs 15% over schedule on average → need more time in schedule"

---

## Feature 4: Temporal Delay Curve

### Objective
Show when during the day delays are systematically worst or best.

### Business Value
- Quickly communicates rush-hour stress points
- Shows midday recovery periods
- One day's pattern is often repeatable (Saskatoon traffic cycles are stable)

### Implementation

#### Backend Service Method

```php
/**
 * Build temporal delay curve showing average delay by hour of day.
 *
 * @return array<string, mixed> ECharts line chart configuration
 */
private function buildTemporalDelayCurve(
    Route $route,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array
{
    $conn = $this->performanceRepo->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        SELECT
            EXTRACT(HOUR FROM predicted_at) as hour,
            AVG(delay_sec) as avg_delay,
            STDDEV(delay_sec) as delay_stddev,
            COUNT(*) as sample_size
        FROM arrival_log
        WHERE route_id = :route_id
          AND predicted_at >= :start_date
          AND predicted_at < :end_date
          AND delay_sec IS NOT NULL
        GROUP BY EXTRACT(HOUR FROM predicted_at)
        HAVING COUNT(*) >= 10
        ORDER BY hour
    SQL;

    $results = $conn->executeQuery($sql, [
        'route_id'   => $route->getId(),
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date'   => $endDate->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();

    $hours      = [];
    $avgDelays  = [];
    $upperBound = [];
    $lowerBound = [];

    foreach ($results as $row) {
        $hour    = (int) $row['hour'];
        $avg     = (float) $row['avg_delay'];
        $stddev  = (float) ($row['delay_stddev'] ?? 0);

        $hours[]      = $hour . ':00';
        $avgDelays[]  = round($avg, 0);
        $upperBound[] = round($avg + $stddev, 0);
        $lowerBound[] = round($avg - $stddev, 0);
    }

    return [
        'title' => [
            'text'      => 'Delay Pattern by Hour',
            'subtext'   => 'Average delay throughout the day (shaded area = variance)',
            'left'      => 'center',
            'top'       => '5',
            'textStyle' => ['fontSize' => 12, 'fontWeight' => 'bold'],
        ],
        'tooltip' => [
            'trigger' => 'axis',
        ],
        'xAxis' => [
            'type'      => 'category',
            'data'      => $hours,
            'boundaryGap' => false,
        ],
        'yAxis' => [
            'type'          => 'value',
            'name'          => 'Delay (seconds)',
            'nameLocation'  => 'middle',
            'nameGap'       => 40,
            'nameTextStyle' => ['fontSize' => 11],
        ],
        'series' => [
            [
                'name'      => 'Average Delay',
                'type'      => 'line',
                'data'      => $avgDelays,
                'smooth'    => true,
                'lineStyle' => ['width' => 3],
                'itemStyle' => ['color' => '#0284c7'],
                'areaStyle' => ['color' => 'rgba(2, 132, 199, 0.1)'],
            ],
            // Upper bound (avg + stddev)
            [
                'name'      => 'Upper Bound',
                'type'      => 'line',
                'data'      => $upperBound,
                'lineStyle' => ['opacity' => 0],
                'stack'     => 'confidence',
                'symbol'    => 'none',
            ],
            // Lower bound (avg - stddev)
            [
                'name'      => 'Lower Bound',
                'type'      => 'line',
                'data'      => $lowerBound,
                'lineStyle' => ['opacity' => 0},
                'areaStyle' => ['color' => 'rgba(2, 132, 199, 0.2)'],
                'stack'     => 'confidence',
                'symbol'    => 'none',
            ],
        ],
        'grid' => [
            'left'         => '40',
            'right'        => '4%',
            'top'          => '80',
            'bottom'       => '10%',
            'containLabel' => true,
        ],
    ];
}
```

#### Add to RouteDetailDto
```php
public array $temporalDelayCurve,  // NEW
```

### Expected Output
- Smooth line chart: X-axis = hour (0-23), Y-axis = avg delay
- Shaded area shows ±1 standard deviation (consistency indicator)
- **Insight Example:** "Peak delays at 5-6 PM (+180 sec), recovers by 8 PM"

---

## Feature 5: Reliability Context Panel

### Objective
Show how this route compares to system-wide performance.

### Business Value
- Normalizes performance — users see if a route's issue is local or system-wide
- Provides context: "Route 27 is #4 of 22 — better than 82% of routes"
- Helps prioritize which routes need intervention

### Implementation

#### New Service Method (OverviewService.php)

```php
/**
 * Get system-wide median performance for a date range.
 */
public function getSystemMedianPerformance(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): float
{
    $qb = $this->performanceRepo->createQueryBuilder('p');

    $results = $qb
        ->select('p.onTimePercentage')
        ->where('p.date >= :start')
        ->andWhere('p.date < :end')
        ->andWhere('p.onTimePercentage IS NOT NULL')
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
        ->getQuery()
        ->getResult();

    if (count($results) === 0) {
        return 0.0;
    }

    $values = array_map(fn($r) => (float) $r['onTimePercentage'], $results);
    sort($values);

    $count = count($values);
    $mid   = (int) floor($count / 2);

    return $count % 2 === 0
        ? ($values[$mid - 1] + $values[$mid]) / 2
        : $values[$mid];
}
```

#### Update RoutePerformanceService

```php
private function buildStats(Route $route, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
{
    // ... existing stats ...

    // NEW: Calculate system comparison
    $systemMedian = $this->overviewService->getSystemMedianPerformance($startDate, $endDate);

    // Get all routes ranked by performance
    $allRoutes = $this->routeRepo->findAll();
    $routePerfs = [];

    foreach ($allRoutes as $r) {
        $perf = $this->performanceRepo->findByRouteAndDateRange($r->getId(), $startDate, $endDate);
        $avgOnTime = 0.0;
        $count = 0;

        foreach ($perf as $p) {
            if ($p->getOnTimePercentage() !== null) {
                $avgOnTime += (float) $p->getOnTimePercentage();
                ++$count;
            }
        }

        if ($count > 0) {
            $routePerfs[$r->getId()] = $avgOnTime / $count;
        }
    }

    // Sort routes by performance (descending)
    arsort($routePerfs);

    // Find rank of current route
    $rank = 1;
    foreach (array_keys($routePerfs) as $routeId) {
        if ($routeId === $route->getId()) {
            break;
        }
        ++$rank;
    }

    $totalRoutes = count($routePerfs);
    $percentile = $totalRoutes > 0 ? round((($totalRoutes - $rank) / $totalRoutes) * 100, 0) : 0;

    return [
        // ... existing stats ...
        'systemComparison' => [
            'systemMedianOnTime' => round($systemMedian, 1),
            'routeRank'          => $rank,
            'totalRoutes'        => $totalRoutes,
            'percentile'         => $percentile,
        ],
    ];
}
```

#### Update Template

```twig
{# Add after insights section #}
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 sm:p-6 mb-8">
    <div class="flex items-start">
        <div class="flex-shrink-0">
            <svg class="h-6 w-6 sm:h-8 sm:w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </div>
        <div class="ml-3 sm:ml-4 flex-1">
            <h3 class="text-base sm:text-lg font-semibold text-blue-900">System Context</h3>
            <div class="mt-2 text-xs sm:text-sm text-blue-800">
                <p class="mb-2">
                    This route ranks <strong>#{{ routeDetail.stats.systemComparison.routeRank }}</strong>
                    out of {{ routeDetail.stats.systemComparison.totalRoutes }} routes in Saskatoon Transit.
                </p>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <div class="text-xs text-blue-600 mb-1">System Median</div>
                        <div class="text-lg font-bold text-blue-900">{{ routeDetail.stats.systemComparison.systemMedianOnTime }}%</div>
                    </div>
                    <div>
                        <div class="text-xs text-blue-600 mb-1">Percentile</div>
                        <div class="text-lg font-bold text-blue-900">{{ routeDetail.stats.systemComparison.percentile }}th</div>
                    </div>
                </div>
                <p class="mt-3 text-xs">
                    This route performs better than {{ routeDetail.stats.systemComparison.percentile }}% of routes in the system.
                </p>
            </div>
        </div>
    </div>
</div>
```

### Expected Output
- Card showing: "Route ranks #4 of 22"
- System median comparison: "System median: 82.5%, This route: 87.3%"
- Percentile: "Better than 82% of routes"

---

## Feature 6: Live Route Health Gauge

### Objective
Real-time % of vehicles within ±2 min of schedule — instant visual feedback.

### Business Value
- Makes mind-the-wait feel alive and responsive
- Helps casual riders at a glance
- No historical data needed — uses current realtime feed

### Implementation

#### New API Endpoint (RouteController.php)

```php
/**
 * Get live health status for a route (% of vehicles on-time).
 */
#[Route('/routes/{gtfsId}/health', name: 'health', methods: ['GET'])]
public function health(string $gtfsId, RealtimeRepository $realtimeRepo): JsonResponse
{
    $route = $this->routeRepo->findOneBy(['gtfsId' => $gtfsId]);

    if ($route === null) {
        return $this->json(['error' => 'Route not found'], 404);
    }

    // Get current vehicles from Redis
    $snapshot = $realtimeRepo->snapshot();
    $vehicles = array_filter($snapshot['vehicles'], fn($v) => ($v['route'] ?? null) === $gtfsId);

    $onTimeCount = 0;
    $lateCount   = 0;
    $earlyCount  = 0;
    $totalCount  = count($vehicles);

    foreach ($vehicles as $vehicle) {
        $delay = $vehicle['delay_sec'] ?? null;

        if ($delay === null) {
            continue; // Skip vehicles without delay data
        }

        if ($delay > 120) {
            ++$lateCount;
        } elseif ($delay < -120) {
            ++$earlyCount;
        } else {
            ++$onTimeCount;
        }
    }

    $healthPercent = $totalCount > 0 ? round(($onTimeCount / $totalCount) * 100, 1) : 0;
    $healthGrade = match (true) {
        $healthPercent >= 90 => 'excellent',
        $healthPercent >= 70 => 'good',
        $healthPercent >= 50 => 'fair',
        default              => 'poor',
    };

    return $this->json([
        'route_id'         => $gtfsId,
        'health_percent'   => $healthPercent,
        'health_grade'     => $healthGrade,
        'active_vehicles'  => $totalCount,
        'on_time_vehicles' => $onTimeCount,
        'late_vehicles'    => $lateCount,
        'early_vehicles'   => $earlyCount,
        'timestamp'        => $snapshot['timestamp'] ?? time(),
    ]);
}
```

#### Frontend Stimulus Controller

```javascript
// assets/controllers/route_health_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        routeId: String,
        pollInterval: { type: Number, default: 30000 }  // Poll every 30 seconds
    }

    static targets = ['gauge', 'activeVehicles', 'healthText']

    connect() {
        this.poll();
        this.pollTimer = setInterval(() => this.poll(), this.pollIntervalValue);
    }

    disconnect() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
        }
    }

    async poll() {
        try {
            const response = await fetch(`/routes/${this.routeIdValue}/health`);
            const data = await response.json();
            this.updateGauge(data);
        } catch (error) {
            console.error('Failed to fetch route health:', error);
        }
    }

    updateGauge(data) {
        // Update text
        this.activeVehiclesTarget.textContent = data.active_vehicles;
        this.healthTextTarget.textContent = `${data.health_percent}% on-time`;

        // Update gauge color
        const color = this.getColorForGrade(data.health_grade);
        this.gaugeTarget.style.background = `conic-gradient(
            ${color} 0deg,
            ${color} ${data.health_percent * 3.6}deg,
            #e5e7eb ${data.health_percent * 3.6}deg
        )`;
    }

    getColorForGrade(grade) {
        const colors = {
            'excellent': '#10b981',  // green
            'good': '#84cc16',       // lime
            'fair': '#fbbf24',       // yellow
            'poor': '#dc2626',       // red
        };
        return colors[grade] || '#9ca3af';
    }
}
```

#### Template Component

```twig
{# Add at top of route detail page, before summary stats #}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 mb-6"
     data-controller="route-health"
     data-route-health-route-id-value="{{ route.gtfsId }}">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-gray-600 mb-1">Live Route Health</h3>
            <div class="text-2xl font-bold text-gray-900" data-route-health-target="healthText">
                Loading...
            </div>
            <p class="text-xs text-gray-500 mt-1">
                <span data-route-health-target="activeVehicles">-</span> active vehicles
            </p>
        </div>
        <div class="w-20 h-20 rounded-full border-4 border-gray-200 flex items-center justify-center"
             data-route-health-target="gauge"
             style="background: conic-gradient(#e5e7eb 0deg, #e5e7eb 360deg);">
            <div class="w-14 h-14 bg-white rounded-full"></div>
        </div>
    </div>
    <div class="mt-3 text-xs text-gray-500">
        Updates every 30 seconds from realtime feed
    </div>
</div>
```

### Expected Output
- Circular gauge showing % on-time (green → red)
- Text: "87% on-time" with "5 active vehicles"
- Auto-refreshes every 30 seconds
- Color-coded: Green (>90%), Yellow (70-90%), Red (<70%)

---

## Feature 7: Data Integrity / Coverage Diagnostics

### Objective
Build user trust by transparently showing data quality and coverage.

### Business Value
- Establishes credibility
- Explains why patterns might be weak ("limited data coverage today")
- Helps debug feed issues

### Implementation

#### New Service (DataIntegrityService.php)

```php
<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

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
    ) {
    }

    /**
     * Get data quality metrics for today.
     *
     * @return array<string, mixed>
     */
    public function getDataQualityMetrics(): array
    {
        $today = new \DateTimeImmutable('today');

        // 1. Trip coverage: % of scheduled trips with realtime data
        $scheduledTrips = $this->tripRepo->count([]);
        $trackedTrips   = $this->arrivalLogRepo->countUniqueTrips($today);
        $tripCoverage   = $scheduledTrips > 0 ? ($trackedTrips / $scheduledTrips) * 100 : 0;

        // 2. Stop coverage: % of stops with arrival predictions today
        $totalStops  = $this->stopRepo->count([]);
        $activeStops = $this->arrivalLogRepo->countUniqueStops($today);
        $stopCoverage = $totalStops > 0 ? ($activeStops / $totalStops) * 100 : 0;

        // 3. Feed latency: Age of latest realtime update
        $snapshot     = $this->realtimeRepo->snapshot();
        $latestUpdate = $snapshot['timestamp'] ?? time();
        $latencySec   = time() - $latestUpdate;

        // 4. Data freshness: Predictions from last hour
        $recentLogs = $this->arrivalLogRepo->countSince(new \DateTimeImmutable('-1 hour'));

        // 5. Active routes: Routes with vehicles right now
        $activeRoutes = count(array_unique(array_map(
            fn($v) => $v['route'] ?? null,
            $snapshot['vehicles'] ?? []
        )));
        $totalRoutes = $this->routeRepo->count([]);

        return [
            'trip_coverage_pct'   => round($tripCoverage, 1),
            'stop_coverage_pct'   => round($stopCoverage, 1),
            'feed_latency_sec'    => $latencySec,
            'recent_predictions'  => $recentLogs,
            'active_routes'       => $activeRoutes,
            'total_routes'        => $totalRoutes,
            'data_quality_grade'  => $this->calculateQualityGrade($tripCoverage, $latencySec),
        ];
    }

    /**
     * Calculate overall data quality grade.
     */
    private function calculateQualityGrade(float $coverage, int $latency): string
    {
        if ($coverage >= 80 && $latency < 60) {
            return 'Excellent';
        }

        if ($coverage >= 60 && $latency < 120) {
            return 'Good';
        }

        if ($coverage >= 40 && $latency < 300) {
            return 'Fair';
        }

        return 'Limited';
    }
}
```

#### Add Repository Methods

```php
// In ArrivalLogRepository.php

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

#### Update Dashboard Template

```twig
{# Add to dashboard footer (dashboard/index.html.twig) #}
<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 sm:p-6 mt-8">
    <div class="flex items-center justify-between mb-4">
        <h4 class="font-semibold text-gray-900">Data Quality</h4>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
            {% if dataQuality.data_quality_grade == 'Excellent' %}bg-green-100 text-green-800
            {% elseif dataQuality.data_quality_grade == 'Good' %}bg-blue-100 text-blue-800
            {% elseif dataQuality.data_quality_grade == 'Fair' %}bg-yellow-100 text-yellow-800
            {% else %}bg-red-100 text-red-800{% endif %}">
            {{ dataQuality.data_quality_grade }}
        </span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <div class="text-xs sm:text-sm text-gray-600 mb-1">Trip Coverage</div>
            <div class="text-xl sm:text-2xl font-bold text-gray-900">{{ dataQuality.trip_coverage_pct }}%</div>
            <div class="text-xs text-gray-500">{{ dataQuality.active_routes }} of {{ dataQuality.total_routes }} routes active</div>
        </div>

        <div>
            <div class="text-xs sm:text-sm text-gray-600 mb-1">Feed Latency</div>
            <div class="text-xl sm:text-2xl font-bold
                {% if dataQuality.feed_latency_sec < 60 %}text-green-600
                {% elseif dataQuality.feed_latency_sec < 300 %}text-yellow-600
                {% else %}text-red-600{% endif %}">
                {{ dataQuality.feed_latency_sec }}s
            </div>
            <div class="text-xs text-gray-500">Last realtime update</div>
        </div>

        <div>
            <div class="text-xs sm:text-sm text-gray-600 mb-1">Stop Coverage</div>
            <div class="text-xl sm:text-2xl font-bold text-gray-900">{{ dataQuality.stop_coverage_pct }}%</div>
            <div class="text-xs text-gray-500">Stops with predictions</div>
        </div>

        <div>
            <div class="text-xs sm:text-sm text-gray-600 mb-1">Recent Activity</div>
            <div class="text-xl sm:text-2xl font-bold text-gray-900">{{ dataQuality.recent_predictions }}</div>
            <div class="text-xs text-gray-500">Predictions last hour</div>
        </div>
    </div>

    <div class="mt-4 text-xs text-gray-600">
        <p>Data quality metrics help you understand the reliability of insights.
        <strong>Excellent</strong> = comprehensive coverage and fresh data.
        <strong>Limited</strong> = partial coverage or stale feed.</p>
    </div>
</div>
```

### Expected Output
- Footer panel showing 4 metrics:
  - Trip Coverage: "78%" (with "18 of 22 routes active")
  - Feed Latency: "45s" (color: green <60s, yellow <300s, red >300s)
  - Stop Coverage: "82%"
  - Recent Activity: "1,247 predictions last hour"
- Overall grade badge: "Excellent" / "Good" / "Fair" / "Limited"

---

## Database Migrations Summary

### Migration 1: Add Schedule Realism Ratio
```php
// migrations/VersionXXX_AddScheduleRealismRatio.php
public function up(Schema $schema): void
{
    $this->addSql('
        ALTER TABLE route_performance_daily
        ADD schedule_realism_ratio NUMERIC(5, 3) DEFAULT NULL
    ');

    $this->addSql("
        COMMENT ON COLUMN route_performance_daily.schedule_realism_ratio
        IS 'Actual/scheduled travel time ratio (1.0 = perfect, >1.1 = under-scheduled)'
    ");
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE route_performance_daily DROP COLUMN schedule_realism_ratio');
}
```

### Migration 2: Add Performance Indexes
```php
// migrations/VersionXXX_AddAnalyticsIndexes.php
public function up(Schema $schema): void
{
    // For stop-level reliability queries
    $this->addSql('
        CREATE INDEX idx_arrival_log_route_stop
        ON arrival_log (route_id, stop_id, predicted_at)
    ');

    // For temporal delay curve queries (PostgreSQL functional index)
    $this->addSql('
        CREATE INDEX idx_arrival_log_route_hour
        ON arrival_log (route_id, EXTRACT(HOUR FROM predicted_at))
    ');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX IF EXISTS idx_arrival_log_route_stop');
    $this->addSql('DROP INDEX IF EXISTS idx_arrival_log_route_hour');
}
```

---

## Implementation Roadmap (2-Week Timeline)

### Week 1: Backend Foundation + Database

**Day 1-2: Database & Data Integrity**
- ✅ Create migrations for schedule_realism_ratio column
- ✅ Create migrations for new indexes
- ✅ Implement DataIntegrityService
- ✅ Add repository methods (countUniqueTrips, countUniqueStops, countSince)
- ✅ Test data quality metrics on dashboard

**Day 3-4: Chart Services (Part 1)**
- ✅ Implement buildStopLevelReliabilityChart()
- ✅ Implement buildDelayPropagationHeatmap()
- ✅ Test with sample data

**Day 5: Chart Services (Part 2) + Performance Aggregation**
- ✅ Implement buildTemporalDelayCurve()
- ✅ Update PerformanceAggregator to calculate schedule realism ratio
- ✅ Add system comparison calculations to RoutePerformanceService
- ✅ Update buildStats() method with new metrics

### Week 2: Frontend + Polish

**Day 6-7: Route Detail Page Enhancement**
- ✅ Update RouteDetailDto with new chart properties
- ✅ Update RoutePerformanceService::getRouteDetail() to generate all new charts
- ✅ Update route_detail.html.twig with new chart sections
- ✅ Add metric cards for schedule realism and system rank
- ✅ Add reliability context panel

**Day 8-9: Live Features**
- ✅ Create /routes/{id}/health API endpoint
- ✅ Implement route_health Stimulus controller
- ✅ Add live health gauge component to route detail page
- ✅ Test real-time polling and auto-refresh

**Day 10: Data Quality Dashboard**
- ✅ Add data integrity metrics to dashboard footer
- ✅ Style and format quality indicators
- ✅ Test with different data coverage scenarios

**Day 11-12: Testing, Optimization & Documentation**
- ✅ Write PHPUnit tests for new service methods
- ✅ Test with limited data scenarios (1 day, missing stops, partial coverage)
- ✅ Add loading states and error handling for live features
- ✅ Optimize query performance (verify index usage with EXPLAIN)
- ✅ Run `make cs-fix` for code style
- ✅ Update CLAUDE.md with new features
- ✅ Create user-facing documentation

---

## Testing Strategy

### Unit Tests (PHPUnit)

**Test file:** `tests/Service/Dashboard/RoutePerformanceServiceTest.php`

```php
public function testStopLevelReliabilityChartGeneratesValidData(): void
{
    // Given: Route with arrival logs at multiple stops
    // When: buildStopLevelReliabilityChart() is called
    // Then: Chart config contains stop names, delays, and variances
}

public function testDelayPropagationHeatmapCalculatesDelta(): void
{
    // Given: Trip with delays at consecutive stops
    // When: buildDelayPropagationHeatmap() is called
    // Then: Heatmap shows delay delta (change between stops)
}

public function testScheduleRealismRatioCalculation(): void
{
    // Given: Actual and scheduled trip durations
    // When: calculateScheduleRealismRatio() is called
    // Then: Returns correct ratio (actual/scheduled)
}
```

**Test file:** `tests/Service/Dashboard/DataIntegrityServiceTest.php`

```php
public function testDataQualityMetricsWithFullCoverage(): void
{
    // Given: All trips tracked, fresh feed
    // When: getDataQualityMetrics() is called
    // Then: Returns "Excellent" grade
}

public function testDataQualityMetricsWithStaleFeed(): void
{
    // Given: Feed latency > 300 seconds
    // When: getDataQualityMetrics() is called
    // Then: Returns "Limited" grade
}
```

### Integration Tests

**Test scenarios:**
1. Route detail page loads with all 7 new features
2. Live health gauge updates via API endpoint
3. Charts render correctly with 1 day vs 30 days of data
4. Data quality panel shows accurate metrics

### Manual Testing Checklist

- [ ] Stop-level reliability chart identifies problem stops
- [ ] Delay propagation heatmap shows delay growth patterns
- [ ] Schedule realism index correctly flags over/under-scheduled routes
- [ ] Temporal delay curve shows peak delay hours
- [ ] System comparison ranks routes correctly
- [ ] Live health gauge updates in real-time
- [ ] Data quality metrics match actual database counts
- [ ] All charts are mobile-responsive (test at 320px width)
- [ ] Charts handle missing data gracefully ("Insufficient data" message)
- [ ] Page load performance is acceptable (<2 seconds)

---

## Performance Considerations

### Query Optimization

**All new queries:**
1. Use indexed columns (route_id, predicted_at, stop_id)
2. Include `HAVING COUNT(*) >= N` to filter out low-sample-size data
3. Use native SQL for complex aggregations (faster than Doctrine DQL)

**Index verification:**
```sql
EXPLAIN ANALYZE
SELECT /* your query here */;
```

Expected: "Index Scan" not "Seq Scan"

### Caching Strategy

**Chart data caching:**
- RouteDetailDto is rendered once per page load (no need for additional caching)
- Live health endpoint is polled every 30 seconds (acceptable load)

**Optional: Add Redis caching for heavy queries:**
```php
$cacheKey = "route_detail_{$route->getId()}_{$startDate->format('Ymd')}";
$cachedData = $this->cache->get($cacheKey, function () use ($route, $startDate, $endDate) {
    return $this->buildStopLevelReliabilityChart($route, $startDate, $endDate);
});
```

### Database Load

**Estimated impact:**
- 7 new queries per route detail page load
- Each query scans ~1K-10K arrival_log rows (indexed, fast)
- Live health API: 1 Redis read per request (minimal)

**Mitigation if needed:**
- Materialize chart data nightly (like RoutePerformanceDaily)
- Add API rate limiting for live health endpoint

---

## Data Scarcity Handling

### Minimum Sample Size Requirements

Each feature includes sample size checks:

```php
HAVING COUNT(*) >= 10  -- Minimum 10 observations
```

### Fallback UI for Insufficient Data

**Template pattern:**
```twig
{% if chartData.sample_size >= 10 %}
    {# Render chart #}
{% else %}
    <div class="text-center py-8 text-gray-500">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="mt-2 text-sm">Insufficient data for this analysis</p>
        <p class="text-xs">Need at least {{ chartData.min_sample_size }} observations</p>
    </div>
{% endif %}
```

### Confidence Indicators

**Show sample size and confidence level:**
```twig
<div class="text-xs text-gray-500 mt-2">
    Based on {{ chartData.sample_size }} observations
    (Confidence: {% if chartData.sample_size > 100 %}High{% elseif chartData.sample_size > 50 %}Medium{% else %}Low{% endif %})
</div>
```

---

## Code Quality Checklist

**Before committing:**
- [ ] All new methods have complete PHPDoc comments
- [ ] Use `readonly` properties where appropriate
- [ ] Follow existing naming conventions:
  - PascalCase for service classes
  - camelCase for methods
  - snake_case for database columns
- [ ] SQL queries use named parameters (`:route_id` not `?`)
- [ ] Handle null/missing data gracefully (null coalescing, early returns)
- [ ] Run `make cs-fix` to auto-fix code style
- [ ] No hardcoded values (use constants or config)
- [ ] Add Twig comments explaining complex chart logic
- [ ] Use ECharts for consistency with existing visualizations
- [ ] Test mobile responsiveness (Chrome DevTools at 320px width)
- [ ] No console.log() statements in production JS
- [ ] API endpoints return proper HTTP status codes

---

## Expected Outcomes

### For Users (Casual Riders)
- ✅ Live health gauge provides instant visual feedback
- ✅ Temporal delay curve shows "avoid this route at 5 PM"
- ✅ Data quality panel builds trust in insights

### For City Planners & Operators
- ✅ Stop-level reliability map identifies bottleneck intersections
- ✅ Schedule realism index provides evidence for schedule changes
- ✅ Delay propagation heatmap shows if padding is working
- ✅ System comparison helps prioritize which routes need intervention

### For Developers & Analysts
- ✅ Extensible chart service architecture for future features
- ✅ Reusable data quality metrics across application
- ✅ Real-time health monitoring foundation for alerts
- ✅ Pattern-making visible even with limited data

---

## Future Enhancements (Post-Launch)

### Phase 2 (Month 2-3)
1. **Chart Export:** Add PNG download buttons (ECharts built-in feature)
2. **Email Alerts:** Notify when route drops below health threshold
3. **Historical Comparison:** "This week vs last week" overlays
4. **Stop Detail Pages:** Deep-dive into individual stop performance

### Phase 3 (Month 4-6)
1. **Predictive ML Model:** Forecast delays based on patterns
2. **Weather Integration:** Overlay precipitation/temperature on charts
3. **Route Comparison Tool:** Side-by-side comparison of 2+ routes
4. **API Documentation:** Public API for third-party developers

### Advanced Analytics (Month 6+)
1. **Bunching Detection:** Populate BunchingIncident table from realtime data
2. **Passenger Load Estimation:** Correlate delays with ridership (if data available)
3. **Network Effect Analysis:** How delays on one route impact connecting routes
4. **Seasonal Patterns:** Year-over-year performance comparison

---

## Appendix: Sample Data Queries

### Generate Test Data (Development Only)

```php
// For testing with limited data, create sample arrival logs
docker compose exec php bin/console app:seed:performance-data --clear
```

### Manual Testing Queries

**Check stop-level reliability data:**
```sql
SELECT
    s.name,
    AVG(al.delay_sec) as avg_delay,
    COUNT(*) as samples
FROM arrival_log al
JOIN stop s ON al.stop_id = s.id
WHERE al.route_id = 123
GROUP BY s.name
ORDER BY avg_delay DESC;
```

**Check schedule realism:**
```sql
SELECT
    AVG(EXTRACT(EPOCH FROM (predicted_arrival_at - scheduled_arrival_at))) as avg_diff
FROM arrival_log
WHERE route_id = 123
  AND scheduled_arrival_at IS NOT NULL;
```

**Check data coverage:**
```sql
SELECT
    COUNT(DISTINCT trip_id) as tracked_trips,
    (SELECT COUNT(*) FROM trip) as total_trips,
    ROUND(COUNT(DISTINCT trip_id)::numeric / (SELECT COUNT(*) FROM trip) * 100, 1) as coverage_pct
FROM arrival_log
WHERE predicted_at >= CURRENT_DATE;
```

---

## Questions & Answers

**Q: What if we only have 1 day of data?**
A: All features are designed to work with minimal data. Stop-level reliability and temporal curves provide value even from 1 day (geographic patterns are stable). Show confidence levels and sample sizes.

**Q: How do we handle routes with no recent data?**
A: Display "Insufficient data" messages with sample size requirements. Data quality panel explains coverage gaps.

**Q: Will this slow down the route detail page?**
A: No. All queries use indexes and return <10K rows. Total page render time should be <2 seconds. Monitor with Symfony profiler.

**Q: Can we backfill historical data?**
A: Yes. Run `CollectDailyPerformanceCommand` with `--date` option for each historical date. Schedule realism ratio will be calculated retroactively.

**Q: How do we know if indexes are being used?**
A: Run `EXPLAIN ANALYZE` on queries. Look for "Index Scan" in query plan.

---

**Last Updated:** 2025-10-17
**Status:** Implementation Plan
**Owner:** @samuelwilk
**Estimated Effort:** 2 weeks (1 developer)
