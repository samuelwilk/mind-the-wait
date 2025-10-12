# Arrival Predictions API

Real-time predictions for when transit vehicles will arrive at specific stops.

## Overview

The arrival prediction system uses a 3-tier fallback strategy to provide the most accurate estimates possible:

1. **HIGH confidence** - GTFS-RT TripUpdate predictions (agency-provided ETAs)
2. **MEDIUM confidence** - GPS interpolation (position + schedule calculations)
3. **LOW confidence** - Static schedule (timetable fallback)

## Quick Start

**Step 1: Find your stop ID**

```bash
# Search by location (returns stops sorted by distance)
curl 'https://localhost/api/stops?lat=52.1674652&lon=-106.5755517&limit=3'

# Or search by name
curl 'https://localhost/api/stops?name=evergreen&limit=5'
```

**Step 2: Get arrival predictions**

```bash
curl 'https://localhost/api/stops/3900/predictions?limit=5'
```

See [Stop Search API](endpoints.md#get-apistops) for complete documentation.

## Endpoints

### GET /api/stops/{stopId}/predictions

Get upcoming vehicle arrivals for a specific stop.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `stopId` | string | Yes | GTFS stop identifier (path parameter) |
| `limit` | integer | No | Max predictions to return (default: 10) |
| `route` | string | No | Filter by route ID |

#### Example Request

```bash
# Get next 5 arrivals at a stop
curl https://localhost/api/stops/3734/predictions?limit=5

# Filter by specific route
curl https://localhost/api/stops/3734/predictions?route=14&limit=3
```

#### Response Schema

```typescript
interface StopPredictionResponse {
  stop_id: string;
  stop_name: string;
  predictions: ArrivalPrediction[];
}

interface ArrivalPrediction {
  vehicle_id: string;
  route_id: string;
  trip_id: string;
  stop_id: string;
  headsign: string | null;           // e.g. "Downtown" / "University"
  arrival_in_sec: number;            // Countdown timer (seconds)
  arrival_at: number;                // Unix timestamp
  confidence: 'high' | 'medium' | 'low';
  delay_sec: number | null;          // Schedule deviation (negative=early, positive=late)
  status: VehicleStatus | null;      // Punctuality status
  current_location: {
    lat: number;
    lon: number;
    stops_away: number | null;
  } | null;
  feedback_summary: {
    ahead: number;
    on_time: number;
    late: number;
    total: number;
  };
}
```

#### Example Response

```json
{
  "stop_id": "3734",
  "stop_name": "Primrose / Lenore",
  "predictions": [
    {
      "vehicle_id": "606",
      "route_id": "14",
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
        "deviation_sec": 30,
        "reason": null,
        "feedback": {
          "ahead": 1,
          "on_time": 12,
          "late": 3,
          "total": 16
        }
      },
      "current_location": {
        "lat": 52.1234,
        "lon": -106.5678,
        "stops_away": 2
      },
      "feedback_summary": {
        "ahead": 1,
        "on_time": 12,
        "late": 3,
        "total": 16
      }
    },
    {
      "vehicle_id": "707",
      "route_id": "14",
      "trip_id": "trip-def456",
      "stop_id": "3734",
      "headsign": "University",
      "arrival_in_sec": 840,
      "arrival_at": 1759898040,
      "confidence": "medium",
      "delay_sec": 360,
      "status": {
        "color": "orange",
        "label": "late",
        "severity": "🐌 fashionably late",
        "deviation_sec": 180,
        "reason": "Moderate congestion detected along route 14.",
        "feedback": {
          "ahead": 0,
          "on_time": 4,
          "late": 7,
          "total": 11
        }
      },
      "current_location": {
        "lat": 52.1567,
        "lon": -106.6123,
        "stops_away": 5
      },
      "feedback_summary": {
        "ahead": 0,
        "on_time": 4,
        "late": 7,
        "total": 11
      }
    }
  ]
}
```

## Confidence Levels

### High Confidence

- **Source:** GTFS-RT TripUpdate predictions
- **Accuracy:** Agency-calculated ETAs based on realtime conditions
- **Use case:** Most accurate, use for critical decisions

### Medium Confidence

- **Source:** GPS position interpolation
- **Accuracy:** Calculated from current vehicle location + schedule
- **Use case:** Good fallback when TripUpdates unavailable

### Low Confidence

- **Source:** Static GTFS schedule
- **Accuracy:** Assumes on-time performance (no realtime data)
- **Use case:** Last resort, treat as approximate

## Field Descriptions

### arrival_in_sec

Countdown timer showing seconds until arrival. Calculated as:

```
arrival_in_sec = max(0, arrival_at - current_time)
```

Always ≥ 0 (never negative). If vehicle has passed, countdown stops at 0.

### delay_sec

Schedule deviation in seconds. Compares the realtime predicted arrival with the static GTFS schedule.

- **Negative values** = Bus is early (e.g., `-120` = 2 minutes ahead of schedule)
- **Positive values** = Bus is late (e.g., `360` = 6 minutes behind schedule)
- **Zero** = Bus is exactly on time
- **null** = No static schedule available for this trip/stop combination

**Calculation:**
```
delay_sec = realtime_arrival - scheduled_arrival
```

**Example:**
- Scheduled arrival: 6:51 PM (from GTFS `stop_times.txt`)
- Realtime prediction: 6:57 PM (from TripUpdate or GPS interpolation)
- Result: `delay_sec = 360` (6 minutes late)

**Use cases:**
- Show "6 min late" badges in rider-facing UIs
- Track on-time performance metrics
- Trigger alerts when delays exceed thresholds
- Compare against crowd feedback votes (ahead/on_time/late)

**Note:** This field complements the `status.deviation_sec` field from the vehicle status system, but uses static schedule as the baseline instead of dynamic headway calculations.

### stops_away

Number of scheduled stops between vehicle's current position and target stop.

- `null` if vehicle has no GPS position
- `0` if vehicle is at or past the target stop
- Calculated using nearest stop detection via Haversine distance

### status

See [Vehicle Status System](vehicle-status.md) for complete documentation on color codes, severity labels, and deviation calculations.

### headsign

Destination or direction indicator from GTFS `trips.trip_headsign`.

Common examples:
- "Downtown"
- "University"
- "Express - Mall"
- "Northbound" / "Southbound"

May be `null` if trip has no headsign defined.

## Error Responses

### 404 Not Found

```json
{
  "error": "Stop not found"
}
```

**Cause:** Invalid `stopId` (not in GTFS static database)

## Data Freshness

- Predictions update every ~30 seconds (controlled by Python sidecar polling interval)
- Stale predictions (>2 minutes old) may indicate GTFS-RT feed issues
- Check `arrival_in_sec` countdown for realtime accuracy

## Performance Notes

- Response time: ~50-200ms (depends on number of active vehicles)
- Predictions are computed on-demand (not cached)
- Limit parameter reduces compute time for large stops

## Integration Examples

### JavaScript (Fetch API)

```javascript
async function getArrivals(stopId, limit = 5) {
  const response = await fetch(
    `https://localhost/api/stops/${stopId}/predictions?limit=${limit}`
  );
  const data = await response.json();
  return data.predictions;
}

