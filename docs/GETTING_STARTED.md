# Getting Started with Mind the Wait

Welcome! This guide will help you get the mind-the-wait transit monitoring system up and running.

## What is Mind the Wait?

Mind the Wait is a real-time transit headway monitoring system that:

- 📊 **Tracks Vehicle Performance** - Monitors bus positions and calculates service reliability
- ⏱️ **Predicts Arrivals** - Provides countdown timers with confidence levels
- 🎨 **Visualizes Status** - 6-color spectrum from "warp speed early" to "ghost bus late"
- 🌤️ **Correlates Weather** - Tracks weather impact on transit performance
- 📈 **Analyzes Trends** - Historical performance data and daily aggregation
- 👥 **Crowd Sources Feedback** - Riders vote on vehicle punctuality

## Quick Start

### Prerequisites

- **Docker Desktop** (or Docker Engine + Compose v2.10+)
- **GNU Make** (included on macOS/Linux, available via Chocolatey on Windows)
- **4GB RAM** minimum
- **5GB disk space**

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/samuelwilk/mind-the-wait.git
cd mind-the-wait

# 2. Run setup (one command does everything!)
make setup
```

That's it! The setup command will:
1. Build and start all Docker containers
2. Install PHP dependencies
3. Create databases and run migrations
4. Load GTFS static data (routes, stops, schedules)
5. Collect initial weather data
6. Calculate initial headway scores

**Time:** 3-7 minutes on first run

### Access the Application

Once setup completes:

- **Dashboard**: https://localhost/
- **API Docs**: See [docs/api/endpoints.md](api/endpoints.md)
- **Realtime Data**: `curl -sk https://localhost/api/realtime | jq`
- **Scores**: `curl -sk https://localhost/api/score | jq`

## Understanding the System

### Architecture Overview

```
┌─────────────────┐
│  GTFS Static    │     ┌──────────────┐
│  (Routes/Stops/ │────▶│  PostgreSQL  │
│   Schedules)    │     │  (Persistent │
└─────────────────┘     │   Storage)   │
                        └──────────────┘
┌─────────────────┐           │
│  GTFS Realtime  │           │
│  (Vehicle       │     ┌─────▼────────┐
│   Positions)    │────▶│    Redis     │
└─────────────────┘     │  (Realtime   │
                        │    Cache)    │
┌─────────────────┐     └──────────────┘
│  Open-Meteo API │           │
│  (Weather Data) │           │
└─────────────────┘           │
         │                    │
         └──────┬─────────────┘
                │
         ┌──────▼──────┐
         │   Symfony   │
         │ Application │
         └──────┬──────┘
                │
    ┌───────────┼───────────┐
    │           │           │
┌───▼────┐ ┌───▼────┐ ┌───▼────┐
│Dashboard│ │   API  │ │Scheduler│
│ (Web UI)│ │(REST)  │ │(Tasks) │
└─────────┘ └────────┘ └────────┘
```

### Key Components

1. **Python Parser** (`pyparser/`)
   - Polls GTFS-RT protobuf feeds every 12 seconds
   - Writes vehicle positions to Redis
   - Runs continuously in background

2. **Symfony Scheduler** (`scheduler` container)
   - **Score Calculation**: Every 30 seconds
   - **Weather Collection**: Hourly at :00
   - **Performance Aggregation**: Daily at 1:00 AM
   - Uses Symfony Messenger for reliable task execution

3. **PostgreSQL Database**
   - Static GTFS data (routes, stops, trips, schedules)
   - Historical weather observations
   - Daily performance metrics
   - Arrival logs

4. **Redis Cache**
   - Current vehicle positions (`mtw:vehicles`)
   - Trip updates (`mtw:trips`)
   - Service alerts (`mtw:alerts`)
   - Headway scores (`mtw:score`)
   - Vehicle feedback votes (`mtw:vehicle_feedback:*`)

## Core Concepts

### Headway Calculation

**Headway** = Time between consecutive vehicles on the same route

The system uses **position-based calculation** when possible:
1. Finds where each vehicle is on its route (GPS + schedule)
2. Estimates when each vehicle crosses the route midpoint
3. Calculates time deltas at that common point
4. More accurate than simple timestamp differences

**Fallback**: If position data unavailable, uses timestamp-based calculation.

### Grading System

Routes receive A-F grades based on service quality:

**Multi-vehicle routes** (graded by headway):
- **A**: ≤10 minutes between buses
- **B**: 10-15 minutes
- **C**: 15-20 minutes
- **D**: >20 minutes

**Single-vehicle routes** (graded by schedule adherence):
- **A**: On-time (±60 seconds)
- **B**: 1-3 minutes late
- **C**: 3-5 minutes late
- **D**: 5-10 minutes late
- **F**: >10 minutes late

### Confidence Levels

Scores include confidence to indicate data quality:

- **HIGH**: Multi-vehicle headway calculation (most reliable)
- **MEDIUM**: Single-vehicle schedule adherence (reliable)
- **LOW**: Default grade due to limited data (least reliable)

### Weather Impact

Weather conditions are assessed for transit impact:

- **None**: Normal operations
- **Minor**: Slight delays possible (2-5 min)
- **Moderate**: Expect 5-10 minute delays
- **Severe**: Major delays expected, plan extra time

Triggers based on:
- Temperature extremes (<-20°C, >30°C)
- Precipitation (rain/snow)
- Visibility (<5km)
- Wind speed (>40 km/h)

## Common Tasks

### View Logs

```bash
# Scheduler (score calculation, weather, aggregation)
docker compose logs -f scheduler

# Python parser (GTFS-RT polling)
docker compose logs -f pyparser

# PHP application
docker compose logs -f php

# All services
docker compose logs -f
```

### Check Data

```bash
# Redis vehicle positions
docker compose exec redis redis-cli HGETALL mtw:vehicles

# Redis scores
docker compose exec redis redis-cli HGETALL mtw:score

# PostgreSQL routes
docker compose exec php bin/console dbal:run-sql "SELECT * FROM route LIMIT 5"

# Latest weather
docker compose exec php bin/console dbal:run-sql \
  "SELECT * FROM weather_observation ORDER BY observed_at DESC LIMIT 1"
```

### Reload GTFS Data

```bash
# From default source (ArcGIS)
docker compose exec php bin/console app:gtfs:load --mode=arcgis

# From ZIP URL
docker compose exec php bin/console app:gtfs:load \
  --source=https://your-agency.com/gtfs.zip

# From local file
docker compose exec php bin/console app:gtfs:load \
  --source=/path/to/gtfs.zip
```

### Manual Commands

```bash
# Collect weather now
make weather-collect

# Calculate scores now
make score-tick

# Aggregate yesterday's performance
docker compose exec php bin/console app:aggregate:performance
```

### Run Tests

```bash
# All tests
make test-phpunit

# Specific test
docker compose exec php vendor/bin/phpunit tests/Service/Headway/HeadwayCalculatorTest.php

# Code coverage
docker compose exec php vendor/bin/phpunit --coverage-html coverage/
```

### Code Quality

```bash
# Fix code style
make cs-fix

# Check code style (dry run)
make cs-dry-run

# Clear cache
make cc
```

## Customization

### Change Transit Agency

1. Update GTFS static source in `.env.local`:
   ```env
   MTW_GTFS_STATIC_URL=https://your-agency.com/gtfs.zip
   ```

2. Update GTFS-RT feeds in `compose.override.yaml`:
   ```yaml
   services:
     pyparser:
       environment:
         VEH_URL: "https://your-agency.com/VehiclePositions.pb"
         TRIP_URL: "https://your-agency.com/TripUpdates.pb"
         ALERT_URL: "https://your-agency.com/Alerts.pb"
   ```

3. Update weather location in `WeatherService.php`:
   ```php
   private const LATITUDE = 52.1332;   // Your city
   private const LONGITUDE = -106.6700; // Your city
   ```

4. Reload data:
   ```bash
   docker compose down
   docker compose up -d
   make database
   make gtfs-load
   make weather-collect
   ```

### Adjust Schedules

Edit schedule classes in `src/Scheduler/`:

- `ScoreTickSchedule.php` - Score calculation frequency
- `WeatherCollectionSchedule.php` - Weather polling frequency
- `PerformanceAggregationSchedule.php` - Daily aggregation time

Then restart:
```bash
docker compose restart scheduler
```

## Troubleshooting

### No vehicles showing?

**Check parser logs:**
```bash
docker compose logs pyparser --tail=50
```

**Common causes:**
- Wrong GTFS-RT URLs
- Feed is down
- Feed requires authentication
- Trip IDs don't match static data

### Scores always N/A?

**Cause**: Not enough vehicles or no matching trip IDs

**Fix**:
1. Verify vehicles are being received: `curl -sk https://localhost/api/realtime`
2. Check trip ID matches: Compare realtime `trip_id` to database `trip` table
3. Reload static data if outdated: `make gtfs-load`

### Weather not updating?

**Check scheduler logs:**
```bash
docker compose logs scheduler | grep -i weather
```

**Verify schedule:**
```bash
docker compose exec php bin/console debug:scheduler
```

Should show next weather collection at top of the hour.

### Database issues?

**Reset database:**
```bash
make database  # Drops and recreates
make database-test  # Reset test database too
```

**Check migrations:**
```bash
docker compose exec php bin/console doctrine:migrations:status
```

## Next Steps

- 📖 **[API Documentation](api/endpoints.md)** - Complete REST API reference
- 🏗️ **[Architecture Overview](architecture/overview.md)** - System design deep dive
- 🧪 **[Testing Guide](development/testing.md)** - Writing and running tests
- 📊 **[Dashboard Features](features/public-dashboard.md)** - Dashboard capabilities
- 🌤️ **[Weather Integration](features/weather-integration.md)** - Weather correlation

## Support

- **Documentation**: See [docs/README.md](README.md)
- **Issues**: [GitHub Issues](https://github.com/samuelwilk/mind-the-wait/issues)
- **Contributing**: See [CONTRIBUTING.md](../CONTRIBUTING.md)
- **Changelog**: See [CHANGELOG.md](../CHANGELOG.md)

## License

MIT License - see [LICENSE](../LICENSE) for details.
