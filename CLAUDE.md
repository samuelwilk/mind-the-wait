# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**mind-the-wait** is a transit headway monitoring system that calculates and scores the reliability of public transit vehicles in real-time. It ingests GTFS (General Transit Feed Specification) static schedule data and GTFS-Realtime position feeds to compute observed headways (time between consecutive vehicles) on transit routes, then grades service quality.

**Tech Stack:** Symfony 7.3, Doctrine ORM, PostgreSQL, Redis (Predis), Docker, Python GTFS-RT parser

## Development Commands

All commands should be run via the Makefile targets, which wrap Docker Compose operations:

### Setup & Environment
```bash
make docker-build          # Build and start containers
make docker-up            # Start containers without building
make docker-down          # Stop containers
make docker-php           # Open interactive shell in PHP container
```

### Database
```bash
make database             # Full setup: drop, create, migrate
make database-migrations-generate  # Generate new migration from entity changes
make database-migrations-execute   # Run pending migrations
```

### Code Quality
```bash
make cs-fix               # Auto-fix code style with php-cs-fixer
make cs-dry-run          # Check code style without fixing
make test-phpunit        # Run PHPUnit tests
```

### Application Commands
```bash
# Load GTFS static data (routes, stops, trips, stop_times)
docker compose exec php bin/console app:gtfs:load

# Process GTFS-RT feeds (enqueues polls for vehicles, trips, alerts)
docker compose exec php bin/console app:gtfs:tick

# Compute headway scores from realtime vehicle positions
make score-tick

# Seed synthetic performance data for development/demo (⚠️ GENERATES FAKE DATA)
# Creates 30 days of realistic-looking route performance and weather data
# Only use in development - DO NOT use in production
docker compose exec php bin/console app:seed:performance-data --clear

# Pre-warm AI insight cache for fast page loads
# Generates 7 AI-powered insights (2 dashboard + 5 weather analysis)
# Runs nightly at 2:00 AM via Symfony Scheduler in production
docker compose exec php bin/console app:warm-insight-cache

# Submit rider feedback (votes: ahead|on_time|late)
curl -X POST https://localhost/api/vehicle-feedback \
  -H 'Content-Type: application/json' \
  -d '{"vehicleId":"veh-1","vote":"late"}'
```

## Architecture

### Data Flow Pipeline

1. **GTFS Static Ingestion** (`GtfsLoadCommand`)
   - Downloads/extracts GTFS zip OR fetches from ArcGIS FeatureServer
   - Loads into PostgreSQL: `Route`, `Stop`, `Trip`, `StopTime` entities
   - Supports two modes: ZIP (standard GTFS) and ArcGIS (feature pagination)

2. **GTFS-Realtime Polling** (Python sidecar: `pyparser/`)
   - Python script polls GTFS-RT protobuf feeds (vehicles, trip updates, alerts)
   - Parses protobuf → JSON and writes to Redis keys: `mtw:vehicles`, `mtw:trips`, `mtw:alerts`
   - Runs continuously in Docker container `pyparser`

3. **Headway Scoring** (`ScoreTickCommand` + services)
   - Reads vehicle positions from Redis (`RealtimeRepository::getVehicles()`)
   - Groups vehicles by `(route_id, direction)` (`VehicleGrouper`)
   - Calculates observed headway and assigns grade (`HeadwayCalculator`, `HeadwayScorer`)
   - Writes scores back to Redis: `mtw:score`
   - Runs every 30 seconds via `scheduler` container

4. **API Exposure**
   - `/api/score` → returns latest headway scores from Redis (`ScoreController`)
   - `/api/realtime` → returns raw vehicle/trip/alert snapshot enriched with per-vehicle status + feedback (`RealtimeController`)
   - `/api/vehicle-feedback` → crowd feedback endpoints for punctuality votes (`VehicleFeedbackController`)

### Key Components

**Commands:**
- `GtfsLoadCommand`: ETL for static GTFS data (ZIP or ArcGIS)
- `TickGtfsCommand`: Dispatches async messages to poll GTFS-RT feeds (currently disabled; Python sidecar handles this)
- `ScoreTickCommand`: Orchestrates headway calculation and scoring
- `WarmInsightCacheCommand`: Pre-generates AI insights for instant page loads

