# API Roadmap for Dashboard Features

This document outlines the API endpoints needed to power the public dashboard and rider utility features.

## Current API Status

### ✅ Already Implemented

```
GET  /api/realtime                         # Full system snapshot
GET  /api/score                            # Headway scores by route
GET  /api/stops                            # Stop search (by lat/lon or name)
GET  /api/stops/{stopId}/nearby            # Nearby vehicles within radius
POST /api/vehicle-feedback                 # Submit crowd feedback
GET  /api/vehicle-feedback/{vehicleId}     # Get feedback summary
```

### 🎯 Ready to Implement (Have Data)

These endpoints can be built immediately with existing data structures:

```
GET  /api/system/overview                  # System health dashboard
GET  /api/routes                           # List all routes with grades
GET  /api/route/{routeId}/details          # Comprehensive route metrics
GET  /api/stop/{stopId}/arrivals           # Live predictions at stop
GET  /api/routes/compare                   # Side-by-side route comparison
```

### 📊 Requires Historical Data Collection

These endpoints need time-series data accumulation:

```
GET  /api/route/{routeId}/trends           # Performance over time (24hr/7d/30d)
GET  /api/route/{routeId}/bunching/history # Historical bunching incidents
GET  /api/reports/monthly                  # Monthly performance reports
GET  /api/reports/route/{routeId}          # Route performance report card
GET  /api/stop/{stopId}/history            # Historical performance at stop
```

### 🔮 Requires New Logic/Algorithms

These endpoints need new business logic:

```
GET  /api/stop/{stopId}/recommendation     # "Should I wait or walk?"
GET  /api/route/{routeId}/alerts/bunching  # Real-time bunching alerts
GET  /api/route/{routeId}/best-time        # Optimal riding times
POST /api/alerts/subscribe                 # Email/SMS alert subscriptions
```

---

## Phase 1: Core Dashboard Endpoints

### 1. System Overview

**Endpoint:** `GET /api/system/overview`

**Purpose:** Homepage dashboard with system-wide health metrics

**Implementation:**

```php
// src/Controller/SystemController.php

#[Route('/api/system/overview', name: 'system_overview', methods: ['GET'])]
public function overview(): JsonResponse
{
    $snapshot = $this->realtimeRepo->snapshot();
    $scores = $this->realtimeRepo->getScores();

    // Calculate system-wide grade
    $systemGrade = $this->calculateSystemGrade($scores);

    // Find top/bottom performers
    $topPerformers = array_slice($scores, 0, 3);
    $bottomPerformers = array_slice(array_reverse($scores), 0, 3);

    return $this->json([
        'timestamp' => time(),
        'system_grade' => $systemGrade['letter'],
        'on_time_percentage' => $systemGrade['percentage'],
        'trend' => $this->calculateTrend(), // vs yesterday
        'active_vehicles' => count($snapshot['vehicles'] ?? []),
        'total_routes' => count($this->routeRepo->findAll()),
        'alerts' => $this->getActiveAlerts(),
        'top_performers' => $this->formatRoutes($topPerformers),
        'needs_attention' => $this->formatRoutes($bottomPerformers),
    ]);
}

private function calculateSystemGrade(array $scores): array
{
    // Weight by vehicles per route, calculate overall on-time %
    // Map percentage to letter grade (A: 90-100%, B: 80-89%, etc.)
}
```

**Response Example:**

```json
{
  "timestamp": 1760229154,
  "system_grade": "C+",
  "on_time_percentage": 71,
  "trend": "declining",
  "active_vehicles": 32,
  "total_routes": 54,
  "alerts": [
    {
      "id": "alert-001",
      "severity": "warning",
      "route": "27",
      "message": "Delays due to traffic on 8th Street"
    }
  ],
  "top_performers": [
    {
      "route_id": "14526",
      "short_name": "14",
      "long_name": "North Industrial / City Centre",
      "grade": "A",
      "on_time_percentage": 94
    }
  ],
  "needs_attention": [
    {
      "route_id": "14551",
      "short_name": "43",
      "long_name": "Evergreen / City Centre",
      "grade": "D-",
      "on_time_percentage": 52,
      "issues": ["bunching", "delays"]
    }
  ]
}
```

**Data Sources:**
- Realtime snapshot (vehicles, trips)
- Headway scores from Redis (`mtw:score`)
- Route repository (static data)

---

### 2. Route List

**Endpoint:** `GET /api/routes`

