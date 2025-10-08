# API Endpoints

Complete reference for all REST API endpoints in mind-the-wait.

## Base URL

```
https://localhost/api
```

## Authentication

Currently no authentication required. All endpoints are public.

---

## GET /api/realtime

Returns a complete snapshot of the current transit system state including vehicles, trips, alerts, and enriched status data.

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
