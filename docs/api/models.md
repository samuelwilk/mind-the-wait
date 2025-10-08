# Data Models

Complete reference for all request/response data structures.

## Table of Contents

- [Vehicle](#vehicle)
- [VehicleStatus](#vehiclestatus)
- [VehicleFeedback](#vehiclefeedback)
- [Score](#score)
- [Snapshot](#snapshot)

---

## Vehicle

Represents a transit vehicle in the system.

### Schema

```typescript
interface Vehicle {
  id: string;           // Vehicle identifier
  route: string;        // GTFS route_id
  trip?: string;        // GTFS trip_id (optional)
  lat?: number;         // Latitude (optional)
  lon?: number;         // Longitude (optional)
  ts?: number;          // Unix timestamp of last update
  status?: VehicleStatus; // Enriched status (optional)
  feedback?: VehicleFeedback; // Aggregated votes (optional)
}
```

### Example

```json
{
  "id": "606",
  "route": "14530",
  "trip": "trip-abc123",
  "lat": 52.1234,
  "lon": -106.5678,
  "ts": 1759894200,
  "status": { /* VehicleStatus */ },
  "feedback": { /* VehicleFeedback */ }
}
```

### Field Details

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | Yes | Unique vehicle identifier from GTFS-RT |
| `route` | string | Yes | Route identifier from GTFS static |
| `trip` | string | No | Current trip assignment |
| `lat` | number | No | GPS latitude coordinate |
| `lon` | number | No | GPS longitude coordinate |
| `ts` | integer | No | Last position update timestamp |
| `status` | object | No | Enriched punctuality status |
| `feedback` | object | No | Crowd-sourced feedback summary |

---

## VehicleStatus

Enriched punctuality status for a vehicle.

### Schema

```typescript
interface VehicleStatus {
  color: 'green' | 'blue' | 'yellow' | 'orange' | 'red' | 'purple';
  label: 'ahead' | 'on_time' | 'late';
  severity: string;      // Emoji-enhanced description
  deviation_sec: number; // Seconds ahead (-) or behind (+)
  reason?: string;       // Traffic explanation (optional)
  feedback: VehicleFeedback;
}
```

### Example

```json
{
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
}
```

### Color Values

| Value | Meaning |
|-------|---------|
| `green` | Way early (≥10 min) |
| `blue` | Slightly early (1-10 min) |
| `yellow` | On time (±1 min) |
| `orange` | Slightly/moderately late (1-7 min) |
| `red` | Very late (7-15 min) |
| `purple` | Catastrophically late (≥15 min) |

### Severity Values

| Value | Color | Description |
|-------|-------|-------------|
| `🚀 warp speed` | green | 10+ min early |
| `⚡ zooming` | blue | 3-10 min early |
| `🏃 speedy` | blue | 1-3 min early |
| `✓ vibing` | yellow | ±1 min |
| `🐌 fashionably late` | orange | 1-3 min late |
| `😬 delayed` | orange | 3-7 min late |
| `🔥 yikes` | red | 7-15 min late |
| `💀 ghost bus` | purple | 15+ min late |

---

## VehicleFeedback

Aggregated crowd feedback votes.

### Schema

```typescript
interface VehicleFeedback {
  ahead: number;    // Count of "ahead" votes
  on_time: number;  // Count of "on_time" votes
  late: number;     // Count of "late" votes
  total: number;    // Total vote count
}
```

### Example

```json
{
  "ahead": 2,
  "on_time": 15,
  "late": 8,
  "total": 25
}
```

### Vote Request

```typescript
interface VehicleFeedbackRequest {
  vehicleId: string;
  vote: 'ahead' | 'on_time' | 'late';
}
```

---

## Score

Headway score for a route/direction group.

### Schema

```typescript
interface Score {
  route_id: string;           // GTFS route identifier
  direction: 0 | 1;           // Direction (0 or 1)
  observed_headway_sec: number; // Median headway in seconds
  grade: 'A' | 'B' | 'C' | 'D' | 'F' | 'N/A';
  vehicle_count: number;      // Active vehicles in group
}
```

### Example

```json
{
  "route_id": "14530",
  "direction": 0,
  "observed_headway_sec": 420,
  "grade": "B",
  "vehicle_count": 3
}
```

### Grade Calculation

| Grade | Headway Range | Quality |
|-------|---------------|---------|
| A | < 300s (5 min) | Excellent |
| B | 300-599s (5-10 min) | Good |
| C | 600-899s (10-15 min) | Fair |
| D | 900-1199s (15-20 min) | Poor |
| F | ≥ 1200s (20+ min) | Failing |
| N/A | - | Insufficient data (<2 vehicles) |

---

## Snapshot

Complete system state snapshot.

### Schema

```typescript
interface Snapshot {
  ts: number;           // Unix timestamp
  vehicles: Vehicle[];  // List of vehicles
  trips: any[];        // GTFS-RT TripUpdate data
  alerts: any[];       // Service alerts
}
```

### Example

```json
{
  "ts": 1759894221,
  "vehicles": [
    { /* Vehicle */ }
  ],
  "trips": [
    { /* GTFS-RT TripUpdate */ }
  ],
  "alerts": [
    { /* GTFS-RT Alert */ }
  ]
}
```

---

## Backend DTOs

PHP DTOs used internally (for reference):

### VehicleDto

```php
final readonly class VehicleDto
{
    public function __construct(
        public string $routeId,
        public ?DirectionEnum $direction = null,
        public ?int $timestamp = null,
        public ?string $tripId = null,
        public ?float $lat = null,
        public ?float $lon = null,
    ) {}
}
```

### VehicleStatusDto

```php
final readonly class VehicleStatusDto
{
    public function __construct(
        public VehicleStatusColor $color,
        public VehiclePunctualityLabel $label,
        public string $severity,
        public int $deviationSec,
        public ?string $reason = null,
        public array $feedback = [],
    ) {}
}
```

### ScoreDto

```php
final readonly class ScoreDto
{
    public function __construct(
        public string $routeId,
        public int $direction,
        public int $observedHeadwaySec,
        public ScoreGradeEnum $grade,
        public int $vehicleCount,
    ) {}
}
```

---

## Validation Rules

### Vehicle ID
- Type: string
- Required: Yes
- Pattern: Any non-empty string
- Example: `"606"`, `"veh-123"`, `"bus-42"`

### Vote Value
- Type: string
- Required: Yes
- Enum: `"ahead"`, `"on_time"`, `"late"`
- Case-sensitive

### Route ID
- Type: string
- Format: From GTFS static feed
- Example: `"14530"`, `"route-1"`, `"express-A"`

### Direction
- Type: integer
- Values: `0` or `1`
- Meaning: Feed-specific (consult GTFS `directions.txt`)

---

## Error Responses

### Standard Error Format

```json
{
  "error": "Description of what went wrong"
}
```

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | Success | Request completed |
| 400 | Bad Request | Malformed JSON |
| 404 | Not Found | Unknown endpoint |
| 422 | Unprocessable | Invalid vote value |
| 500 | Server Error | Internal failure |

---

## Serialization Notes

- All timestamps are Unix epoch seconds (integer)
- All coordinates are decimal degrees (float)
- Null values may be omitted from JSON responses
- Empty arrays are always included as `[]`