**Parameters:**
- `filter` (optional): `all`, `a-b`, `c`, `d-f`
- `sort` (optional): `grade`, `number`, `name`, `on_time`
- `search` (optional): Route number or name substring

**Implementation:**

```php
#[Route('/api/routes', name: 'routes_list', methods: ['GET'])]
public function listRoutes(Request $request): JsonResponse
{
    $scores = $this->realtimeRepo->getScores();
    $filter = $request->query->get('filter', 'all');
    $sort = $request->query->get('sort', 'grade');
    $search = $request->query->get('search');

    $routes = $this->routeRepo->findAll();
    $enriched = [];

    foreach ($routes as $route) {
        $score = $this->findScoreForRoute($scores, $route->getGtfsId());
        $enriched[] = [
            'route_id' => $route->getGtfsId(),
            'short_name' => $route->getShortName(),
            'long_name' => $route->getLongName(),
            'grade' => $score['grade'] ?? 'N/A',
            'on_time_percentage' => $score['on_time_pct'] ?? null,
            'trend' => $this->calculateRouteTrend($route->getGtfsId()),
            'active_vehicles' => $this->countActiveVehicles($route->getGtfsId()),
        ];
    }

    // Apply filters
    $enriched = $this->applyFilter($enriched, $filter);
    $enriched = $this->applySort($enriched, $sort);

    if ($search) {
        $enriched = $this->applySearch($enriched, $search);
    }

    return $this->json([
        'routes' => $enriched,
        'total' => count($enriched),
    ]);
}
```

**Response Example:**

```json
{
  "routes": [
    {
      "route_id": "14526",
      "short_name": "14",
      "long_name": "North Industrial / City Centre",
      "grade": "A",
      "on_time_percentage": 94,
      "trend": "improving",
      "active_vehicles": 3
    },
    {
      "route_id": "14525",
      "short_name": "12",
      "long_name": "River Heights / City Centre",
      "grade": "A",
      "on_time_percentage": 89,
      "trend": "stable",
      "active_vehicles": 2
    }
  ],
  "total": 54
}
```

---

### 3. Route Detail

**Endpoint:** `GET /api/route/{routeId}/details`

**Purpose:** Comprehensive route metrics for route detail page

**Implementation:**

```php
#[Route('/api/route/{routeId}/details', name: 'route_details', methods: ['GET'])]
public function routeDetails(string $routeId): JsonResponse
{
    $route = $this->routeRepo->findOneBy(['gtfsId' => $routeId]);
    if (!$route) {
        return $this->json(['error' => 'Route not found'], 404);
    }

    $snapshot = $this->realtimeRepo->snapshot();
    $scores = $this->realtimeRepo->getScores();

    // Current status
    $vehicles = $this->getVehiclesForRoute($snapshot['vehicles'], $routeId);
    $todayStats = $this->calculateTodayStats($routeId);

    // Bunching analysis
    $bunching = $this->analyzeBunching($routeId, $vehicles);

    // Crowd feedback
    $feedback = $this->aggregateFeedback($routeId);

    // Performance by hour
    $hourlyPerformance = $this->calculateHourlyPerformance($routeId);

    return $this->json([
        'route' => [
            'route_id' => $route->getGtfsId(),
            'short_name' => $route->getShortName(),
            'long_name' => $route->getLongName(),
            'grade' => $this->findGrade($scores, $routeId),
            'on_time_percentage' => $this->findOnTimePercentage($scores, $routeId),
            'trend' => $this->calculateRouteTrend($routeId),
        ],
        'current_status' => [
            'active_vehicles' => count($vehicles),
            'trips_today' => $todayStats['trips'],
            'on_time_today' => $todayStats['on_time'],
            'late_today' => $todayStats['late'],
            'early_today' => $todayStats['early'],
        ],
        'reliability_warning' => $this->generateReliabilityWarning($routeId),
        'vehicles' => $this->formatVehicles($vehicles),
        'bunching' => $bunching,
        'crowd_feedback' => $feedback,
        'performance_by_hour' => $hourlyPerformance,
    ]);
}
```

**Response Example:**

