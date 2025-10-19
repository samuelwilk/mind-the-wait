# API Endpoints

Complete reference for all REST API endpoints in mind-the-wait.

## Base URL

```
https://localhost/api
```

## Authentication

Currently no authentication required. All endpoints are public.

---

## Mobile API (v1)

Mobile-optimized JSON endpoints for iOS app consumption. All v1 endpoints use `/api/v1/` prefix and include appropriate HTTP caching headers.

### GET /api/v1/routes

List all routes with current performance metrics and active vehicle counts.

**Caching:** 5 minutes (`Cache-Control: public, max-age=300`)

#### Example Request

```bash
curl https://localhost/api/v1/routes
```

#### Response

```json
{
  "routes": [
    {
      "id": "14530",
      "short_name": "2",
      "long_name": "Confed to Exhibition",
      "color": "#0000FF",
      "grade": "B",
      "on_time_pct": 78.5,
      "active_vehicles": 3
    },
    {
      "id": "14514",
      "short_name": "4",
      "long_name": "City Hosp to Lakewood",
      "color": "#FF0000",
      "grade": "A",
      "on_time_pct": 91.2,
      "active_vehicles": 2
    }
  ],
  "timestamp": 1759894221
}
```

#### Route Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | GTFS route identifier |
| `short_name` | string | Route number (e.g., "2", "4") |
| `long_name` | string | Route description |
| `color` | string | Route color (hex) |
| `grade` | string | 30-day average performance grade (A-F) |
| `on_time_pct` | float | 30-day on-time percentage |
| `active_vehicles` | integer | Currently active vehicles on route |

---

### GET /api/v1/routes/{gtfsId}

Get detailed performance metrics for a specific route.

**Caching:** 10 minutes (`Cache-Control: public, max-age=600`)

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `gtfsId` | string | Yes | Route GTFS identifier (path parameter) |

#### Example Request

```bash
curl https://localhost/api/v1/routes/14530
```

#### Response

```json
{
  "route": {
    "id": "14530",
    "short_name": "2",
    "long_name": "Confed to Exhibition",
    "color": "#0000FF"
  },
  "stats": {
    "on_time_pct": 78.5,
    "avg_delay_min": 2.3,
    "total_trips": 847,
    "days_tracked": 30,
    "grade": "B"
  },
  "timestamp": 1759894221
}
```

#### Error Responses

**404 Not Found** - Route does not exist

---

### GET /api/v1/stops

List all transit stops, optionally filtered by route.

**Caching:** 1 hour (`Cache-Control: public, max-age=3600`)

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `route_id` | string | No | Filter stops served by this route (GTFS ID) |

#### Example Requests

```bash
# All stops
curl https://localhost/api/v1/stops

# Stops served by route 2
curl 'https://localhost/api/v1/stops?route_id=14530'
```

#### Response

```json
{
  "stops": [
    {
      "id": "3900",
      "name": "Evergreen / Wyant",
      "lat": 52.168249,
      "lon": -106.575778
    },
    {
      "id": "3921",
      "name": "Zary / Evergreen",
      "lat": 52.166906,
      "lon": -106.576557
    }
  ],
  "timestamp": 1759894221
}
```

#### Stop Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | GTFS stop identifier |
| `name` | string | Stop name |
| `lat` | float | Latitude |
| `lon` | float | Longitude |

---

### GET /api/v1/stops/{gtfsId}/predictions

Get realtime arrival predictions for a specific stop with countdown timers and confidence levels.

**Caching:** None (`Cache-Control: no-cache`) - Always fresh data

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `gtfsId` | string | Yes | Stop GTFS identifier (path parameter) |
| `limit` | integer | No | Max predictions to return (default: 5) |
| `route_id` | string | No | Filter predictions to specific route |

#### Example Requests

```bash
# Next 5 arrivals at stop
curl https://localhost/api/v1/stops/3734/predictions

# Next 10 arrivals, filtered to route 2
curl 'https://localhost/api/v1/stops/3734/predictions?limit=10&route_id=14530'
```

#### Response

