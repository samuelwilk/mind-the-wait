# Bunching Incidents by Weather: Normalization Implementation

## Problem Statement

The current "Bunching by Weather Conditions" chart shows **raw incident counts** for each weather condition over the last 30 days:

- **Clear:** 3,085 incidents
- **Cloudy:** 1,650 incidents
- **Snow:** 0 incidents
- **Rain:** 0 incidents

**Why this is misleading:**

If Saskatoon has clear weather 80% of the time and cloudy weather 20% of the time, we'd naturally expect more incidents during clear weather simply due to **exposure time**. The chart doesn't account for how long each weather condition lasted.

### Example Scenario

Assume 30 days = 720 hours total:

| Condition | Hours | Raw Incidents | Incidents/Hour |
|-----------|-------|---------------|----------------|
| Clear     | 576h (80%) | 3,085 | **5.35/hour** |
| Cloudy    | 144h (20%) | 1,650 | **11.46/hour** |

**Key insight:** Cloudy weather has **2.14× higher bunching rate** than clear weather, even though clear weather has more total incidents.

---

## Proposed Solution

### Metric: Incidents per Hour

**Formula:**
```
Incidents per Hour = Total Incidents / Hours of Weather Condition
```

**Why hourly rate?**
- More intuitive than "per day" (weather changes multiple times per day)
- Allows comparison across conditions with different exposure times
- Easier to interpret than raw counts

**Alternative metrics considered:**
- ❌ **Incidents per day:** Too coarse (weather changes intraday)
- ❌ **Incidents per observation:** Depends on observation frequency
- ✅ **Incidents per hour:** Standard rate metric, easy to communicate

---

## Implementation Plan

### Phase 1: Backend Changes

#### 1.1 Update `BunchingIncidentRepository`

**File:** `src/Repository/BunchingIncidentRepository.php`

**Add new method:**

```php
/**
 * Count bunching incidents by weather condition with exposure hours.
 *
 * Returns incidents per hour for better comparison across weather conditions.
 *
 * @return list<array{
 *     weather_condition: string,
 *     incident_count: int,
 *     exposure_hours: float,
 *     incidents_per_hour: float
 * }>
 */
public function countByWeatherConditionNormalized(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = <<<'SQL'
        WITH weather_durations AS (
            -- Calculate hours spent in each weather condition
            SELECT
                weather_condition,
                COUNT(DISTINCT DATE_TRUNC('hour', observed_at)) as exposure_hours
            FROM weather_observation
            WHERE observed_at >= :start_date
              AND observed_at < :end_date
              AND weather_condition IS NOT NULL
            GROUP BY weather_condition
        ),
        incident_counts AS (
            -- Count bunching incidents by weather condition
            SELECT
                w.weather_condition,
                COUNT(bi.id) as incident_count
            FROM bunching_incident bi
            LEFT JOIN weather_observation w ON bi.weather_observation_id = w.id
            WHERE bi.detected_at >= :start_date
              AND bi.detected_at < :end_date
              AND w.weather_condition IS NOT NULL
            GROUP BY w.weather_condition
        )
        SELECT
            COALESCE(wd.weather_condition, ic.weather_condition) as weather_condition,
            COALESCE(ic.incident_count, 0) as incident_count,
            COALESCE(wd.exposure_hours, 0) as exposure_hours,
            CASE
                WHEN COALESCE(wd.exposure_hours, 0) > 0
                THEN COALESCE(ic.incident_count, 0)::float / wd.exposure_hours
                ELSE 0
            END as incidents_per_hour
        FROM weather_durations wd
        FULL OUTER JOIN incident_counts ic ON wd.weather_condition = ic.weather_condition
        WHERE COALESCE(wd.exposure_hours, 0) > 0  -- Exclude conditions with no exposure
        ORDER BY incidents_per_hour DESC
    SQL;

    $results = $conn->executeQuery($sql, [
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date'   => $endDate->format('Y-m-d H:i:s'),
    ])->fetchAllAssociative();

    return array_map(function ($row) {
        return [
            'weather_condition'   => $row['weather_condition'],
            'incident_count'      => (int) $row['incident_count'],
            'exposure_hours'      => (float) $row['exposure_hours'],
            'incidents_per_hour'  => round((float) $row['incidents_per_hour'], 2),
        ];
    }, $results);
}
```

