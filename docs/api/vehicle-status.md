# Vehicle Status System

The vehicle status system provides real-time punctuality indicators with delightful, human-friendly labels.

## Overview

Each vehicle in the `/api/realtime` response can include a `status` object that classifies its punctuality using:
- **Color codes** (6-color spectrum from green to purple)
- **Severity labels** (emoji-enhanced, goofy descriptions)
- **Delay values** (seconds ahead/behind schedule)
- **Traffic reasons** (heuristic explanations with 10% dad joke chance)
- **Crowd feedback** (aggregated rider votes)

## Status Object Schema

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

## Color Spectrum

Colors progress from earliest to latest:

| Color | Hex | Delay Range | Meaning |
|-------|-----|-------------|---------|
| 🟢 Green | `#00FF00` | ≤ -600s (10+ min early) | Way early |
| 🔵 Blue | `#0000FF` | -599s to -61s | Slightly early |
| 🟡 Yellow | `#FFFF00` | -60s to +60s | On time |
| 🟠 Orange | `#FFA500` | +61s to +419s | Slightly/moderately late |
| 🔴 Red | `#FF0000` | +420s to +899s | Very late |
| 🟣 Purple | `#800080` | ≥ +900s (15+ min late) | Catastrophically late |

### CSS Classes

For UI implementation:

```css
.status-green  { color: #00FF00; }
.status-blue   { color: #0000FF; }
.status-yellow { color: #FFFF00; }
.status-orange { color: #FFA500; }
.status-red    { color: #FF0000; }
.status-purple { color: #800080; }
```

## Severity Labels

Labels combine emojis with personality:

| Severity | Color | Delay Range | Vibe |
|----------|-------|-------------|------|
| 🚀 warp speed | Green | ≤ -600s | Hilariously early |
| ⚡ zooming | Blue | -599s to -180s | Comfortably early |
| 🏃 speedy | Blue | -179s to -61s | Slightly early |
| ✓ vibing | Yellow | -60s to +60s | Perfect timing |
| 🐌 fashionably late | Orange | +61s to +179s | Mildly late |
| 😬 delayed | Orange | +180s to +419s | Noticeably late |
| 🔥 yikes | Red | +420s to +899s | Very late |
| 💀 ghost bus | Purple | ≥ +900s | Where even is it? |

## Label Enum

Backend uses `VehiclePunctualityLabel`:

```php
enum VehiclePunctualityLabel: string
{
    case AHEAD   = 'ahead';
    case ON_TIME = 'on_time';
    case LATE    = 'late';
}
```

## Traffic Reasons

Heuristic explanations for delays/early arrivals:

### Standard Reasons

**Severe Delay (≥ 10 min late):**
```
"Severe traffic likely impacting {route} (delay {X} min)."
```

**Moderate Delay (2-10 min late):**
```
"Moderate congestion detected along route {route}."
```

**Way Early (≥ 5 min early):**
```
"Light traffic allowing vehicles on route {route} to run ahead."
```

**Slightly Early (2-5 min early):**
```
"Lower-than-normal demand on route {route}."
```

### Easter Egg Dad Jokes (10% chance)

When a vehicle has a delay ≥ 2 minutes, there's a 10% probability you'll receive one of these gems instead:

- "Driver heard there was a sale at the mall."
- "Bus stopped to argue with a pigeon."
- "Driver practicing speedruns. Current PB: 3 minutes early."
- "Time traveler drove this route."
- "Driver forgot to reset clock after daylight savings."
- "Vehicle achieved quantum entanglement with schedule."
- "Gremlins in the GPS again."
- "Driver took the scenic route (for science)."
- "Bus entered a wormhole near 3rd and Main."
- "Schedule machine needs more coffee."

## Status Calculation Logic

Status is determined by:

1. **Find next upcoming stop** from GTFS-RT TripUpdate predictions
2. **Extract delay value** (seconds ahead/behind)
3. **Classify delay** into color/severity bucket
4. **Generate reason** (90% heuristic, 10% dad joke)
5. **Attach feedback** from Redis cache

### When Status is NULL

Status will be `null` when:
- Vehicle has no `trip_id` in realtime feed
- Trip ID doesn't match any database record
- No TripUpdate predictions available
- Next stop arrival time is in the past (beyond grace period)

## Crowd Feedback Integration

Riders submit votes via `POST /api/vehicle-feedback`:

```json
{
  "vehicleId": "606",
  "vote": "late"
}
```

Votes are aggregated in Redis with 24-hour TTL and displayed in status:

```json
"feedback": {
  "ahead": 2,
  "on_time": 15,
  "late": 8,
  "total": 25
}
```

### Feedback Use Cases

- **Validation**: Compare heuristic status with crowd perception
- **Trust signals**: High agreement = reliable status
- **Conflict detection**: Large mismatch = investigate feed quality

## UI Implementation Examples

### React Component

```jsx
function VehicleStatus({ status }) {
  if (!status) return <span>No status available</span>;

  const colorClass = `status-${status.color}`;

  return (
    <div className={colorClass}>
      <span className="severity">{status.severity}</span>
      {status.reason && <p className="reason">{status.reason}</p>}
      <div className="feedback">
        👍 {status.feedback.on_time} |
        ⏰ {status.feedback.ahead} |
        ⏱️ {status.feedback.late}
      </div>
    </div>
  );
}
```

### Plain HTML/CSS

```html
<div class="vehicle-status status-orange">
  <span class="emoji">😬</span>
  <span class="severity">delayed</span>
  <span class="deviation">+4 minutes</span>
  <p class="reason">Moderate congestion detected along route 14530.</p>
</div>
```

## Testing

See `tests/Service/Realtime/VehicleStatusServiceTest.php` for comprehensive examples:

- All 6 color classifications
- All severity label emoji
- Feedback integration
- Null handling

Run tests:
```bash
make test-phpunit
```