```json
{
  "stop": {
    "id": "3734",
    "name": "Primrose / Lenore",
    "lat": 52.1234,
    "lon": -106.5678
  },
  "predictions": [
    {
      "vehicle_id": "606",
      "route_id": "14530",
      "route_short_name": "2",
      "headsign": "Exhibition",
      "arrival_in_sec": 180,
      "arrival_at": 1759897380,
      "confidence": "high",
      "delay_sec": 45
    },
    {
      "vehicle_id": "512",
      "route_id": "14514",
      "route_short_name": "4",
      "headsign": "Lakewood",
      "arrival_in_sec": 420,
      "arrival_at": 1759897620,
      "confidence": "medium",
      "delay_sec": -20
    }
  ],
  "timestamp": 1759897200
}
```

#### Prediction Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `vehicle_id` | string | Vehicle identifier |
| `route_id` | string | GTFS route identifier |
| `route_short_name` | string | Route number for display |
| `headsign` | string | Destination/direction |
| `arrival_in_sec` | integer | Seconds until arrival |
| `arrival_at` | integer | Unix timestamp of predicted arrival |
| `confidence` | string | Prediction confidence: `high`, `medium`, `low` |
| `delay_sec` | integer | Schedule deviation (positive = late, negative = early) |

#### Error Responses

**404 Not Found** - Stop does not exist

---

## Web API (Unversioned)

---

## GET /api/stops

**⭐ NEW:** Search for transit stops by location (GPS coordinates) or name.

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `lat` | float | Conditional* | Latitude for proximity search |
| `lon` | float | Conditional* | Longitude for proximity search |
| `name` | string | Conditional* | Stop name search (case-insensitive partial match) |
| `limit` | integer | No | Max results (default: 10 for location, 20 for name) |

\* Either `lat`+`lon` OR `name` must be provided

### Search by Location

Returns stops sorted by distance from coordinates:

```bash
curl 'https://localhost/api/stops?lat=52.1674652&lon=-106.5755517&limit=5'
```

**Response:**
```json
[
  {
    "gtfs_id": "3900",
    "name": "Evergreen / Wyant",
    "lat": 52.168249,
    "lon": -106.575778,
    "distance_km": 0.09
  },
  {
    "gtfs_id": "3921",
    "name": "Zary / Evergreen",
    "lat": 52.166906,
    "lon": -106.576557,
    "distance_km": 0.09
  }
]
```

### Search by Name

Returns stops matching name query:

```bash
curl 'https://localhost/api/stops?name=evergreen&limit=5'
```

**Response:**
```json
[
  {
    "gtfs_id": "3900",
    "name": "Evergreen / Wyant",
    "lat": 52.168249,
    "lon": -106.575778
  },
  {
    "gtfs_id": "3901",
    "name": "Evergreen / Salloum",
    "lat": 52.170604,
    "lon": -106.573167
  }
]
```

### Use Case: Finding Your Stop

To find stops for a route from address A to address B:

1. **Get GPS coordinates** from your map application or browser
2. **Search near origin:**
   ```bash
   curl 'https://localhost/api/stops?lat=52.1674652&lon=-106.5755517&limit=3'
   ```
3. **Search near destination:**
   ```bash
   curl 'https://localhost/api/stops?lat=52.132582&lon=-106.6675509&limit=3'
   ```
4. **Check predictions** for each stop:
   ```bash
   curl 'https://localhost/api/stops/3900/predictions?limit=5'
   ```

---

## GET /api/stops/{stopId}/predictions

**⭐ NEW:** Get realtime arrival predictions for a specific stop with countdown timers and confidence levels.

See complete documentation: [Arrival Predictions API](arrival-predictions.md)

### Quick Example

```bash
curl https://localhost/api/stops/3734/predictions?limit=5
```

### Response

```json
{
  "stop_id": "3734",
  "stop_name": "Primrose / Lenore",
  "predictions": [
    {
      "vehicle_id": "606",
      "route_id": "14",
      "arrival_in_sec": 180,
      "arrival_at": 1759897380,
      "confidence": "high",
      "status": { /* vehicle status */ },
      "current_location": {
        "lat": 52.1234,
        "lon": -106.5678,
        "stops_away": 2
      }
    }
  ]
}
```

---

## GET /api/realtime