**Key SQL logic:**

1. **`weather_durations` CTE:** Counts distinct hours for each weather condition
   - Uses `DATE_TRUNC('hour', observed_at)` to get hourly buckets
   - Groups by `weather_condition`

2. **`incident_counts` CTE:** Counts bunching incidents per weather condition
   - Joins `bunching_incident` with `weather_observation`
   - Groups by `weather_condition`

3. **Final query:** Joins both CTEs and calculates `incidents_per_hour`
   - Handles division by zero
   - Excludes conditions with no exposure time
   - Orders by rate (descending) to show worst conditions first

---

#### 1.2 Update `WeatherAnalysisService`

**File:** `src/Service/Dashboard/WeatherAnalysisService.php`

**Update method:**

```php
/**
 * Build bunching by weather chart with normalized rates.
 *
 * @return array<string, mixed>
 */
private function buildBunchingByWeatherChart(): array
{
    $endDate   = new \DateTimeImmutable('today');
    $startDate = $endDate->modify('-30 days');

    // Use normalized data (incidents per hour)
    $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

    // Build data arrays
    $conditionMap = [
        'snow'        => ['label' => 'Snow', 'color' => '#ede9fe'],
        'rain'        => ['label' => 'Rain', 'color' => '#dbeafe'],
        'cloudy'      => ['label' => 'Cloudy', 'color' => '#e5e7eb'],
        'clear'       => ['label' => 'Clear', 'color' => '#fef3c7'],
        'showers'     => ['label' => 'Showers', 'color' => '#bfdbfe'],
        'thunderstorm'=> ['label' => 'Thunderstorm', 'color' => '#1e293b'],
    ];

    $data = [];
    foreach ($conditionMap as $condition => $config) {
        $incidentsPerHour = 0;
        $exposureHours = 0;

        foreach ($results as $row) {
            if (strtolower($row['weather_condition']) === $condition) {
                $incidentsPerHour = $row['incidents_per_hour'];
                $exposureHours = $row['exposure_hours'];
                break;
            }
        }

        $data[] = [
            'value'     => $incidentsPerHour,
            'itemStyle' => ['color' => $config['color']],
            // Store metadata for tooltip
            'exposureHours' => $exposureHours,
        ];
    }

    $conditions = array_column($conditionMap, 'label');
    $totalIncidents = array_sum(array_column($results, 'incident_count'));
    $hasData = $totalIncidents > 0;

    return [
        'title' => [
            'text'      => 'Bunching Rate by Weather Condition',
            'subtext'   => $hasData ? 'Incidents per hour (last 30 days)' : 'No data available yet',
            'left'      => 'center',
            'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
        ],
        'tooltip' => [
            'trigger'     => 'axis',
            'axisPointer' => ['type' => 'shadow'],
            // Formatter will be set in JavaScript
        ],
        'xAxis' => [
            'type' => 'category',
            'data' => $conditions,
        ],
        'yAxis' => [
            'type'          => 'value',
            'name'          => 'Incidents/Hour',
            'nameLocation'  => 'middle',
            'nameGap'       => 50,
            'nameTextStyle' => ['fontSize' => 11],
            'min'           => 0,
            'axisLabel'     => [
                'formatter' => '{value}',
            ],
        ],
        'series' => [
            [
                'name'  => 'Bunching Rate',
                'type'  => 'bar',
                'data'  => $data,
                'label' => [
                    'show'      => $hasData,
                    'position'  => 'top',
                    'formatter' => '{c}', // Will be customized in JS
                    'fontSize'  => 11,
                ],
            ],
        ],
        'graphic' => $hasData ? [] : [
            [
                'type'  => 'text',
                'left'  => 'center',
                'top'   => 'middle',
                'style' => [
                    'text'       => "No bunching data yet\n\nRun 'app:detect:bunching' command\nto analyze arrival patterns",
                    'fontSize'   => 14,
                    'fill'       => '#94a3b8',
                    'textAlign'  => 'center',
                    'fontWeight' => 'normal',
                ],
            ],
        ],
        'grid' => [
            'left'         => '50',
            'right'        => '4%',
            'bottom'       => '10%',
            'containLabel' => true,
        ],
    ];
}
```

