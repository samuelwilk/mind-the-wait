# Enhanced Analytics Features - Recommendations

**Date:** 2025-10-25
**Status:** 📋 Analysis Complete
**Context:** Based on Route 543 investigation and sample size bias fix

## Executive Summary

After investigating Route 543 (17.6% on-time) and implementing the sample size bias fix, I've analyzed the 7 proposed analytics features and identified which provide the highest value, which can be combined, and which should be deferred.

**Key Insight:** Route 543's poor performance (17.6%) would have been immediately diagnosable with **Feature 3 (Schedule Realism Index)** and **Feature 1 (Stop-Level Reliability Map)**. These features should be prioritized.

---

## Feature-by-Feature Analysis

### ✅ Feature 1: Stop-Level Reliability Map
**Priority:** 🔴 **HIGH** (Implement first)

**Recommendation:** **IMPLEMENT AS-IS**

**Why:**
- **Directly addresses Route 543 problem**: Would show WHICH stops cause delays
- **Actionable for transit agencies**: Pinpoints bottleneck locations for traffic engineering
- **Works with limited data**: Geographic patterns stable even with 1 day of data
- **Already have infrastructure**: ArrivalLog table has all needed data

**Real-World Value:**
```
Route 543 Stop-Level Analysis (hypothetical):
- Stop 1 (Evergreen Blvd): +2 min delay
- Stop 5 (Nelson Road @ 8th): +8 min delay  ← BOTTLENECK IDENTIFIED
- Stop 12 (City Centre): -1 min (recovery)
```

**Enhancement Suggestion:**
Add confidence indicators based on sample size (use same logic as route list badges):
```php
public function findStopReliabilityData(...): array {
    // ... existing SQL ...
    HAVING COUNT(*) >= 10  // Already has minimum sample size!
}
```

**Estimated Effort:** 2 days

---

### ⚠️ Feature 2: Delay Propagation Visualization
**Priority:** 🟡 **MEDIUM** (Consider merging with Feature 1)

**Recommendation:** **MERGE into Feature 1 as optional secondary chart**

**Why:**
- **Overlaps with Feature 1**: Both analyze stop-level delay patterns
- **More complex**: Requires window functions and heatmap visualization
- **Less actionable**: Shows patterns but not specific bottlenecks like Feature 1

**Suggested Approach:**
- Implement Feature 1 first
- Add delay propagation as **optional toggle** on same chart:
  - Default view: Stop-level average delay (Feature 1)
  - Advanced view: Delay delta heatmap (Feature 2)

**Alternative:**
If delay propagation adds significant diagnostic value, implement as separate "Advanced Analytics" section.

**Estimated Effort:** 1-2 days (if implemented separately)

---

### ✅ Feature 3: Schedule Realism Index
**Priority:** 🔴 **HIGH** (Implement second, right after Feature 1)

**Recommendation:** **IMPLEMENT AS TOP PRIORITY**

**Why:**
- **Would have immediately flagged Route 543**: Ratio > 1.15 (severely under-scheduled)
- **Most actionable metric**: Transit agencies can adjust schedules directly
- **Objective measurement**: Removes guesswork from schedule planning
- **Low implementation complexity**: Single repository method + enum

**Real-World Application:**
```
Route 543 Schedule Realism Analysis:
- Scheduled travel time: 45 minutes
- Actual average travel time: 60 minutes
- Ratio: 1.33 (severely under-scheduled!)
- Grade: "Severely Under-scheduled"
- Recommendation: Add 15 minutes to schedule
```

**Enhancement Suggestions:**

1. **Add to route list table** (not just detail page):
```twig
<!-- Show schedule realism grade next to on-time percentage -->
<span class="text-xs {{ route.scheduleRealismGrade.getColorClass() }}">
    Schedule: {{ route.scheduleRealismGrade.value }}
</span>
```

2. **Integrate with sample size confidence**:
```php
// Only show schedule realism if sufficient data
if ($route->daysOfData >= 5 && $route->scheduleRealism !== null) {
    // Display schedule realism grade
}
```