**Services:**
  - `VehicleGrouper`: Groups vehicles by route + direction
  - `HeadwayCalculator`: Computes mean headway and assigns A-F grade
  - `HeadwayScorer`: Coordinates grouping + calculation, outputs `ScoreDto[]`
  - `VehicleStatusService`: Builds red/yellow/green punctuality with feedback + heuristics
  - `HeuristicTrafficReasonProvider`: Generates placeholder traffic reasons for delays/early running
  - `InsightGeneratorService`: Generates AI-powered narrative insights using OpenAI GPT-4o-mini
  - `OverviewService`: Dashboard system metrics with AI insight cards
  - `WeatherAnalysisService`: Weather impact analysis with AI-generated narratives

**Repositories:**
- `RealtimeRepository`: Redis-backed storage for vehicles, trips, alerts, scores
- `RouteRepository`, `StopRepository`, `TripRepository`, `StopTimeRepository`: Doctrine repositories for static GTFS entities
- `BaseRepository`: Shared upsert logic with `gtfs_id` uniqueness handling

**DTOs/Enums:**
- `VehicleDto`, `ScoreDto`: Immutable data transfer objects
- `DirectionEnum` (0/1), `GtfsSourceEnum` (Zip/ArcGIS), `ScoreGradeEnum` (A-F), `RouteTypeEnum` (Bus/Rail/etc)

### Redis Keys

- `mtw:vehicles` → hash: `{ts: int, json: VehicleDto[]}`
- `mtw:trips` → hash: `{ts: int, json: TripUpdate[]}`
- `mtw:alerts` → hash: `{ts: int, json: Alert[]}`
- `mtw:score` → hash: `{ts: int, json: ScoreDto[]}`
- `mtw:vehicle_feedback:<vehicleId>` → hash storing vote tallies (ahead/on_time/late/total)

### Container Architecture

- **php**: FrankenPHP runtime for Symfony app
- **nginx**: Reverse proxy (ports 8080/443)
- **database**: PostgreSQL 16
- **redis**: Redis 7 (realtime data store)
- **pyparser**: Python sidecar that polls GTFS-RT protobuf feeds and writes to Redis
- **scheduler**: Runs `app:score:tick` every 30 seconds and `app:warm-insight-cache` nightly at 2:00 AM
- **worker**: Symfony Messenger consumer (disabled by default; requires `--profile queue`)

### Important Patterns

**Bulk Operations:**
- `StopTimeRepository::bulkInsert()` uses native SQL for batch inserts (~50K rows)
- `BaseRepository::upsert()` handles GTFS entities with `ON CONFLICT (gtfs_id) DO UPDATE`

**GTFS Time Handling:**
- GTFS allows times like "25:30:00" (1:30 AM next day)
- `GtfsTimeUtils::timeToSeconds()` converts to seconds since midnight, supports >24h

**Direction Mapping:**
- Realtime vehicle feeds don't always include direction
- `TripRepository::directionMapByGtfsId()` preloads `trip_id → direction` for O(1) lookup

## Environment Variables

Configure in `.env.local`:

```bash
# GTFS Static source (ZIP mode)
MTW_GTFS_STATIC_URL=https://example.com/gtfs.zip
MTW_GTFS_STATIC_FALLBACK=/path/to/local.zip

# ArcGIS FeatureServer (alternative to ZIP)
MTW_ARCGIS_ROUTE=https://...
MTW_ARCGIS_STOP=https://...
MTW_ARCGIS_TRIP=https://...
MTW_ARCGIS_STOP_TIME=https://...

# Database
DATABASE_URL=postgresql://user:pass@127.0.0.1:5432/mindthewait

# Redis
REDIS_URL=redis://redis:6379
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages

# OpenAI API (for AI-generated insights)
# Get your key from https://platform.openai.com/api-keys
OPENAI_API_KEY=your-openai-api-key-here
```

## Testing & CI

- PHPUnit tests: `make test-phpunit`
- Code style: `make cs-fix` (uses `.php-cs-fixer.dist.php`)
- Git hooks configured in `.githooks/` (pre-commit runs cs-fixer)
- Realtime status coverage lives in `tests/Service/Realtime/VehicleStatusServiceTest.php`.

## Common Workflows

**Loading fresh GTFS data:**
```bash
# From ZIP
docker compose exec php bin/console app:gtfs:load --source=/path/to/gtfs.zip

# From ArcGIS (uses env vars)
docker compose exec php bin/console app:gtfs:load --mode=arcgis
```