**Changes:**
1. **Y-axis:** Changed from "Incidents" to "Incidents/Hour"
2. **Data:** Uses `incidents_per_hour` instead of raw counts
3. **Subtitle:** Clarifies metric ("Incidents per hour")
4. **Tooltip:** Will show exposure hours (implemented in JS)

---

#### 1.3 Update Statistics Method

**File:** `src/Service/Dashboard/WeatherAnalysisService.php`

```php
/**
 * Build statistics for bunching story card with normalized rates.
 *
 * @return array<string, mixed>
 */
private function buildBunchingByWeatherStats(): array
{
    $endDate   = new \DateTimeImmutable('today');
    $startDate = $endDate->modify('-30 days');

    $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

    $snowRate  = 0;
    $rainRate  = 0;
    $clearRate = 0;
    $snowHours = 0;
    $rainHours = 0;
    $clearHours = 0;

    foreach ($results as $row) {
        $condition = strtolower($row['weather_condition']);

        match ($condition) {
            'snow'  => [
                $snowRate = $row['incidents_per_hour'],
                $snowHours = $row['exposure_hours'],
            ],
            'rain'  => [
                $rainRate = $row['incidents_per_hour'],
                $rainHours = $row['exposure_hours'],
            ],
            'clear' => [
                $clearRate = $row['incidents_per_hour'],
                $clearHours = $row['exposure_hours'],
            ],
            default => null,
        };
    }

    // Calculate multiplier (how much worse is snow vs clear?)
    $multiplier = $clearRate > 0 ? round($snowRate / $clearRate, 1) : 0.0;
    $hasData = count($results) > 0;

    return [
        'snow_rate'    => $snowRate,
        'rain_rate'    => $rainRate,
        'clear_rate'   => $clearRate,
        'snow_hours'   => $snowHours,
        'rain_hours'   => $rainHours,
        'clear_hours'  => $clearHours,
        'multiplier'   => $multiplier,
        'hasData'      => $hasData,
    ];
}
```

**Changes:**
- Returns `*_rate` (incidents per hour) instead of raw incident counts
- Returns `*_hours` (exposure time) for transparency
- Calculates multiplier based on rates, not raw counts

---

### Phase 2: Frontend Changes

#### 2.1 Update Chart Controller

**File:** `assets/controllers/chart_controller.js`

**Add tooltip formatter for bunching chart:**

```javascript
initializeChart() {
    // ... existing code ...

    const options = this.optionsValue;

    // ... existing heatmap and scatter fixes ...

    // Fix bunching by weather tooltip
    if (options.title?.text?.includes('Bunching Rate by Weather')) {
        options.tooltip.formatter = function(params) {
            const condition = params[0].name;
            const rate = params[0].value;
            const exposureHours = params[0].data.exposureHours || 0;

            return `
                <strong>${condition}</strong><br/>
                Rate: ${rate.toFixed(2)} incidents/hour<br/>
                Exposure: ${exposureHours.toFixed(0)} hours
            `;
        };

        // Update label formatter to show rate with decimals
        options.series[0].label.formatter = function(params) {
            return params.value.toFixed(2);
        };
    }

    this.chart.setOption(options);
}
```

**Tooltip output example:**
```
Snow
Rate: 15.43 incidents/hour
Exposure: 72 hours
```

