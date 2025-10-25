# Small Sample Size Bias Bug

**Status:** 🔴 Active Bug (Discovered 2025-10-25)
**Severity:** High - Affects route ranking accuracy
**Impact:** Route list shows misleading performance rankings

## Summary

The route performance ranking system suffers from **small sample size bias**, where routes with limited service (few days of data) can achieve artificially high rankings due to statistical variance and luck.

**Symptom:** Low-frequency routes (2-5 days of data) rank higher than high-frequency routes (30 days of data) even when the latter have objectively better sustained performance.

## Root Cause

**File:** `src/Service/Dashboard/RoutePerformanceService.php:84-97`

The `getRouteListWithMetrics()` method calculates average on-time percentage **without filtering for minimum sample size**:

```php
// Current code - NO minimum sample size filter
$perf = $this->performanceRepo->findByRouteAndDateRange($route->getId(), $startDate, $endDate);

$avgOnTime = 0.0;
$count     = 0;
foreach ($perf as $p) {
    if ($p->getOnTimePercentage() !== null) {
        $avgOnTime += (float) $p->getOnTimePercentage();
        ++$count;
    }
}
$avgOnTime = $count > 0 ? $avgOnTime / $count : 0.0;
```

**The Problem:**
- ✅ Route with **2 good days** out of 30 possible: **95% on-time** → ranked #1
- ❌ Route with **all 30 days**: **78% on-time** → ranked lower (despite being more reliable)

## Why This Happens

### 1. Small Sample Size Fallacy
Routes that run infrequently can have inflated scores due to:
- **Statistical variance**: Small samples have high variance and don't regress to the mean
- **Luck**: A few good days don't represent true long-term performance
- **Selection bias**: Limited-service routes may only run during favorable conditions (off-peak hours, good weather)

### 2. Regression to the Mean
As sample size increases, performance converges toward the system average. Routes with only 2-3 days of data haven't had enough observations to reveal their true performance.

### 3. Confidence Intervals
- **2 days of data**: 95% confidence interval = ±40 percentage points (unreliable)
- **30 days of data**: 95% confidence interval = ±8 percentage points (reliable)

## Evidence

**Code Inconsistency:**
Other methods in the codebase already use minimum sample size filtering:

```php
// RoutePerformanceDailyRepository.php:356-380
public function findHistoricalTopPerformers(
    int $days = 30,
    int $minDays = 3,  // ✅ Has minimum sample size
    int $limit = 5
): array {
    // ...
    ->having('COUNT(p.id) >= :minDays')
    ->setParameter('minDays', $minDays)
```

**But the main route list doesn't use this pattern!**

## Impact Assessment

### User Experience
- **Misleading rankings**: Users trust that "top performers" are consistently reliable
- **Poor decision-making**: Transit planners may over-invest in routes with lucky data
- **Credibility risk**: Informed users notice statistical anomalies and lose trust

### Example Scenario

```
TOP PERFORMERS (Current - WRONG)
1. Route 99X (Weekend Express)     - 95% on-time (2 days of data)
2. Route 42 (Seasonal Service)     - 92% on-time (3 days of data)
3. Route 8 (Main Line)             - 78% on-time (30 days of data) ⚠️ Actually best!

BOTTOM PERFORMERS (Current)
1. Route 14 (Peak-Hour Service)    - 72% on-time (30 days of data)
2. Route 27 (All-Day Service)      - 74% on-time (30 days of data)
```

Route 8 is objectively the best performer (consistent 78% over 30 days), but ranks #3 behind routes with insufficient data.

## Proposed Fix

### Option 1: Hard Minimum Threshold (Simple)
```php
// Only include routes with at least 5 days of data
if ($count < 5) {
    $grade = 'N/A';
    $avgOnTime = 0.0;
}
```

**Pros:** Simple, easy to understand
**Cons:** Excludes valid routes, creates "cliff effect"

### Option 2: Bayesian Adjustment (Recommended)
```php
// Shrink estimates toward system mean for low-sample routes
$systemMean = $this->performanceRepo->getSystemMedianPerformance($startDate, $endDate);
$confidence = min(1.0, $count / 10.0); // Full confidence at 10+ days
$adjustedOnTime = ($avgOnTime * $confidence) + ($systemMean * (1 - $confidence));
```

**Pros:** Uses all data, statistically sound, smooth transition
**Cons:** More complex, requires system mean calculation

### Option 3: Confidence Badges (Complementary)
Add visual indicators to show data quality:

```php
// In RouteMetricDto
public readonly int $daysOfData;
public readonly string $confidenceLevel; // 'high' | 'medium' | 'low'
```

UI badges:
- 🟢 **High confidence** (10+ days)
- 🟡 **Medium confidence** (5-9 days)
- 🔴 **Limited data** (<5 days)