3. **Add to daily aggregation pipeline**:
```php
// PerformanceAggregator.php
$ratio = $this->arrivalLogRepo->calculateScheduleRealismRatio($route->getId(), $date);
$performance->setScheduleRealismRatio($ratio);
```

**Estimated Effort:** 1-2 days (including migration)

---

### ⚠️ Feature 4: Temporal Delay Curve
**Priority:** 🟢 **LOW** (Consider skipping)

**Recommendation:** **SKIP or merge with existing time-of-day heatmap**

**Why:**
- **Already have time-of-day heatmap**: Shows performance by day × hour
- **Redundant**: Temporal delay curve adds minimal new insight
- **Existing heatmap is more comprehensive**: 2D (day + hour) vs 1D (hour only)

**Alternative:**
If temporal patterns are important, **enhance the existing heatmap** instead:
```php
// Add tooltip showing exact delay seconds (not just on-time %)
'tooltip' => [
    'formatter' => 'function(params) {
        return params.marker + " " + params.value[2] + "% on-time" +
               "<br/>Avg Delay: " + delaySeconds[params.dataIndex] + " sec";
    }'
]
```

**Verdict:** Skip as standalone feature, enhance existing heatmap if needed.

---

### ✅ Feature 5: Reliability Context Panel
**Priority:** 🟡 **MEDIUM** (Partially implemented!)

**Recommendation:** **IMPLEMENT using existing infrastructure**

**Why:**
- **We already have `getSystemMedianPerformance()`**: Implemented for Bayesian adjustment!
- **Low incremental cost**: Ranking logic is straightforward
- **Provides valuable context**: "Route ranks #5 out of 40 (top 12%)"

**Implementation Note:**
```php
// RoutePerformanceDailyRepository.php
// ✅ ALREADY EXISTS (line 498-529)
public function getSystemMedianPerformance(...): float

// NEW: Just need ranking method
public function getRoutePerformanceRanking(...): array
// ^^ This is already shown in the doc, just needs implementation
```

**Enhancement Suggestion:**
Show system comparison on route detail page:
```twig
<div class="bg-gray-50 border-l-4 border-primary-500 p-4">
    <p class="text-sm text-gray-700">
        <strong>System Comparison:</strong>
        Route ranks <strong>#{{ systemComparison.routeRank }}</strong> out of
        {{ systemComparison.totalRoutes }} routes (top {{ systemComparison.percentile }}%)
    </p>
    <p class="text-xs text-gray-500 mt-1">
        System median: {{ systemComparison.systemMedianOnTime }}% on-time
    </p>
</div>
```

**Estimated Effort:** 0.5-1 day (reuses existing code!)

---

### ⚠️ Feature 6: Live Route Health Gauge
**Priority:** 🟢 **LOW** (Redundant)

**Recommendation:** **SKIP as separate feature**

**Why:**
- **Already have `/api/realtime` endpoint**: Returns all vehicle status data
- **Already have `VehicleStatusService`**: Calculates red/yellow/green status
- **Redundant with existing infrastructure**: Same data, different endpoint

**Alternative:**
If live health is needed for iOS app or frontend, **enhance existing realtime endpoint** instead:
```php
// /api/realtime response already includes:
{
  "vehicles": [
    {"id": "123", "status": "late", "delay_sec": 240},
    {"id": "456", "status": "on_time", "delay_sec": 30}
  ],
  // ADD: Route-level aggregation
  "route_health": {
    "543": {
      "health_percent": 17.6,
      "active_vehicles": 3,
      "late_vehicles": 2,
      "on_time_vehicles": 1
    }
  }
}
```

**Verdict:** Skip as standalone feature, integrate into existing `/api/realtime` if needed.

---

### ⚠️ Feature 7: Data Integrity / Coverage Diagnostics
**Priority:** 🟢 **LOW** (Admin tool, not user-facing)

**Recommendation:** **DEFER to admin dashboard project**