---

#### 2.2 Update Template (Optional)

**File:** `templates/dashboard/weather.html.twig`

**Add explanation text below chart:**

```twig
{# Bunching by Weather Chart #}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 sm:p-6 mb-8">
    <div
        data-controller="chart"
        data-chart-options-value="{{ weatherImpact.bunchingByWeatherChart|json_encode|e('html_attr') }}"
        style="width: 100%; height: 350px; min-height: 350px; position: relative; z-index: 1;">
    </div>

    {# Explanation banner #}
    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-start">
            <svg class="h-5 w-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-xs sm:text-sm text-blue-800">
                <strong>About this metric:</strong> Values show bunching incidents per hour of each weather condition.
                This accounts for how much time was spent in each condition, allowing fair comparison.
                For example, if it's clear 80% of the time, we'd naturally see more raw incidents during clear weather.
            </div>
        </div>
    </div>
</div>
```

---

### Phase 3: AI Insight Updates

#### 3.1 Update Insight Generator

**File:** `src/Service/Dashboard/InsightGeneratorService.php`

**Update prompt:**

```php
public function generateBunchingByWeatherInsight(array $stats): string
{
    if (!$stats['hasData']) {
        return 'No bunching data available yet. Run the bunching detection command to analyze arrival patterns.';
    }

    $prompt = <<<PROMPT
        Based on 30 days of transit data for Saskatoon Transit:

        Bunching rates by weather condition (incidents per hour):
        - Snow: {$stats['snow_rate']} incidents/hour ({$stats['snow_hours']} hours exposure)
        - Rain: {$stats['rain_rate']} incidents/hour ({$stats['rain_hours']} hours exposure)
        - Clear: {$stats['clear_rate']} incidents/hour ({$stats['clear_hours']} hours exposure)

        Snow weather has a {$stats['multiplier']}× higher bunching rate than clear weather.

        Write a 2-3 sentence insight explaining:
        1. Which weather condition has the highest bunching rate
        2. Why this might occur (operational challenges, driver behavior, traffic)
        3. One actionable recommendation for transit operators

        Use plain language. No jargon. Be specific to Saskatoon's winter climate.
        PROMPT;

    return $this->generateInsight($prompt, 'bunching_weather');
}
```

**Example AI output:**

> "Snow conditions show a 2.3× higher bunching rate (15.4 incidents/hour) compared to clear weather (6.7 incidents/hour), despite only occurring 10% of the time. This is likely due to reduced speeds and unpredictable traffic flow during snowfall, causing buses to cluster together. Operators should consider adding 5-minute buffer time to schedules during active snow periods to maintain proper headway spacing."

---

### Phase 4: Testing & Validation

#### 4.1 Unit Tests

**File:** `tests/Repository/BunchingIncidentRepositoryTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\BunchingIncidentRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BunchingIncidentRepositoryTest extends KernelTestCase
{
    private BunchingIncidentRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(BunchingIncidentRepository::class);
    }

    public function testCountByWeatherConditionNormalized(): void
    {
        $endDate   = new \DateTimeImmutable('today');
        $startDate = $endDate->modify('-30 days');

        $results = $this->repository->countByWeatherConditionNormalized($startDate, $endDate);

        // Assertions
        $this->assertIsArray($results);

        foreach ($results as $row) {
            $this->assertArrayHasKey('weather_condition', $row);
            $this->assertArrayHasKey('incident_count', $row);
            $this->assertArrayHasKey('exposure_hours', $row);
            $this->assertArrayHasKey('incidents_per_hour', $row);

            // Validate data types
            $this->assertIsString($row['weather_condition']);
            $this->assertIsInt($row['incident_count']);
            $this->assertIsFloat($row['exposure_hours']);
            $this->assertIsFloat($row['incidents_per_hour']);

            // Validate logic: rate = incidents / hours
            if ($row['exposure_hours'] > 0) {
                $expectedRate = $row['incident_count'] / $row['exposure_hours'];
                $this->assertEqualsWithDelta(
                    $expectedRate,
                    $row['incidents_per_hour'],
                    0.01,
                    'Rate calculation should be correct'
                );
            }
        }
    }

    public function testNormalizationHandlesZeroExposure(): void
    {
        // Edge case: What if a weather condition has 0 exposure hours?
        // It should be excluded from results (filtered by WHERE clause)

        $endDate   = new \DateTimeImmutable('2025-01-01');
        $startDate = $endDate->modify('-1 day');

        $results = $this->repository->countByWeatherConditionNormalized($startDate, $endDate);

        foreach ($results as $row) {
            $this->assertGreaterThan(0, $row['exposure_hours'], 'No zero-exposure conditions should be returned');
        }
    }
}
```

