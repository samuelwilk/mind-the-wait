# Public Dashboard Specification

Real-time transit reliability dashboard for riders, advocates, and city officials.

## Overview

A public-facing web application that exposes transit system health and route-level performance metrics. Unlike consumer navigation apps (Google Maps, Transit), this dashboard prioritizes **reliability transparency** over individual trip planning.

**Target URL:** `https://mindthewait.city` (or subdomain: `https://transit.saskatoon.ca/reliability`)

## Core Pages

### 1. Home: System Overview

**URL:** `/`

**Purpose:** At-a-glance system health for entire transit network

#### Layout

```
┌─────────────────────────────────────────────────────────┐
│  🚌 Saskatoon Transit Reliability Dashboard            │
│  Last updated: 2 minutes ago                            │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │ System Grade │  │   Vehicles   │  │    Alerts    │ │
│  │      C+      │  │    32/54     │  │      2       │ │
│  │  71% on-time │  │   operating  │  │    active    │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
│                                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Top Performers (A-grade)           🏆          │   │
│  │  ✅ Route 14: North Industrial (94%)            │   │
│  │  ✅ Route 12: River Heights (89%)               │   │
│  │  ✅ Route 50: Lakeview (87%)                    │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Needs Attention (D-F grades)       ⚠️          │   │
│  │  ⚠️ Route 43: Evergreen (52%, bunching)         │   │
│  │  ⚠️ Route 27: Silverspring (58%, delays)        │   │
│  │  ⚠️ Route 6: Wilson Cres (61%, irregular)       │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │           Real-Time System Map                   │   │
│  │  [Interactive map: routes color-coded by grade] │   │
│  │  🟢 A-grade  🟡 B-grade  🟠 C-grade  🔴 D-F     │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
│  [ View All Routes → ]  [ Historical Reports → ]        │
└─────────────────────────────────────────────────────────┘
```

#### Widgets

**System Grade Card:**
- Letter grade (A-F) based on system-wide on-time percentage
- Percentage on-time today
- Trend indicator (↑ improving, ↓ declining, → stable)
- Color-coded background

**Active Vehicles Card:**
- Count of currently active buses
- Total routes in service
- Link to real-time map

**Service Alerts Card:**
- Count of active alerts
- Most critical alert preview
- Link to full alert list

**Top/Bottom Performers:**
- Route number + name
- Current grade
- On-time percentage
- Icon indicating status (✅, ⚠️, ❌)

**Real-Time Map:**
- Leaflet/Mapbox base map
- Routes drawn as lines, color-coded by grade
- Active buses as icons
- Click route → route detail page
- Click bus → arrival predictions
- Toggle layers (all routes, top performers, problem routes)

#### Data Sources

```php
// API endpoint
GET /api/system/overview

Response:
{
  "timestamp": 1760229154,
  "system_grade": "C+",
  "on_time_percentage": 71,
  "trend": "declining",  // vs yesterday
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

---

### 2. Route List

**URL:** `/routes`

**Purpose:** Browse all routes with performance metrics

#### Layout

```
┌─────────────────────────────────────────────────────────┐
│  All Routes (54)                                        │
│  ┌────────────────────────────────────────────────┐    │
│  │ 🔍 Search routes...                            │    │
│  │ Filter: [All] [A-B] [C] [D-F]  Sort: [Grade ▼]│    │
│  └────────────────────────────────────────────────┘    │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Grade Route    Name                   On-Time  Trend   │
│  ────────────────────────────────────────────────────── │
│  A     14       North Industrial       94%      ↑       │
│  A     12       River Heights          89%      →       │
│  A-    50       Lakeview               87%      ↑       │
│  B+    2        Meadowgreen            84%      →       │
│  B     1        City Centre/Exhibition 81%      →       │
│  B     10       Centre Mall/University 79%      ↓       │
│  C+    15       Civic Op Centre        76%      →       │
│  C     19       City Centre/Mall       72%      ↓       │
│  C-    30       Lawson Heights         68%      ↓       │
│  D+    6        Wilson Cres            61%      ↓       │
│  D     27       Silverspring           58%      ↓↓      │
│  D-    43       Evergreen              52%      ↓↓      │
│  ...                                                     │
│                                                          │
│  Showing 12 of 54 routes  [Load More]                   │
└─────────────────────────────────────────────────────────┘
```

#### Features

**Search:**
- By route number (e.g., "27")
- By route name (e.g., "Evergreen")
- Real-time filtering

**Filters:**
- Grade range (A-B, C, D-F, All)
- Service type (regular, express, community)
- Area of city

**Sort Options:**
- Grade (best/worst first)
- Route number
- On-time percentage
- Name (alphabetical)

**Trend Indicators:**
- ↑ Improving (≥5% better than yesterday)
- → Stable (±5%)
- ↓ Declining (≥5% worse)
- ↓↓ Significantly declining (≥10% worse)

#### Data Sources

```php
GET /api/routes