**Why:**
- **Not user-facing**: Diagnostic tool for operators/developers
- **Better suited for admin panel**: Separate from public route pages
- **Low impact on user value**: Doesn't help riders or planners directly

**Alternative:**
If data quality is a concern, add **simple health check endpoint**:
```php
// /api/health
{
  "status": "healthy",
  "last_update": "2025-10-25T18:30:00Z",
  "data_quality": "good",
  "tracked_routes": 40,
  "active_vehicles": 87
}
```

**Verdict:** Skip for now, revisit if building admin dashboard.

---

## Recommended Implementation Priority

Based on Route 543 investigation and actual user value:

### Phase 1: Critical Diagnostics (Week 1)
1. ✅ **Feature 3: Schedule Realism Index** (1-2 days)
   - Would have immediately flagged Route 543
   - Most actionable for transit agencies
   - Add to daily aggregation pipeline

2. ✅ **Feature 1: Stop-Level Reliability Map** (2 days)
   - Identifies bottleneck locations
   - Complements Schedule Realism Index
   - Add confidence indicators

### Phase 2: System Context (Week 2)
3. ✅ **Feature 5: Reliability Context Panel** (0.5-1 day)
   - Reuses existing `getSystemMedianPerformance()`
   - Low effort, nice value-add
   - Shows percentile ranking

### Phase 3: Advanced Analytics (Optional)
4. ⚠️ **Feature 2: Delay Propagation** (1-2 days)
   - Only if Feature 1 proves insufficient
   - Consider as "Advanced View" toggle
   - Optional heatmap visualization

### Deferred Features
- ❌ **Feature 4: Temporal Delay Curve** - Redundant with existing heatmap
- ❌ **Feature 6: Live Route Health** - Redundant with existing `/api/realtime`
- ❌ **Feature 7: Data Integrity** - Admin tool, not user-facing

---

## New Feature Suggestions

Based on Route 543 investigation, here are **additional features** not in the original document:

### A. Route Problem Summary Card

**Concept:** Auto-generate diagnostic summary for routes like 543.

**Example:**
```
⚠️ Route 543 Performance Alert

Problem: Chronic lateness (17.6% on-time)
Root Cause: Unrealistic schedule (ratio 1.33)
Recommendation: Add 15 minutes to scheduled travel time

Top 3 Bottleneck Stops:
1. Nelson Road @ 8th St (+8 min avg delay)
2. Evergreen Blvd @ Main (+5 min avg delay)
3. City Centre Terminal (+3 min avg delay)

Confidence: Medium (8 days of data)
```

**Implementation:**
```php
// New service: RouteProblemsService
public function generateProblemSummary(Route $route): ?RouteProblemDto
{
    // Only show if confident AND performance is poor
    if ($route->daysOfData < 5 || $route->onTimePercentage > 50) {
        return null;
    }

    // Analyze: Schedule realism, stop reliability, temporal patterns
    // Return: Actionable summary
}
```

**Value:** Provides **actionable recommendations** instead of just charts.

**Estimated Effort:** 2-3 days

---

### B. Historical Trend Alerts

**Concept:** Flag routes with declining performance.

**Example:**
```
📉 Route 14 Performance Declining

Last 7 days: 62% on-time (↓ 15% from previous week)
Possible causes:
- New construction on Main St?
- Schedule change?
- Weather impact?

Action: Review recent service changes
```

**Implementation:**
```php
public function detectPerformanceAlerts(): array
{
    // Compare last 7 days vs previous 7 days
    // Flag routes with >10% drop
    // Return list of RouteTrendAlertDto
}
```

**Value:** **Proactive alerting** for transit agencies.

**Estimated Effort:** 1-2 days

---

### C. Minimum Sample Size Integration

**Concept:** Apply sample size confidence to ALL new features (not just route list).

**Implementation:**
```php
// Stop-level reliability
if ($stop->sampleSize < 10) {
    // Show with "⚠️ Limited data" badge
}

// Schedule realism
if ($daysCalculated < 5) {
    // Show as "Insufficient data" instead of ratio
}

// Delay propagation heatmap
// Only show cells with COUNT(*) >= 5
```