**Monitoring realtime scores:**
```bash
# Kick off a manual scoring pass
make score-tick

# Check Redis for latest scores
docker compose exec redis redis-cli HGETALL mtw:score

# Tail scheduler logs
docker compose logs -f scheduler
```

**Generating migrations after entity changes:**
```bash
make database-migrations-generate
# Review migration, then:
make database-migrations-execute
```

**Seeding sample data for development:**
```bash
# ⚠️ Only for development/demo - generates FAKE but realistic-looking data
# Creates 30 days of performance records and weather observations

# Seed data (clears existing performance data first)
docker compose exec php bin/console app:seed:performance-data --clear

# Check what was created
docker compose exec php bin/console dbal:run-sql "SELECT COUNT(*) FROM route_performance_daily"
docker compose exec php bin/console dbal:run-sql "SELECT COUNT(*) FROM weather_observation"

# View a route detail page to see charts populated with fake data
# https://localhost/routes/14514
```

**Important:** This command generates synthetic data with realistic patterns (weather impact, day-of-week variations, etc.) but is NOT real transit data. In production, use `app:collect:arrival-logs` and `app:collect:daily-performance` to collect and aggregate real data instead.

**Working with AI-generated insights:**
```bash
# Pre-warm insight cache (generates all 7 insights)
docker compose exec php bin/console app:warm-insight-cache

# Clear insight cache to force regeneration
docker compose exec php bin/console cache:pool:clear cache.app

# Check Symfony scheduler status
docker compose exec php bin/console debug:scheduler

# View scheduler logs (includes nightly cache warming at 2:00 AM)
docker compose logs -f scheduler
```

**AI Insight Features:**
- **Dashboard Overview**: 2 AI-generated insight cards (Winter Weather Impact, Temperature Threshold)
- **Weather Impact Page**: 5 AI-generated narratives (Winter Operations, Temperature Threshold, Weather Impact Matrix, Bunching Analysis, Key Takeaway)
- **Caching**: 24-hour cache with automatic nightly refresh at 2:00 AM
- **Model**: OpenAI GPT-4o-mini (fast, cost-effective)
- **Cost**: ~$0.05/month (~60 cents/year) with 24-hour caching (7 insights/day)
- **Fallback**: Graceful fallback message if API fails or rate limits hit
- **Retry Logic**: 2 automatic retries with 5-second delays for rate limit handling

**Cache Key Strategy:**
Insights are cached with content-based keys using `md5(serialize($stats))`. This means:
- Same data = same cached insight (fast)
- Changed data = new insight generated (dynamic content)
- Cache duration: 24 hours (refreshed nightly)

## Known Issues & Limitations

### Position-Based Headway Calculation

The system uses position-based headway calculation when possible, with automatic fallback to timestamp-based calculation.

**How it works:**
1. `PositionInterpolator` uses vehicle GPS (lat/lon) to find nearest stop
2. Uses stop sequence + schedule to estimate when vehicle crosses route midpoint
3. Calculates headway as time deltas at that common crossing point
4. More accurate than timestamp deltas (which just measure GPS ping intervals)

**Fallback behavior:**
- If trip IDs in realtime feed don't match database, falls back to timestamp-based calculation
- If vehicles lack lat/lon or trip_id, falls back to timestamp-based calculation
- Fallback headways show GPS ping intervals (1-15 sec) instead of true vehicle spacing

**Trip ID mismatch (common issue):**
Transit agencies often have misaligned GTFS feeds:
- **Realtime feed**: Current active trips (updated hourly/daily)
- **Static feed**: Scheduled trips (updated weekly/monthly)

When trip IDs don't overlap, position-based calculation fails. This happens when:
- Static schedule is outdated (new service period started)
- Agency uses different trip ID schemes for different seasons
- Static endpoints are down/stale

**For Saskatoon Transit specifically:**
- Official ZIP (`apps2.saskatoon.ca`) is sometimes down (503 errors)
- ArcGIS FeatureServer has outdated data (~6k trips vs full 13k)
- TransitFeeds fallback has full dataset but requires increased memory limit
- Realtime and static trip IDs may be from different service periods

**Solution:** Reload GTFS static when trip IDs mismatch. For large feeds, increase PHP memory limit:
```bash
# In compose.yaml or .env, set:
PHP_MEMORY_LIMIT=1024M

# Then reload:
docker compose down -v
docker compose up -d
docker compose exec php bin/console app:gtfs:load --mode=zip
```