Response:
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
    // ... more routes
  ],
  "total": 54
}
```

---

### 3. Route Detail Page

**URL:** `/route/:routeId` (e.g., `/route/14536`)

**Purpose:** Deep dive into single route performance

#### Layout

```
┌─────────────────────────────────────────────────────────┐
│  ← Back to Routes                                       │
│  Route 27: Silverspring / University                   │
├─────────────────────────────────────────────────────────┤
│  Current Status                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │  Grade: D-   │  │  On-Time: 58%│  │  Vehicles: 2 │ │
│  │  Trend: ↓    │  │  Today: 7/18 │  │  Active now  │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
│                                                          │
│  ⚠️ Reliability Warning                                 │
│  This route is late 78% of the time on Friday evenings │
│  Plan for 6-8 min delays during peak hours             │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Real-Time Map                                           │
│  ┌─────────────────────────────────────────────────┐   │
│  │  [Map showing route line + active buses]        │   │
│  │  Bus 606 → 4 stops away from Primrose/Lenore   │   │
│  │  Bus 707 → 12 stops away from Evergreen/8th    │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Performance Trends                                      │
│  ┌─────────────────────────────────────────────────┐   │
│  │  [Line chart: On-time % over last 30 days]     │   │
│  │   100% ┤                                        │   │
│  │    80% ┤    ●─●─●──●                            │   │
│  │    60% ┤            ●─●──●──●──●──●──●         │   │
│  │    40% ┤                                        │   │
│  │        └────────────────────────────────────    │   │
│  │         Oct 1        Oct 15       Oct 30       │   │
│  └─────────────────────────────────────────────────┘   │
│  [ 24 Hours ] [ 7 Days ] [ 30 Days ] [ Custom ]        │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Schedule Adherence (Last 24 Hours)                     │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Early: ▓▓░░░░░░░░ 11% (2 arrivals)            │   │
│  │  On-Time: ▓▓▓▓▓▓▓░░░░ 38% (7 arrivals)         │   │
│  │  Late: ▓▓▓▓▓▓▓▓▓▓▓▓ 50% (9 arrivals)           │   │
│  │                                                  │   │
│  │  Avg Delay: 6.2 minutes                         │   │
│  │  Max Delay: 14 minutes (6:45 PM trip)           │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Bunching Analysis                                       │
│  🔴 3 incidents today  |  📊 2.4 incidents/day (30-day) │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Recent Incidents:                               │   │
│  │  • 2:45 PM: 2 buses arrived 1 min apart         │   │
│  │    (12 min gap after)                            │   │
│  │  • 4:30 PM: 2 buses arrived 45 sec apart        │   │
│  │    (18 min gap after)                            │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Crowd Feedback (Last 30 Days)                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Ahead: ▓▓░░░░░░░░░░ 12% (23 votes)             │   │
│  │  On-Time: ▓▓▓▓░░░░░░░ 31% (59 votes)            │   │
│  │  Late: ▓▓▓▓▓▓▓▓░░░░░ 57% (108 votes)            │   │
│  │                                                  │   │
│  │  Total Votes: 190                                │   │
│  │  Agreement with Agency: 64%                      │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Performance by Time of Day (Weekdays)                  │
│  ┌─────────────────────────────────────────────────┐   │
│  │  7-9 AM    🟢 A-  92% on-time  Best window       │   │
│  │  9-12 PM   🟡 B   81% on-time                    │   │
│  │  12-3 PM   🟡 B-  76% on-time                    │   │
│  │  3-6 PM    🔴 D+  58% on-time  Avoid if possible │   │
│  │  6-9 PM    🟡 C   68% on-time                    │   │
│  │  9 PM-12 AM 🟢 B+ 84% on-time                    │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Stop-by-Stop Performance                                │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Stops with Most Delays (30-day avg):           │   │
│  │  1. Evergreen / 8th St    -7.2 min behind       │   │
│  │  2. College Dr / Preston  -5.8 min behind       │   │
│  │  3. 8th St / Broadway     -4.3 min behind       │   │
│  │                                                  │   │
│  │  [View Full Route Timeline →]                   │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Actions                                                 │
│  [ 📊 Export Data (CSV) ]  [ 🔗 Share This Route ]     │
│  [ 🔔 Get Alerts ]  [ 🆚 Compare Routes ]               │
└─────────────────────────────────────────────────────────┘
```

#### Sections

**Current Status Cards:**
- Grade (large, color-coded)
- On-time percentage (today)
- Active vehicles count
- Trend vs yesterday

**Reliability Warning:**
- Only shown if route has <70% on-time for current time/day pattern
- Actionable advice for riders

**Real-Time Map:**
- Route line drawn on map
- Active buses with vehicle IDs
- Click bus → upcoming stops + ETAs
- Click stop → arrivals at that stop

**Performance Trends Chart:**
- Line chart: on-time % over time
- Selectable time ranges: 24hr, 7d, 30d, custom
- Annotations for major incidents/changes
- Export data button

**Schedule Adherence Histogram:**
- Horizontal bars: Early / On-Time / Late
- Percentage + count for each category
- Average delay/early
- Maximum delay/early (with time of occurrence)

**Bunching Analysis:**
- Incident count (today + 30-day average)
- List of recent incidents with details
- Definition: 2+ buses within 2 min, followed by ≥1.5× scheduled headway gap

**Crowd Feedback:**
- Vote breakdown (ahead/on_time/late)
- Total votes
- Agreement percentage with agency predictions
- Trend over time (are votes improving/worsening?)

**Performance by Time of Day:**
- Grade + on-time % for each time bucket
- Color-coded by performance
- Recommendations (best/worst times)
- Weekday vs Weekend toggle

**Stop-by-Stop Performance:**
- Identify bottleneck stops (most delays)
- Average delay at each stop
- Link to full route timeline visualization

**Actions:**
- Export data (CSV format with all metrics)
- Share route scorecard (link copy, social media)
- Get alerts (email/SMS when grade drops)
- Compare routes (side-by-side with alternatives)

#### Data Sources

```php
GET /api/route/{routeId}/details

