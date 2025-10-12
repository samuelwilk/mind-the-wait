# Route ID Mismatch Issue

**Status:** ✅ RESOLVED
**Date Discovered:** 2025-10-08
**Date Resolved:** 2025-10-11
**Resolution:** ArcGIS FeatureServer updated with post-August 31 schedule data

## Resolution Summary

On **October 9, 2025**, Saskatoon Transit updated their ArcGIS FeatureServer with the current GTFS static data, resolving the route ID mismatch completely.

### Final Results (October 11, 2025)
```bash
docker compose exec php bin/console app:gtfs:load --mode=arcgis
```

**Outcome:**
- ✅ **100% route ID match rate** (31/31 realtime routes matched)
- ✅ **126,690 stop_times** loaded (complete schedule coverage)
- ✅ **54 routes** including new routes 339, 340 from August 31 changes
- ✅ **6,165 trips** with matching IDs
- ✅ **1,376 stops** with full schedule data
- ✅ Position-based headway calculation operational
- ✅ HIGH confidence arrival predictions enabled
- ✅ GPS interpolation working with static schedule

**All realtime route IDs (14514-14568) now exist in database with correct route mappings.**

---

## Historical Context

The following sections document the investigation and resolution process for this issue.

### Original Impact (Oct 8-11, 2025)
Predictions showed wrong route IDs, no HIGH confidence predictions available

## Problem Summary

The GTFS-Realtime feed and static GTFS database use **completely different ID schemes** for the same routes, causing a 100% mismatch.

## Evidence

### Realtime Feed (saskprdtmgtfs.sasktrpcloud.com)
- **Source:** `https://saskprdtmgtfs.sasktrpcloud.com/TMGTFSRealTimeWebService/Vehicle/VehiclePositions.pb`
- **Route ID Range:** 14514 - 14568
- **Example:** Route ID `14536` corresponds to public route **43** (Evergreen / City Centre)

### Static GTFS Database (TransitFeeds)
- **Source:** `https://transitfeeds.com/p/city-of-saskatoon/264/latest/download`
- **Route ID Range:** 13880 - 13989
- **Example:** Route ID `13915` corresponds to public route **43** (Evergreen / City Centre)

### Actual Observation
```bash
# Google Maps shows: Route 43 departing Evergreen / Wyant at 5:40 AM
# Our system shows: Route 14536 (which doesn't exist in database)
# Database has: Route 13915 for route 43
```

## Diagnostic Results

**Realtime Route IDs in Feed:** 24 unique route IDs
**Matched in Database:** 0 (0%)
**ID Offset:** ~620 difference between realtime and database IDs

### Sample Comparison

| Public Route | Google Maps | Realtime ID | Database ID | Match? |
|--------------|-------------|-------------|-------------|--------|
| 43           | ✓           | 14536       | 13915       | ✗      |
| 45           | ✓           | 14529       | 13917       | ✗      |
| 16           | ✓           | (unknown)   | 13894       | ✗      |

## Impact on System

### Current Behavior
1. **Predictions API** (`/api/stops/{stopId}/predictions`)
   - Shows realtime route IDs (14514-14568)
   - These IDs don't exist in our database
   - Users can't look up route details

2. **Confidence Levels**
   - **HIGH:** Never achieved (requires GPS interpolation with static schedule)
   - **MEDIUM:** All predictions (from TripUpdate feed only)
   - **LOW:** Never returned

3. **Position-Based Headway**
   - Falls back to timestamp-based (inaccurate)
   - Can't use `PositionInterpolator` because trip IDs don't match