## Recommended Solution

**Implement all three:**
1. ✅ **Minimum threshold** (5 days) for inclusion in rankings
2. ✅ **Bayesian adjustment** for routes with 5-15 days of data
3. ✅ **Confidence badges** to show data quality in UI

This provides:
- Statistical rigor
- User transparency
- Smooth degradation for edge cases

## Implementation Plan

### 1. Update RouteMetricDto
```php
final readonly class RouteMetricDto
{
    public function __construct(
        // ... existing properties
        public int $daysOfData,           // NEW
        public string $confidenceLevel,   // NEW: 'high' | 'medium' | 'low'
    ) {}
}
```

### 2. Update RoutePerformanceService
```php
private function calculateAdjustedPerformance(
    float $rawAverage,
    int $daysOfData,
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array {
    // Get system baseline
    $systemMean = $this->performanceRepo->getSystemMedianPerformance($startDate, $endDate);

    // Apply Bayesian shrinkage
    $confidence = min(1.0, $daysOfData / 10.0);
    $adjusted = ($rawAverage * $confidence) + ($systemMean * (1 - $confidence));

    // Determine confidence level
    $confidenceLevel = match (true) {
        $daysOfData >= 10 => 'high',
        $daysOfData >= 5  => 'medium',
        default           => 'low',
    };

    return [
        'adjusted' => $adjusted,
        'confidence' => $confidenceLevel,
    ];
}
```

### 3. Add Repository Method
```php
// RoutePerformanceDailyRepository.php
public function getSystemMedianPerformance(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): float {
    // Return median on-time percentage across all routes
    // (already exists in codebase - see line 864)
}
```

### 4. Update UI Template
```twig
{# templates/route/list.html.twig #}
<div class="route-metric">
    <span class="grade">{{ route.grade }}</span>
    <span class="performance">{{ route.onTimePercentage }}%</span>

    {# NEW: Confidence badge #}
    {% if route.confidenceLevel == 'low' %}
        <span class="badge badge-warning">⚠️ Limited data ({{ route.daysOfData }} days)</span>
    {% elseif route.confidenceLevel == 'medium' %}
        <span class="badge badge-info">{{ route.daysOfData }} days</span>
    {% endif %}
</div>
```

## Testing Strategy

### Unit Tests
```php
// tests/Service/Dashboard/RoutePerformanceServiceTest.php
public function testSmallSampleSizeDoesNotInflateRankings(): void
{
    // Create route with 2 days of 100% performance
    $lowFreqRoute = $this->createRoute('99X', daysOfData: 2, avgOnTime: 100.0);

    // Create route with 30 days of 85% performance
    $highFreqRoute = $this->createRoute('8', daysOfData: 30, avgOnTime: 85.0);

    $metrics = $this->service->getRouteListWithMetrics();

    // High-frequency route should rank HIGHER after adjustment
    $this->assertGreaterThan(
        $this->findRoutePerformance($metrics, '99X'),
        $this->findRoutePerformance($metrics, '8')
    );
}
```

### Data Quality Checks
```sql
-- Find routes with suspicious high performance and low sample size
SELECT
    r.short_name,
    COUNT(DISTINCT rpd.date) as days,
    AVG(rpd.on_time_percentage) as avg_perf
FROM route r
JOIN route_performance_daily rpd ON rpd.route_id = r.id
WHERE rpd.date >= NOW() - INTERVAL '30 days'
GROUP BY r.id
HAVING COUNT(DISTINCT rpd.date) < 5 AND AVG(rpd.on_time_percentage) > 90
ORDER BY avg_perf DESC;
```

## Related Issues

- **Route 543 anomaly**: Nelson Road / Evergreen showing 17% on-time (investigating - see below)
- **Weather analysis bias**: Winter performance comparison may be affected by same issue
- **Historical rankings**: `findHistoricalTopPerformers()` already has fix but main list doesn't

## References

- [Wikipedia: Regression toward the mean](https://en.wikipedia.org/wiki/Regression_toward_the_mean)
- [Bayesian Average Calculation](https://en.wikipedia.org/wiki/Bayesian_average)
- [How Not To Sort By Average Rating](https://www.evanmiller.org/how-not-to-sort-by-average-rating.html)

## Changelog

- **2025-10-25**: Bug discovered during feature planning review
- **2025-10-25**: Documentation created, investigation ongoing

---

**Next Steps:**
1. ✅ Document bug (this file)
2. 🔄 Investigate Route 543 anomaly (17% performance)
3. ⏳ Implement Bayesian adjustment fix
4. ⏳ Add confidence badges to UI
5. ⏳ Write unit tests
6. ⏳ Deploy and verify in production