Response:
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
  "performance_trend": [
    {"date": "2025-10-01", "on_time_percentage": 78},
    {"date": "2025-10-02", "on_time_percentage": 72},
    // ... 30 days
  ],
  "schedule_adherence": {
    "early": {"count": 2, "percentage": 11},
    "on_time": {"count": 7, "percentage": 38},
    "late": {"count": 9, "percentage": 50},
    "avg_delay_sec": 372,
    "max_delay_sec": 840,
    "max_delay_time": "2025-10-11T18:45:00Z"
  },
  "bunching": {
    "incidents_today": 3,
    "incidents_30d_avg": 2.4,
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
    },
    // ... more time buckets
  ],
  "bottleneck_stops": [
    {
      "stop_id": "3921",
      "stop_name": "Evergreen / 8th St",
      "avg_delay_sec": 432
    },
    // ... more stops
  ]
}
```

---

### 4. Stop Detail Page

**URL:** `/stop/:stopId` (e.g., `/stop/3734`)

**Purpose:** Live arrivals + historical performance at specific stop

#### Layout

```
┌─────────────────────────────────────────────────────────┐
│  ← Back to Routes                                       │
│  Stop 3734: Primrose / Lenore                          │
│  52.1638° N, -106.6227° W                              │
├─────────────────────────────────────────────────────────┤
│  Live Arrivals                       Last updated: 30s  │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Route  Destination      Arrives  Grade Status  │   │
│  │  ──────────────────────────────────────────────  │   │
│  │  27     University       3 min    D-    ⚠️ Late │   │
│  │         Expected: 3:42 PM                        │   │
│  │         Scheduled: 3:39 PM (3 min late)          │   │
│  │         4 stops away                             │   │
│  │                                                   │   │
│  │  14     North Industrial 7 min    A     ✅ On-Time│  │
│  │         Expected: 3:46 PM                        │   │
│  │         Scheduled: 3:47 PM (1 min early)         │   │
│  │         6 stops away                             │   │
│  │                                                   │   │
│  │  27     University       18 min   D-    ⚠️ Late │   │
│  │         Expected: 3:57 PM                        │   │
│  │         Scheduled: 3:51 PM (6 min late)          │   │
│  │         12 stops away                            │   │
│  └─────────────────────────────────────────────────┘   │
│  🔄 Auto-refresh every 30 seconds                       │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Routes Serving This Stop                                │
│  ┌─────────────────────────────────────────────────┐   │
│  │  27  Silverspring / University  D-  58% on-time │   │
│  │  14  North Industrial           A   94% on-time │   │
│  │  12  River Heights              A   89% on-time │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Should I Wait?                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │  🚶 Walk to Stop 3801 (Evergreen / 8th)         │   │
│  │     Distance: 450m (6 min walk)                  │   │
│  │     Route 14 in 12 min (A-grade, reliable)      │   │
│  │                                                   │   │
│  │  ⏱️  Wait here                                    │   │
│  │     Route 27 in 3 min (D-grade, often late)     │   │
│  │                                                   │   │
│  │  💡 Recommendation: Walk to Stop 3801            │   │
│  │     You'll likely arrive 5 min sooner           │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Nearby Stops (within 500m)                             │
│  ┌─────────────────────────────────────────────────┐   │
│  │  [Map showing this stop + nearby alternatives]  │   │
│  │                                                  │   │
│  │  • Stop 3801: Evergreen / 8th (450m, 6 min)    │   │
│  │    Routes: 14 (A), 12 (A)                       │   │
│  │  • Stop 3745: College Dr (380m, 5 min)         │   │
│  │    Routes: 10 (B), 19 (C)                       │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
├─────────────────────────────────────────────────────────┤
│  Historical Performance at This Stop (Last 30 Days)     │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Route 27:                                       │   │
│  │    On-Time: 54%  |  Avg Delay: 6.8 min          │   │
│  │    Best Time: 7-9 AM (89% on-time)              │   │
│  │    Worst Time: 3-6 PM (41% on-time)             │   │
│  │                                                  │   │
│  │  Route 14:                                       │   │
│  │    On-Time: 92%  |  Avg Delay: 1.2 min          │   │
│  │    Consistently reliable across all times       │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