// Display countdown timers
function formatCountdown(seconds) {
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  return `${minutes} min`;
}

const predictions = await getArrivals('3734');
predictions.forEach(p => {
  console.log(`${p.route_id} → ${formatCountdown(p.arrival_in_sec)}`);
});
```

### Python

```python
import requests

def get_arrivals(stop_id: str, limit: int = 5):
    response = requests.get(
        f"https://localhost/api/stops/{stop_id}/predictions",
        params={"limit": limit},
        verify=False  # For self-signed certs
    )
    return response.json()["predictions"]

predictions = get_arrivals("3734")
for p in predictions:
    minutes = p["arrival_in_sec"] // 60
    print(f"{p['route_id']} in {minutes} min ({p['confidence']})")
```

### React Component

```jsx
function StopPredictions({ stopId }) {
  const [predictions, setPredictions] = useState([]);

  useEffect(() => {
    const fetchPredictions = async () => {
      const response = await fetch(
        `/api/stops/${stopId}/predictions?limit=5`
      );
      const data = await response.json();
      setPredictions(data.predictions);
    };

    fetchPredictions();
    const interval = setInterval(fetchPredictions, 30000); // Refresh every 30s
    return () => clearInterval(interval);
  }, [stopId]);

  return (
    <div>
      <h2>{predictions[0]?.stop_name || 'Loading...'}</h2>
      {predictions.map(p => (
        <div key={p.vehicle_id} className={`prediction-${p.status?.color}`}>
          <span className="route">{p.route_id}</span>
          <span className="headsign">{p.headsign}</span>
          <span className="countdown">
            {Math.floor(p.arrival_in_sec / 60)} min
          </span>
          <span className="confidence">{p.confidence}</span>
        </div>
      ))}
    </div>
  );
}
```

## Related Endpoints

- [GET /api/realtime](endpoints.md#get-apirealtime) - Full system snapshot
- [GET /api/score](endpoints.md#get-apiscore) - Headway grades
- [GET /api/vehicle-feedback/{vehicleId}](endpoints.md#get-apivehicle-feedbackvehicleid) - Crowd perception

## Troubleshooting

### All predictions show `confidence: "low"`

**Cause:** Realtime GTFS-RT feed not available or trip IDs mismatched

**Solution:**
1. Check Python sidecar logs: `docker compose logs pyparser`
2. Verify GTFS-RT URLs in `compose.override.yaml`
3. Reload GTFS static data if trip IDs outdated

### `stops_away` always `null`

**Cause:** Vehicles missing GPS coordinates in realtime feed

**Solution:** Check VehiclePosition feed includes `latitude` and `longitude` fields

### Predictions missing for active vehicles

**Cause:** Vehicle trip ID doesn't appear in stop's schedule

**Solution:** Vehicle may be on different route variant or trip not serving this stop

### Status field always `null`

**Cause:** No TripUpdate delay data available

**Solution:** Ensure GTFS-RT TripUpdate feed includes `delay` field for stop_time_updates

## FAQ

**Q: What's the difference between `status.feedback` and `feedback_summary`?**
A: They're the same data. `feedback_summary` is top-level for convenience, `status.feedback` is nested within status object.

**Q: Can I get historical predictions?**
A: No, predictions are realtime only. For historical analysis, log predictions externally.

**Q: Why do some vehicles have `headsign: null`?**
A: Trip doesn't define a headsign in GTFS `trips.txt`. Use route name as fallback.

**Q: How accurate are "medium" confidence predictions?**
A: Typically within ±2 minutes if vehicle is maintaining schedule speed. Less accurate in heavy traffic.

**Q: Can I subscribe to prediction updates?**
A: Not currently. Poll endpoint every 30-60 seconds for updates, or use [SSE stream](endpoints.md#get-apirealtime-server-sent-events) for full realtime snapshot.