```json
{
  "route": {
    "route_id": "14536",
    "short_name": "27",
    "long_name": "Silverspring / University",
    "grade": "D-",
    "on_time_percentage": 58,
    "trend": "declining"
  },
  "current_status": {
    "active_vehicles": 2,
    "trips_today": 18,
    "on_time_today": 7,
    "late_today": 9,
    "early_today": 2
  },
  "reliability_warning": {
    "show": true,
    "message": "This route is late 78% of the time on Friday evenings",
    "advice": "Plan for 6-8 min delays during peak hours"
  },
  "vehicles": [
    {
      "vehicle_id": "606",
      "lat": 52.1674,
      "lon": -106.5755,
      "stops_away": 4,
      "next_stop": "3734"
    }
  ],
  "bunching": {
    "incidents_today": 3,
    "recent_incidents": [
      {
        "time": "2025-10-11T14:45:00Z",
        "vehicles": ["606", "707"],
        "gap_after_sec": 720
      }
    ]
  },
  "crowd_feedback": {
    "ahead": {"count": 23, "percentage": 12},
    "on_time": {"count": 59, "percentage": 31},
    "late": {"count": 108, "percentage": 57},
    "total": 190,
    "agreement_percentage": 64
  },
  "performance_by_hour": [
    {
      "time_range": "07:00-09:00",
      "grade": "A-",
      "on_time_percentage": 92,
      "label": "Best window"
    }
  ]
}
```

---

### 4. Stop Arrivals

**Endpoint:** `GET /api/stop/{stopId}/arrivals`

**Parameters:**
- `limit` (optional): Max arrivals to return (default: 10)
- `route` (optional): Filter by route ID

**Purpose:** Live arrivals for stop detail page

**Implementation:**

```php
#[Route('/api/stop/{stopId}/arrivals', name: 'stop_arrivals', methods: ['GET'])]
public function stopArrivals(
    string $stopId,
    Request $request,
    ArrivalPredictorInterface $predictor
): JsonResponse {
    $stop = $this->stopRepo->findOneBy(['gtfsId' => $stopId]);
    if (!$stop) {
        return $this->json(['error' => 'Stop not found'], 404);
    }

    $limit = $request->query->getInt('limit', 10);
    $routeFilter = $request->query->get('route');

    $predictions = $predictor->predictArrivalsForStop($stopId, $limit, $routeFilter);

    // Enrich with historical performance
    $enriched = [];
    foreach ($predictions as $prediction) {
        $array = $prediction->toArray();
        $array['historical_reliability'] = $this->getStopRouteReliability(
            $stopId,
            $prediction->routeId
        );
        $enriched[] = $array;
    }

    return $this->json([
        'stop_id' => $stopId,
        'stop_name' => $stop->getName(),
        'stop_lat' => $stop->getLat(),
        'stop_lon' => $stop->getLong(),
        'predictions' => $enriched,
        'routes_at_stop' => $this->getRoutesServingStop($stopId),
    ]);
}
```

**Response Example:**

```json
{
  "stop_id": "3734",
  "stop_name": "Primrose / Lenore",
  "stop_lat": 52.1638,
  "stop_lon": -106.6227,
  "predictions": [
    {
      "vehicle_id": "606",
      "route_id": "14536",
      "trip_id": "trip-abc123",
      "stop_id": "3734",
      "headsign": "University",
      "arrival_in_sec": 180,
      "arrival_at": 1759897380,
      "confidence": "high",
      "delay_sec": 120,
      "status": {
        "color": "yellow",
        "label": "on_time",
        "severity": "✓ vibing",
        "deviation_sec": 30
      },
      "current_location": {
        "lat": 52.1234,
        "lon": -106.5678,
        "stops_away": 2
      },
      "historical_reliability": {
        "on_time_percentage": 54,
        "avg_delay_sec": 408
      }
    }
  ],
  "routes_at_stop": [
    {
      "route_id": "14536",
      "short_name": "27",
      "long_name": "Silverspring / University",
      "grade": "D-",
      "on_time_percentage": 58
    }
  ]
}
```

---

### 5. Route Comparison

**Endpoint:** `GET /api/routes/compare`

**Parameters:**
- `routes` (required): Comma-separated route IDs (e.g., `14536,14526,14525`)

**Implementation:**