#### Features

**Live Arrivals:**
- Real-time countdown timers
- Scheduled vs expected arrival
- Delay calculation (in minutes)
- Route grade indicator
- Status badge (on-time/late/early)
- Stops away from this stop
- Auto-refresh every 30 seconds

**Should I Wait?:**
- Decision recommendation algorithm
- Considers:
  - Walking distance to alternatives
  - Route reliability grades
  - Expected arrival times accounting for typical delays
- Shows reasoning for recommendation

**Nearby Stops Map:**
- Interactive map
- This stop highlighted
- Alternatives within 500m radius
- Walking distance + time
- Routes serving each alternative
- Grades for each route

**Historical Performance:**
- Per-route stats at this specific stop
- On-time percentage
- Average delay
- Best/worst time of day for each route
- Link to full route details

#### Data Sources

```php
GET /api/stop/{stopId}/arrivals?live=true

Response:
{
  "stop": {
    "stop_id": "3734",
    "name": "Primrose / Lenore",
    "lat": 52.1638,
    "lon": -106.6227
  },
  "arrivals": [
    {
      "route_id": "14536",
      "route_name": "27",
      "headsign": "University",
      "arrival_in_sec": 180,
      "scheduled_arrival": 1760229240,
      "expected_arrival": 1760229420,
      "delay_sec": 180,
      "grade": "D-",
      "status": "late",
      "stops_away": 4
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
  ],
  "recommendation": {
    "action": "walk",
    "target_stop": "3801",
    "reason": "Route 14 more reliable, arrive 5 min sooner",
    "confidence": 0.82
  },
  "nearby_stops": [
    {
      "stop_id": "3801",
      "name": "Evergreen / 8th",
      "distance_meters": 450,
      "walk_time_sec": 360,
      "routes": [
        {"route": "14", "grade": "A"},
        {"route": "12", "grade": "A"}
      ]
    }
  ],
  "historical_performance": {
    "14536": {
      "on_time_percentage": 54,
      "avg_delay_sec": 408,
      "best_time": {"range": "07:00-09:00", "on_time": 89},
      "worst_time": {"range": "15:00-18:00", "on_time": 41}
    }
  }
}
```