---

#### 4.2 Manual Testing Checklist

**Before deploying:**

- [ ] Run `app:detect:bunching` command to populate data
- [ ] Verify weather observations exist for last 30 days
- [ ] Check chart renders correctly in browser
- [ ] Hover over bars to verify tooltip shows rate + exposure hours
- [ ] Verify Y-axis label says "Incidents/Hour"
- [ ] Verify chart subtitle says "Incidents per hour (last 30 days)"
- [ ] Check AI insight mentions "rate" and "hours exposure"
- [ ] Test with different date ranges (7 days, 60 days)
- [ ] Test with zero bunching incidents (should show "No data" message)

**SQL validation query:**

```sql
-- Verify normalization logic manually
WITH weather_durations AS (
    SELECT
        weather_condition,
        COUNT(DISTINCT DATE_TRUNC('hour', observed_at)) as exposure_hours
    FROM weather_observation
    WHERE observed_at >= NOW() - INTERVAL '30 days'
      AND weather_condition IS NOT NULL
    GROUP BY weather_condition
),
incident_counts AS (
    SELECT
        w.weather_condition,
        COUNT(bi.id) as incident_count
    FROM bunching_incident bi
    LEFT JOIN weather_observation w ON bi.weather_observation_id = w.id
    WHERE bi.detected_at >= NOW() - INTERVAL '30 days'
      AND w.weather_condition IS NOT NULL
    GROUP BY w.weather_condition
)
SELECT
    wd.weather_condition,
    ic.incident_count,
    wd.exposure_hours,
    ic.incident_count::float / wd.exposure_hours as incidents_per_hour
FROM weather_durations wd
JOIN incident_counts ic ON wd.weather_condition = ic.weather_condition
ORDER BY incidents_per_hour DESC;
```

---

## Migration Path

### Step 1: Deploy Backend Changes

```bash
# No database migration needed (uses existing tables)

# Deploy updated code
git add src/Repository/BunchingIncidentRepository.php
git add src/Service/Dashboard/WeatherAnalysisService.php
git commit -m "feat: normalize bunching incidents by weather exposure time"

# Deploy to staging
git push origin main
```

### Step 2: Deploy Frontend Changes

```bash
# Update chart controller
git add assets/controllers/chart_controller.js
git add templates/dashboard/weather.html.twig
git commit -m "feat: add exposure hours to bunching weather tooltip"

# Build assets
npm run build

# Deploy
git push origin main
```

### Step 3: Verify in Production

1. Navigate to `/weather-impact`
2. Scroll to "Bunching Incidents by Weather" chart
3. Verify Y-axis shows "Incidents/Hour"
4. Hover over bars to verify tooltip format
5. Check AI insight text below chart

---

## Expected Results

### Before (Raw Counts)

| Condition | Incidents | Exposure | Misleading Interpretation |
|-----------|-----------|----------|---------------------------|
| Clear     | 3,085     | ?        | "Clear weather is worst for bunching" |
| Cloudy    | 1,650     | ?        | "Cloudy weather has half the incidents" |

### After (Normalized Rates)