**Value:** **Consistent data quality standards** across all features.

**Estimated Effort:** 0.5 days (integrate into existing features)

---

## Updated Effort Estimate

**Original Estimate:** 3 weeks (all 7 features)

**Recommended Scope:**
- Phase 1 (Critical): 3-4 days
  - Feature 3: Schedule Realism Index (1-2 days)
  - Feature 1: Stop-Level Reliability (2 days)

- Phase 2 (Context): 1 day
  - Feature 5: Reliability Context Panel (0.5-1 day)

- Phase 3 (Advanced): 1-2 days
  - Feature 2: Delay Propagation (optional)

**Total:** 5-7 days instead of 15 days (67% time savings!)

---

## Integration with Existing Systems

### 1. Sample Size Bias Fix (Just Implemented)
- ✅ Use same confidence logic for all new features
- ✅ Show "⚠️ Limited data" badges consistently
- ✅ Apply Bayesian adjustment where appropriate

### 2. Route 543 Investigation (Just Completed)
- ✅ Schedule Realism Index would have immediately identified the problem
- ✅ Stop-Level Reliability would show WHERE delays originate
- ✅ Real-world proof of feature value

### 3. Daily Aggregation Pipeline
```php
// PerformanceAggregator.php (existing)
public function aggregateDate(\DateTimeImmutable $date): array
{
    // ... existing aggregations ...

    // NEW: Add schedule realism calculation
    $ratio = $this->arrivalLogRepo->calculateScheduleRealismRatio(
        $route->getId(),
        $date
    );
    $performance->setScheduleRealismRatio($ratio);

    // All other features pull from aggregated data (fast!)
}
```

---

## Risk Assessment

### Low Risk Features (Recommend)
- ✅ **Feature 1**: Stop-Level Reliability
  - Uses existing data model
  - Well-defined SQL query
  - Clear visualization

- ✅ **Feature 3**: Schedule Realism Index
  - Simple calculation (ratio)
  - Low database impact
  - High actionability

- ✅ **Feature 5**: Reliability Context
  - Reuses existing code
  - Minimal new logic
  - Nice-to-have, not critical

### Medium Risk Features (Optional)
- ⚠️ **Feature 2**: Delay Propagation
  - Complex window function SQL
  - Heatmap visualization complexity
  - May overlap with Feature 1

### High Risk Features (Skip)
- ❌ **Feature 4**: Temporal Delay Curve
  - Redundant with existing heatmap
  - Low incremental value

- ❌ **Feature 6**: Live Route Health
  - Redundant with existing API
  - Maintenance burden

- ❌ **Feature 7**: Data Integrity
  - Not user-facing
  - Better as admin tool

---

## Final Recommendations

### Implement (High Value)
1. **Feature 3: Schedule Realism Index** - Addresses Route 543 root cause
2. **Feature 1: Stop-Level Reliability Map** - Identifies bottleneck locations
3. **Feature 5: Reliability Context Panel** - Reuses existing code, low effort

### Consider (Medium Value)
4. **Feature 2: Delay Propagation** - If Feature 1 proves insufficient

### Skip (Low Value / Redundant)
5. ❌ Feature 4: Temporal Delay Curve
6. ❌ Feature 6: Live Route Health Gauge
7. ❌ Feature 7: Data Integrity Diagnostics

### New Suggestions
- **Route Problem Summary Card** - Auto-generated diagnostics
- **Historical Trend Alerts** - Proactive performance monitoring
- **Consistent Sample Size Integration** - Quality standards across all features

---

**Total Recommended Effort:** 5-7 days (Features 1, 3, 5)
**ROI:** High - Directly addresses real-world problems (Route 543)
**Risk:** Low - Uses existing infrastructure

**Next Step:** Implement Feature 3 (Schedule Realism Index) first, as it would have immediately flagged Route 543's unrealistic schedule.