---

### 5. Route Comparison Tool

**URL:** `/compare?routes=14536,14526,14525`

**Purpose:** Side-by-side comparison of multiple routes

#### Layout

```
┌─────────────────────────────────────────────────────────┐
│  Route Comparison                                        │
│  ┌────────────────────────────────────────────────┐    │
│  │ Select routes: [Route 27 ▼] [Route 14 ▼] [+]  │    │
│  └────────────────────────────────────────────────┘    │
├─────────────────────────────────────────────────────────┤
│              Route 27         Route 14        Route 12  │
│              Silverspring     North Ind.      River Hts │
│  ─────────────────────────────────────────────────────  │
│  Grade       D- ⚠️            A ✅            A ✅       │
│  On-Time     58%              94%             89%        │
│  Avg Delay   6.2 min          1.1 min        1.8 min    │
│  Bunching    2.4/day          0.3/day        0.5/day    │
│  Crowd       57% "late"       92% "on-time"  86% "on-time"│
│              190 votes        412 votes      278 votes   │
│  ─────────────────────────────────────────────────────  │
│  Best Time   7-9 AM (92%)     All day (90%+) 7-9 AM (94%)│
│  Worst Time  3-6 PM (41%)     12-3 PM (89%)  3-6 PM (82%)│
│  ─────────────────────────────────────────────────────  │
│  🏆 Recommendation: Route 14 (most reliable)            │
│                                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │  On-Time Performance (Last 30 Days)             │   │
│  │  [Multi-line chart comparing all 3 routes]      │   │
│  │   100% ┤  ──●──●──●──●──●──●─  Route 14         │   │
│  │    80% ┤    ─●──●──●──●──●──●  Route 12         │   │
│  │    60% ┤        ●──●─●─●──●──  Route 27         │   │
│  │        └─────────────────────────────────────   │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
│  [ 📊 Export Comparison ] [ 🔗 Share Link ]            │
└─────────────────────────────────────────────────────────┘
```

#### Features

**Route Selection:**
- Dropdown to add/remove routes
- Max 4 routes for readability
- URL parameters for sharing

**Comparison Metrics:**
- Grade (letter + color)
- On-time percentage
- Average delay
- Bunching frequency
- Crowd feedback consensus

**Time-Based Analysis:**
- Best/worst time of day for each route
- Comparative chart showing trends