```php
#[Route('/api/routes/compare', name: 'routes_compare', methods: ['GET'])]
public function compareRoutes(Request $request): JsonResponse
{
    $routeIds = explode(',', $request->query->get('routes', ''));

    if (count($routeIds) < 2 || count($routeIds) > 4) {
        return $this->json(['error' => 'Provide 2-4 route IDs'], 400);
    }

    $routes = [];
    foreach ($routeIds as $routeId) {
        $route = $this->routeRepo->findOneBy(['gtfsId' => $routeId]);
        if (!$route) {
            continue;
        }

        $routes[] = [
            'route_id' => $route->getGtfsId(),
            'short_name' => $route->getShortName(),
            'long_name' => $route->getLongName(),
            'grade' => $this->findGrade($routeId),
            'on_time_percentage' => $this->findOnTimePercentage($routeId),
            'avg_delay_sec' => $this->calculateAvgDelay($routeId),
            'bunching_per_day' => $this->calculateBunchingRate($routeId),
            'crowd_consensus' => $this->getCrowdConsensus($routeId),
            'best_time' => $this->getBestTime($routeId),
            'worst_time' => $this->getWorstTime($routeId),
        ];
    }

    // Determine recommendation
    $recommendation = $this->recommendRoute($routes);

    return $this->json([
        'routes' => $routes,
        'recommendation' => $recommendation,
    ]);
}
```

---

## Phase 2: Historical Data Collection

To power trend charts and historical reports, we need to persist performance data over time.

### Database Schema

**New Tables:**

```sql
-- Daily route performance snapshots
CREATE TABLE route_performance_daily (
    id SERIAL PRIMARY KEY,
    route_id INTEGER NOT NULL REFERENCES route(id),
    date DATE NOT NULL,
    grade VARCHAR(2),
    on_time_percentage DECIMAL(5,2),
    avg_delay_sec INTEGER,
    bunching_incidents INTEGER,
    total_trips INTEGER,
    on_time_trips INTEGER,
    late_trips INTEGER,
    early_trips INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(route_id, date)
);

-- Hourly performance buckets
CREATE TABLE route_performance_hourly (
    id SERIAL PRIMARY KEY,
    route_id INTEGER NOT NULL REFERENCES route(id),
    date DATE NOT NULL,
    hour INTEGER NOT NULL CHECK (hour >= 0 AND hour < 24),
    on_time_percentage DECIMAL(5,2),
    avg_delay_sec INTEGER,
    trip_count INTEGER,
    UNIQUE(route_id, date, hour)
);

-- Bunching incident log
CREATE TABLE bunching_incident (
    id SERIAL PRIMARY KEY,
    route_id INTEGER NOT NULL REFERENCES route(id),
    occurred_at TIMESTAMP NOT NULL,
    vehicle_ids TEXT[], -- Array of involved vehicle IDs
    gap_after_sec INTEGER,
    severity VARCHAR(10), -- 'minor', 'moderate', 'severe'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Stop-level performance
CREATE TABLE stop_performance_daily (
    id SERIAL PRIMARY KEY,
    stop_id INTEGER NOT NULL REFERENCES stop(id),
    route_id INTEGER NOT NULL REFERENCES route(id),
    date DATE NOT NULL,
    on_time_percentage DECIMAL(5,2),
    avg_delay_sec INTEGER,
    arrival_count INTEGER,
    UNIQUE(stop_id, route_id, date)
);

CREATE INDEX idx_route_perf_daily_route_date ON route_performance_daily(route_id, date);
CREATE INDEX idx_route_perf_hourly_route_date_hour ON route_performance_hourly(route_id, date, hour);
CREATE INDEX idx_bunching_route_time ON bunching_incident(route_id, occurred_at);
CREATE INDEX idx_stop_perf_daily_stop_route_date ON stop_performance_daily(stop_id, route_id, date);
```

### Data Collection Command

**Console Command:** `app:collect:daily-performance`

```php
// src/Command/CollectDailyPerformanceCommand.php

#[AsCommand(
    name: 'app:collect:daily-performance',
    description: 'Collect and persist daily performance metrics'
)]
class CollectDailyPerformanceCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Run daily at midnight via cron
        // Collect previous day's performance

        $yesterday = new \DateTime('yesterday');

        // For each route:
        // - Calculate on-time percentage
        // - Calculate average delay
        // - Count bunching incidents
        // - Insert into route_performance_daily

        // For each route+hour:
        // - Calculate hourly on-time percentage
        // - Insert into route_performance_hourly

        // For each stop+route:
        // - Calculate stop-level performance
        // - Insert into stop_performance_daily

        return Command::SUCCESS;
    }
}
```

**Cron Schedule:**

```bash
# In docker compose scheduler service
0 0 * * * php bin/console app:collect:daily-performance
```

### Historical Endpoints

Once data is collected, expose via API:

```php
GET /api/route/{routeId}/trends?period=30d

Response:
{
  "route_id": "14536",
  "period": "30d",
  "data": [
    {"date": "2025-10-01", "grade": "C", "on_time_percentage": 72},
    {"date": "2025-10-02", "grade": "C-", "on_time_percentage": 68},
    // ... 30 days
  ]
}
```

---

## Phase 3: Rider Utility Algorithms

### "Should I Wait or Walk?" Recommendation

**Endpoint:** `GET /api/stop/{stopId}/recommendation`

**Parameters:**
- `destination_lat` (required)
- `destination_lon` (required)
- `max_walk_meters` (optional, default: 1000)

**Algorithm:**

```php
public function generateRecommendation(
    string $stopId,
    float $destLat,
    float $destLon,
    int $maxWalkMeters = 1000
): array {
    $currentStop = $this->stopRepo->findOneBy(['gtfsId' => $stopId]);

    // 1. Get arrivals at current stop
    $currentArrivals = $this->predictor->predictArrivalsForStop($stopId, 5);

    // 2. Find nearby alternative stops
    $nearbyStops = $this->stopRepo->findNearby(
        $currentStop->getLat(),
        $currentStop->getLong(),
        $maxWalkMeters
    );

    // 3. For each alternative:
    //    - Calculate walk time
    //    - Get arrivals at that stop
    //    - Adjust arrival time by walk time
    //    - Weight by route reliability

    $options = [];

    // Option 1: Wait at current stop
    if (!empty($currentArrivals)) {
        $bestCurrent = $currentArrivals[0];
        $route = $this->routeRepo->findOneBy(['gtfsId' => $bestCurrent->routeId]);
        $reliability = $this->calculateReliability($route->getGtfsId());

        // Adjust arrival time by historical delay
        $adjustedArrival = $bestCurrent->arrivalAt + ($reliability['avg_delay_sec'] ?? 0);

        $options[] = [
            'action' => 'wait',
            'stop_id' => $stopId,
            'route' => $bestCurrent->routeId,
            'expected_arrival_time' => $adjustedArrival,
            'reliability_grade' => $reliability['grade'],
        ];
    }

    // Option 2+: Walk to alternatives
    foreach ($nearbyStops as $altStop) {
        $walkTimeSec = $this->calculateWalkTime($currentStop, $altStop);
        $arrivals = $this->predictor->predictArrivalsForStop($altStop->getGtfsId(), 3);

        foreach ($arrivals as $arrival) {
            $route = $this->routeRepo->findOneBy(['gtfsId' => $arrival->routeId]);
            $reliability = $this->calculateReliability($route->getGtfsId());

            // Adjusted time = now + walk time + (arrival - now) + typical delay
            $effectiveArrival = time() + $walkTimeSec +
                ($arrival->arrivalAt - time()) +
                ($reliability['avg_delay_sec'] ?? 0);

            $options[] = [
                'action' => 'walk',
                'stop_id' => $altStop->getGtfsId(),
                'stop_name' => $altStop->getName(),
                'walk_distance_meters' => $this->calculateDistance($currentStop, $altStop),
                'walk_time_sec' => $walkTimeSec,
                'route' => $arrival->routeId,
                'expected_arrival_time' => $effectiveArrival,
                'reliability_grade' => $reliability['grade'],
            ];
        }
    }

    // 4. Rank options by:
    //    - Expected arrival time (soonest)
    //    - Route reliability (prefer A/B grades)
    //    - Reasonable walk distance

    usort($options, function($a, $b) {
        // Weight: 70% time, 30% reliability
        $timeScore = $a['expected_arrival_time'] - $b['expected_arrival_time'];
        $reliabilityScore = $this->gradeToNumber($a['reliability_grade']) -
                           $this->gradeToNumber($b['reliability_grade']);

        return ($timeScore * 0.7) + ($reliabilityScore * 100 * 0.3);
    });

    $best = $options[0];

    return [
        'recommendation' => $best,
        'reason' => $this->generateReason($best, $options),
        'all_options' => $options,
        'confidence' => $this->calculateConfidence($best, $options),
    ];
}
```

**Response Example:**

```json
{
  "recommendation": {
    "action": "walk",
    "stop_id": "3801",
    "stop_name": "Evergreen / 8th",
    "walk_distance_meters": 450,
    "walk_time_sec": 360,
    "route": "14",
    "expected_arrival_time": 1760229600,
    "reliability_grade": "A"
  },
  "reason": "Route 14 is more reliable (A-grade vs D-grade) and will get you there 5 min sooner despite the walk",
  "all_options": [
    // ... full option list for advanced UIs
  ],
  "confidence": 0.82
}
```