4. **Static Schedule Fallback**
   - Completely broken
   - 97.8% of stops show no scheduled service
   - Only 309,957 stop_times loaded (but trip IDs don't match realtime)

## Database Stats

### Before Full Reload (ArcGIS)
- Routes: 43
- Trips: 6,131
- Stop_times: 465
- Stops with schedules: 30 (2.2%)

### After Full Reload (TransitFeeds)
- Routes: 105
- Trips: 13,649
- Stop_times: 309,957
- Stops with schedules: Still 0 matching realtime

## Root Cause

**Major service changes effective August 31, 2025** caused a complete GTFS schedule update with new route IDs.

### Timeline
- **June 2025:** All available static GTFS exports use route IDs `14398-14455`
- **August 31, 2025:** [Major service changes](https://www.saskatoon.ca/news-releases/better-buses-better-service-transit-route-and-capacity-improvements-coming-fall) go into effect
  - New routes added: 340, 339
  - Routes modified: 4, 43, 44, 45, 46, 17, 10
  - Routes temporarily removed: 325, 336, 338
  - New route IDs assigned: `14519-14568+`
- **October 2025 (current):** Realtime feed reflects new schedule with new IDs

### Why Google Maps Works
Google Maps and other transit apps received the **updated GTFS static** (post-August 31, 2025) from Saskatoon Transit. All publicly available GTFS exports we can access are from **before August 31** and use the old ID scheme.

### Data Sources
- **saskprdtmgtfs.sasktrpcloud.com** - Realtime provider (reflects current schedule)
- **apps2.saskatoon.ca** - Official static GTFS (HTTP 503, down)
- **TransitFeeds / Mobility Database** - Archived static GTFS (June 2025, pre-update)
- **opendata.saskatoon.ca** - Official open data portal (current GTFS not found)

## Attempted Solutions

### ✗ Reload from Official Source
```bash
docker compose exec php bin/console app:gtfs:load --mode=zip \
  --source=http://apps2.saskatoon.ca/app/data/google_transit.zip
# Result: HTTP 503 (Service Unavailable)
```

### ✓ Loaded from TransitFeeds
```bash
docker compose exec php bin/console app:gtfs:load --mode=zip \
  --source=https://transitfeeds.com/p/city-of-saskatoon/264/latest/download
# Result: Complete data (309,957 stop_times), but IDs in 13xxx range (don't match)
```

### ✗ ArcGIS FeatureServer
```bash
docker compose exec php bin/console app:gtfs:load --mode=arcgis
# Result: Only 465 stop_times (99.9% data missing due to ID mismatch)
```

### ✓ Loaded from Mobility Database (mdb-716-202506290116.zip)
```bash
docker compose exec php bin/console app:gtfs:load --mode=zip \
  --source=/var/www/app/gtfs-static/mdb-716-202506290116.zip
# Result: Complete data (135,613 stop_times), IDs in 14xxx range but still offset by ~100-120
# Database: 14398-14455 (46 routes, 6,140 trips)
# Realtime: 14519-14568
# Closest match so far, but still 0% overlap
```

### ✗ Tried Mobility Database (mdb-716-202506280559.zip)
```bash
docker compose exec php bin/console app:gtfs:load --mode=zip \
  --source=/var/www/app/gtfs-static/mdb-716-202506280559.zip
# Result: Incomplete/corrupted export - only 465 stop_times for 6,131 trips
# This file appears to be missing 99%+ of stop_times data
# Same route ID range (14398-14454) but unusable
```

## Solution Implemented ✅

### Final Solution (October 2025)
**Use ArcGIS FeatureServer as primary GTFS static source**

The ArcGIS FeatureServer was updated by Saskatoon Transit on October 9, 2025 with complete post-August 31 schedule data.

```bash
docker compose exec php bin/console app:gtfs:load --mode=arcgis
```

This command now loads:
- Current route IDs (14514-14568) matching realtime feed
- Complete schedule data (126,690 stop_times)
- All active routes including new routes 339, 340

### Recommended Data Refresh Schedule
- **Weekly**: Run diagnostic to check for route ID drift
  ```bash
  docker compose exec php bin/console app:diagnose:route-ids
  ```
- **After service changes**: Reload GTFS static from ArcGIS
- **If mismatches appear**: Contact transit@saskatoon.ca for updated exports

### Previous Workaround Attempts (Not Needed)

The following workarounds were considered but are no longer necessary:

#### ❌ Option 1: Route ID Mapper
Build a mapping table based on route short_name - **not needed, IDs now match**

#### ❌ Option 2: Accept Current Limitations
Continue with mismatched IDs - **resolved, full functionality restored**

## Diagnostic Command

```bash
docker compose exec php bin/console app:diagnose:route-ids
```

This command shows:
- All realtime route IDs from current vehicle feed
- Which ones exist in database
- Complete route listing with short_name mappings

## Related Files

- `/src/Command/DiagnoseRouteIdCommand.php` - Diagnostic tool
- `/src/Dto/VehicleDto.php` - Route ID field mapping
- `/pyparser/parser.py` - Realtime feed parser (line 23: `route_id`)
- `/src/Service/Prediction/ArrivalPredictor.php` - Prediction logic
- `/docs/api/arrival-predictions.md` - API documentation