**Recommendation:**
- Algorithm picks best route based on:
  - Overall grade
  - Current time of day performance
  - Bunching history
  - Crowd consensus

#### Data Sources

```php
GET /api/routes/compare?routes=14536,14526,14525

Response:
{
  "routes": [
    {
      "route_id": "14536",
      "short_name": "27",
      "grade": "D-",
      "on_time_percentage": 58,
      "avg_delay_sec": 372,
      "bunching_per_day": 2.4,
      "crowd_consensus": {"late": 57, "votes": 190},
      "best_time": {"range": "07:00-09:00", "percentage": 92},
      "worst_time": {"range": "15:00-18:00", "percentage": 41}
    },
    // ... other routes
  ],
  "recommendation": {
    "route_id": "14526",
    "reason": "Highest reliability across all time periods"
  },
  "performance_trend": [
    {
      "date": "2025-10-01",
      "14536": 72,
      "14526": 94,
      "14525": 89
    },
    // ... 30 days
  ]
}
```

---

### 6. Historical Reports

**URL:** `/reports`

**Purpose:** Pre-built and custom reports for analysis/advocacy

#### Report Types

**1. Monthly System Performance**
- System-wide grade
- Route-by-route breakdown
- Top improvers/decliners
- Bunching incidents summary
- Crowd feedback trends

**2. Route Performance Report Card**
- Select single route
- Comprehensive 30/60/90 day analysis
- Time-of-day breakdowns
- Stop-by-stop delays
- Comparison to system average

**3. On-Time Performance by Time of Day**
- System-wide hourly performance
- Identify peak problem hours
- Compare weekday vs weekend

**4. Bunching Incidents Log**
- All bunching incidents
- Route breakdown
- Severity scores
- Time patterns

**5. Crowd Feedback vs Agency Accuracy**
- Correlation analysis
- Routes with highest disagreement
- Trend over time

#### Export Formats

**CSV:**
```csv
Date,Route,Grade,OnTime%,AvgDelaySec,BunchingIncidents
2025-10-01,27,D,58,372,3
2025-10-01,14,A,94,66,0
```

**JSON:**
```json
{
  "report": "monthly_system_performance",
  "period": "2025-10",
  "system_grade": "C+",
  "routes": [...]
}
```

**PDF:**
- Formatted report with charts
- Suitable for presentations
- City branding option

#### Data Sources

```php
GET /api/reports/monthly?year=2025&month=10

GET /api/reports/route/{routeId}?start_date=2025-09-01&end_date=2025-10-31

GET /api/reports/bunching?start_date=2025-10-01&end_date=2025-10-31
```

---

## Technical Implementation

### Frontend Stack

**Framework:** React 18+ or Vue 3+
- Component-based architecture
- Server-side rendering for SEO
- Progressive enhancement

**State Management:**
- Redux Toolkit (React) or Pinia (Vue)
- React Query / TanStack Query for API data
- Local state for UI interactions

**Styling:**
- Tailwind CSS for utility-first styling
- CSS modules for component isolation
- Responsive breakpoints: mobile (320px), tablet (768px), desktop (1024px+)

**Charts:**
- Chart.js or Recharts for simple charts
- D3.js for advanced visualizations
- Real-time chart updates via WebSocket

**Maps:**
- Leaflet with OpenStreetMap tiles
- GeoJSON for route lines
- Custom markers for buses
- Clustering for nearby stops

**Icons:**
- Lucide React or Heroicons
- Custom transit icons (bus, alert, grade badges)

### Backend API Endpoints

All endpoints already exist or are extensions of existing:

```
GET  /api/system/overview
GET  /api/routes
GET  /api/route/{routeId}/details
GET  /api/stop/{stopId}/arrivals
GET  /api/stop/{stopId}/nearby
GET  /api/routes/compare?routes=X,Y,Z
GET  /api/reports/monthly
GET  /api/reports/route/{routeId}
GET  /api/reports/bunching
```