Returns a complete snapshot of the current transit system state including vehicles, trips, alerts, and enriched status data.

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `route_id` | string | No | Filter vehicles to specific route (GTFS ID) |

### Example Requests

```bash
# All vehicles
curl https://localhost/api/realtime

# Vehicles on route 2 only
curl 'https://localhost/api/realtime?route_id=14530'
```

### Response

```json
{
  "ts": 1759894221,
  "vehicles": [
    {
      "id": "606",
      "route": "14530",
      "trip": "trip-123",
      "lat": 52.1234,
      "lon": -106.5678,
      "ts": 1759894200,
      "status": {
        "color": "orange",
        "label": "late",
        "severity": "😬 delayed",
        "deviation_sec": 240,
        "reason": "Moderate congestion detected along route 14530.",
        "feedback": {
          "ahead": 2,
          "on_time": 15,
          "late": 8,
          "total": 25
        }
      },
      "feedback": {
        "ahead": 2,
        "on_time": 15,
        "late": 8,
        "total": 25
      }
    }
  ],
  "trips": [...],
  "alerts": [...]
}
```

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `ts` | integer | Unix timestamp when snapshot was captured |
| `vehicles` | array | List of vehicle objects with status |
| `trips` | array | GTFS-RT TripUpdate data |
| `alerts` | array | Service alerts and disruptions |

### Vehicle Object

See [Data Models](models.md#vehicle) for complete schema.

---

## GET /api/realtime (Server-Sent Events)

Stream realtime updates using SSE protocol.

### Usage

```javascript
const source = new EventSource('https://localhost/api/realtime');

source.addEventListener('snapshot', (event) => {
  const data = JSON.parse(event.data);
  console.log('New snapshot:', data);
});
```

### Events

| Event Type | Description |
|------------|-------------|
| `snapshot` | Complete system snapshot (sent when data changes) |

Updates are sent only when the timestamp changes (new data available).

---

## GET /api/score

Returns headway scores for all route/direction groups.

### Response

```json
{
  "ts": 1759894221,
  "scores": [
    {
      "route_id": "14530",
      "direction": 0,
      "observed_headway_sec": 420,
      "grade": "B",
      "vehicle_count": 3
    }
  ]
}
```

### Score Object

| Field | Type | Description |
|-------|------|-------------|
| `route_id` | string | GTFS route identifier |
| `direction` | integer | Direction (0 or 1) |
| `observed_headway_sec` | integer | Median time between vehicles (seconds) |
| `grade` | string | Letter grade: A, B, C, D, F, or N/A |
| `vehicle_count` | integer | Number of active vehicles |

### Grading Scale

| Grade | Headway | Quality |
|-------|---------|---------|
| A | < 5 min | Excellent |
| B | 5-10 min | Good |
| C | 10-15 min | Fair |
| D | 15-20 min | Poor |
| F | > 20 min | Failing |
| N/A | - | Insufficient data |

---

## POST /api/vehicle-feedback

Submit rider feedback on vehicle punctuality.

### Request

```json
{
  "vehicleId": "606",
  "vote": "late"
}
```

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vehicleId` | string | Yes | Vehicle identifier |
| `vote` | string | Yes | One of: `ahead`, `on_time`, `late` |

### Response

```json
{
  "vehicleId": "606",
  "vote": "late",
  "summary": {
    "ahead": 2,
    "on_time": 15,
    "late": 9,
    "total": 26
  }
}
```

### Error Responses

**400 Bad Request**
```json
{
  "error": "Invalid payload"
}
```

**422 Unprocessable Entity**
```json
{
  "error": "vote must be one of: ahead, on_time, late"
}
```

---

## GET /api/vehicle-feedback/{vehicleId}

Retrieve aggregated feedback summary for a specific vehicle.

### Response

```json
{
  "vehicleId": "606",
  "summary": {
    "ahead": 2,
    "on_time": 15,
    "late": 8,
    "total": 25
  }
}
```

### Notes

- Feedback counters expire after 24 hours
- Redis key: `mtw:vehicle_feedback:{vehicleId}`

---

## Rate Limiting

Currently no rate limiting implemented. Please be respectful of the API.

## CORS

CORS headers are configured to allow cross-origin requests from any domain.