---

### Bunching Alert Detection

**Endpoint:** `GET /api/route/{routeId}/bunching/active`

**Real-Time Detection:**

```php
public function detectActiveBunching(string $routeId): array
{
    $snapshot = $this->realtimeRepo->snapshot();
    $vehicles = array_filter(
        $snapshot['vehicles'] ?? [],
        fn($v) => ($v['route'] ?? null) === $routeId
    );

    if (count($vehicles) < 2) {
        return []; // Need at least 2 buses to bunch
    }

    // Sort by position along route
    usort($vehicles, fn($a, $b) =>
        $this->comparePositions($a, $b, $routeId)
    );

    $alerts = [];

    for ($i = 0; $i < count($vehicles) - 1; $i++) {
        $vehicle1 = $vehicles[$i];
        $vehicle2 = $vehicles[$i + 1];

        // Calculate time gap between consecutive buses
        $gap = $this->calculateTimeGap($vehicle1, $vehicle2);

        // Bunching if < 2 minutes apart
        if ($gap < 120) {
            // Check if there's a large gap after
            $nextGap = isset($vehicles[$i + 2])
                ? $this->calculateTimeGap($vehicle2, $vehicles[$i + 2])
                : null;

            $alerts[] = [
                'type' => 'bunching',
                'vehicles' => [$vehicle1['id'], $vehicle2['id']],
                'gap_sec' => $gap,
                'next_gap_sec' => $nextGap,
                'severity' => $this->calculateBunchingSeverity($gap, $nextGap),
                'message' => sprintf(
                    'Buses %s and %s arriving together (%d sec apart), then %d min gap',
                    $vehicle1['id'],
                    $vehicle2['id'],
                    $gap,
                    ($nextGap ?? 0) / 60
                ),
            ];
        }
    }

    return $alerts;
}
```

---

## Implementation Priority

### Sprint 1 (Week 1-2): Core Dashboard
- ✅ Delay calculation (already done!)
- [ ] System overview endpoint
- [ ] Route list endpoint
- [ ] Basic route detail endpoint

### Sprint 2 (Week 3-4): Stop Pages
- [ ] Stop arrivals endpoint (create StopController)
- [ ] Routes serving stop
- [ ] Nearby stops endpoint

### Sprint 3 (Week 5-6): Route Details
- [ ] Bunching detection
- [ ] Crowd feedback aggregation
- [ ] Performance by hour
- [ ] Route comparison endpoint

### Sprint 4 (Week 7-8): Historical Data
- [ ] Database schema for historical data
- [ ] Daily collection command
- [ ] Trend chart endpoints
- [ ] Historical reports

### Sprint 5 (Week 9-10): Rider Utilities
- [ ] "Should I wait?" recommendation
- [ ] Bunching alerts
- [ ] Best time to ride
- [ ] Alert subscriptions

---

## Testing Strategy

### Unit Tests

```php
// tests/Controller/SystemControllerTest.php
class SystemControllerTest extends WebTestCase
{
    public function testSystemOverview(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/system/overview');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('system_grade', $data);
        $this->assertArrayHasKey('on_time_percentage', $data);
        $this->assertArrayHasKey('top_performers', $data);
        $this->assertIsArray($data['top_performers']);
    }
}
```

### Integration Tests

Test with real-world scenarios:
- Route with 0 vehicles (offline)
- Route with bunching
- Stop with no arrivals
- Invalid route/stop IDs

### Load Testing

Use Apache Bench or k6:

```bash
# Test system overview endpoint
ab -n 1000 -c 10 https://localhost/api/system/overview

# Test route details (cache hit)
ab -n 5000 -c 50 https://localhost/api/route/14536/details
```

Target: <100ms p95 latency for cached endpoints

---

## Documentation

Each endpoint needs:
- OpenAPI/Swagger spec
- Example requests (curl, JS, Python)
- Response schema
- Error codes
- Rate limits (if applicable)

Use Symfony's API Platform or NelmioApiDocBundle for auto-generated docs.

---

## Questions for Discussion

1. **Caching Strategy:** What TTLs for different endpoints?
2. **Rate Limiting:** Public API needs throttling?
3. **Authentication:** Public read-only, or API keys for high usage?
4. **CORS:** Allow all origins or restrict?
5. **Versioning:** `/api/v1/` from start, or add later?

---

*Ready to implement? Start with Sprint 1 endpoints and build iteratively.*