New endpoints needed:

```
GET  /api/route/{routeId}/vehicles/live         (real-time bus positions)
GET  /api/stop/{stopId}/recommendation          (should I wait logic)
GET  /api/route/{routeId}/bunching/history      (bunching incidents)
POST /api/alerts/subscribe                      (email/SMS alerts)
```

### Data Refresh Strategy

**Real-Time (WebSocket/SSE):**
- Live vehicle positions (every 5-10 sec)
- Active arrivals at stop pages (every 30 sec)

**Short Cache (30s-5min):**
- System overview metrics
- Route list
- Current status cards

**Medium Cache (5-30min):**
- Performance trend charts
- Historical statistics
- Route comparisons

**Long Cache (1-24hr):**
- Historical reports
- Monthly aggregates
- Static content

### Performance Optimization

**Frontend:**
- Code splitting by route
- Lazy load charts/maps
- Virtual scrolling for long lists
- Image optimization
- Service worker for offline

**Backend:**
- Redis caching layers
- PostgreSQL query optimization (indexes on route_id, stop_id, timestamp)
- Materialized views for aggregates
- CDN for static assets

**Target Metrics:**
- First Contentful Paint: <1.5s
- Time to Interactive: <3s
- Lighthouse Score: >90

### Deployment

**Option 1: Vercel/Netlify (Recommended for MVP)**
- Deploy frontend as static site
- API calls to existing Symfony backend
- Automatic HTTPS, CDN, previews
- Free tier sufficient for small cities

**Option 2: Self-Hosted**
- Build frontend: `npm run build`
- Serve via Nginx (static files + reverse proxy to API)
- Docker container for portability
- SSL via Let's Encrypt

**Option 3: Integrated with Symfony**
- Build frontend, copy to `public/dashboard/`
- Serve from same domain as API
- Single deployment target

### Accessibility

**WCAG 2.1 AA Compliance:**
- Semantic HTML (landmarks, headings)
- ARIA labels for dynamic content
- Keyboard navigation (tab order, focus indicators)
- Screen reader announcements for updates
- Color contrast ratios ≥4.5:1
- Focus visible on all interactive elements
- Skip navigation links

**Responsive Design:**
- Mobile-first approach
- Touch targets ≥44px
- Readable text sizes (16px+ base)
- Horizontal scrolling avoided
- Landscape/portrait handling

---

## Launch Strategy

### Phase 1: Soft Launch (Internal Testing)
- Deploy to staging URL
- Test with transit advocates/city staff
- Gather feedback on UX/data accuracy
- Fix critical bugs

### Phase 2: Public Beta
- Announce on local transit forums/Reddit
- Press release to local media
- Limited feature set (Home, Route List, Route Detail)
- Monitor server load, iterate

### Phase 3: Full Launch
- All pages live
- Public API documentation
- Embed widgets for advocacy sites
- City partnership announcement

### Phase 4: Growth
- Add more cities (multi-tenant)
- Mobile app (React Native/Flutter)
- Advanced features (ML predictions, alerts)
- Community contributions

---

## Success Metrics

### Engagement
- Daily active users
- Pages per session
- Avg session duration
- Return visitor rate

### Impact
- Media citations/references
- City council presentations using data
- Transit agency acknowledgments
- Routes with grade improvements after public attention

### Technical
- API uptime (target: 99.5%)
- Page load times (target: <3s)
- Error rates (target: <0.1%)

---

## Open Questions

1. **Branding:** Should this be city-specific (e.g., "Saskatoon Transit Monitor") or generic ("Mind the Wait")?

2. **Monetization:** Keep 100% free? Optional premium features? City sponsorship?

3. **Moderation:** Allow user comments on routes? If so, how to moderate?

4. **Multi-City:** Design for single city first, or build multi-tenant from start?

5. **Mobile App:** Web-only initially, or native mobile apps priority?

---

*Ready to build? See [Development Guide](../DEVELOPMENT.md) to get started.*