| Condition | Incidents | Exposure | Rate | Correct Interpretation |
|-----------|-----------|----------|------|------------------------|
| Clear     | 3,085     | 576h     | 5.35/hr | "Clear weather has moderate bunching" |
| Cloudy    | 1,650     | 144h     | 11.46/hr | "Cloudy weather has 2× higher bunching rate" |

**Key insight:** Cloudy weather is actually worse for bunching when accounting for exposure time.

---

## Alternative Approaches Considered

### ❌ Option 1: Incidents per Day

**Formula:** `incidents / days_with_condition`

**Why rejected:**
- Weather changes multiple times per day (morning clear, afternoon cloudy)
- Loses granularity of hourly observations
- Harder to interpret ("5 incidents per day" vs "5 incidents per hour")

---

### ❌ Option 2: Percentage of Total Incidents

**Formula:** `(incidents_in_condition / total_incidents) × 100`

**Why rejected:**
- Doesn't account for exposure time
- Still misleading (same problem as raw counts)
- Example: Clear weather = 65% of incidents, but was that 65% of time?

---

### ✅ Option 3: Incidents per Hour (Selected)

**Formula:** `incidents / hours_of_condition`

**Why selected:**
- Standard rate metric (incidents/time)
- Accounts for exposure time
- Easy to interpret and compare
- Aligns with transit industry standards

---

## Future Enhancements

### 1. Confidence Intervals

Show error bars for rates with low exposure time:

```php
'error_margin' => $exposureHours < 24 ? 'Low confidence (<24h data)' : 'High confidence'
```

### 2. Multi-City Comparison

When multi-city support is added, compare normalized rates across cities:

```
Saskatoon: 5.3 incidents/hour (clear)
Regina: 7.8 incidents/hour (clear)
```

### 3. Time-of-Day Breakdown

Show bunching rate by weather × hour:

```
Clear weather:
  - Rush hour (7-9 AM): 8.2 incidents/hour
  - Midday (12-2 PM): 3.1 incidents/hour
```

### 4. Seasonal Trends

Compare bunching rates across seasons:

```
Winter (Dec-Feb): 12.3 incidents/hour (snow)
Summer (Jun-Aug): 4.7 incidents/hour (clear)
```

---

## Success Metrics

**After deploying this change, measure:**

1. **User understanding:** Survey users: "Does the normalized chart help you understand weather impact better?"
2. **AI insight quality:** Do AI-generated insights mention exposure time?
3. **Decision-making:** Did transit operators use this data to adjust schedules?
4. **Data accuracy:** Compare predicted bunching rates to actual observed rates

---

## Rollback Plan

If normalization causes confusion or bugs:

1. **Revert backend changes:**
   ```bash
   git revert HEAD~2  # Revert last 2 commits
   git push origin main
   ```

2. **Revert to raw counts:**
   - Change `countByWeatherConditionNormalized()` back to `countByWeatherCondition()`
   - Change Y-axis back to "Incidents"
   - Remove exposure hours from tooltip

3. **Keep historical data:**
   - No database changes, so no data loss
   - Can re-run analysis anytime

---

## Conclusion

**What we're changing:**
- ❌ Raw incident counts (misleading)
- ✅ Incidents per hour (accounts for exposure)

**Why it matters:**
- Provides fair comparison across weather conditions
- Enables data-driven scheduling decisions
- Improves AI insight accuracy

**Implementation effort:**
- Backend: 1 new repository method + service updates (~2 hours)
- Frontend: Tooltip formatter + chart labels (~1 hour)
- Testing: Unit tests + manual verification (~1 hour)
- **Total:** ~4 hours development + deployment

**Next steps:**
1. Review this document
2. Implement backend changes (Phase 1)
3. Test SQL query manually
4. Deploy frontend changes (Phase 2)
5. Verify in production (Phase 3)

---

**Document Version:** 1.0
**Last Updated:** 2025-10-18
**Author:** Implementation Plan
**Status:** Ready for Implementation
