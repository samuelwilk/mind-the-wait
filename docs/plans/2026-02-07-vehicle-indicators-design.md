# Vehicle Indicators Design

**Date:** 2026-02-07
**Status:** Approved

## Overview

Replace the tabbed route page (Performance History / Live Tracking) with a single unified view featuring compact real-time vehicle indicators.

## Visual Layout

```
┌─────────────────────────────────────────────────────┐
│  [1]  8th Street / City Centre          30-Day Grade│
│       Route 8                                  [B]  │
└─────────────────────────────────────────────────────┘

← 401 · At 8th St   523 · 2 min to Univ   118 · 5 min to Downtown →
   (horizontally scrollable row, updates via Mercure)

┌─────────────────────────────────────────────────────┐
│  Performance charts, stats, etc. (unchanged)        │
└─────────────────────────────────────────────────────┘
```

## Pill Badge Specifications

| State | Background | Example |
|-------|------------|---------|
| At stop (ETA ≤ 30s) | Green | `401 · At 8th St` |
| Approaching (ETA > 30s) | Blue/primary | `523 · 3 min to University` |
| No prediction | Gray | `118 · In transit` |
| No vehicles | — | Text: "No active vehicles" |

- Stale data (>60s or Mercure disconnect): Show yellow warning indicator
- No bus emoji in pills (keep compact)

## Files to Modify

### `templates/dashboard/route_detail.html.twig`
- Remove tab navigation (lines 62-74)
- Add vehicle indicators section where tabs were
- Add Mercure controller data attributes

### `src/Controller/RouteController.php`
- Add snapshot data to `show` action (reuse `RouteTrackingService`)
- Add Mercure hub URL to template context

## Files to Delete

- `templates/dashboard/route_live.html.twig`
- `templates/components/Route/Timeline.html.twig`
- `templates/components/Route/VehicleList.html.twig`
- `templates/components/Route/Header.html.twig`
- `src/Twig/Components/Route/Timeline.php` (if exists)
- `src/Twig/Components/Route/VehicleList.php` (if exists)
- `src/Twig/Components/Route/Header.php` (if exists)
- Controller action: `RouteController::live()`

## New Files

### `templates/components/VehicleIndicators.html.twig`
Horizontally scrollable row of vehicle pills. Structure:
```twig
<div id="vehicle-indicators"
     data-controller="vehicle-indicators"
     data-vehicle-indicators-mercure-url-value="{{ mercure_url }}">
    <div class="flex gap-2 overflow-x-auto">
        {% for vehicle in vehicles %}
            <span class="pill ...">{{ vehicle.displayId }} · {{ vehicle.statusText }}</span>
        {% endfor %}
    </div>
</div>
```

### `assets/controllers/vehicle_indicators_controller.js`
Stimulus controller that:
1. Subscribes to Mercure topic `route/{gtfsId}`
2. Updates pill content on each message
3. Shows stale indicator if no update in 60s

## Existing Code to Reuse

- `RouteTrackingService::getRouteSnapshot()` - Provides vehicle data
- `MercureRouteBroadcaster` - Already broadcasts to `route/{gtfsId}`
- `vehicle-freshness` controller logic - Adapt for pill updates

## Implementation Steps

1. Delete old live tracking files
2. Remove `live` action from RouteController
3. Add snapshot data to `show` action
4. Create VehicleIndicators component
5. Create Stimulus controller for Mercure
6. Update route_detail template (remove tabs, add indicators)
7. Test real-time updates
