# Quick Start Guide

Get mind-the-wait running locally in 5 minutes.

## Prerequisites

- Docker Desktop (or Docker Engine + Compose v2.10+)
- GNU Make
- 4GB RAM minimum
- 5GB disk space

## 1. Clone Repository

```bash
git clone https://github.com/yourusername/mind-the-wait.git
cd mind-the-wait
```

## 2. Start Services

```bash
make docker-build
```

This will:
- Build PHP/FrankenPHP container
- Start PostgreSQL, Redis, nginx
- Install Composer dependencies
- Generate TLS certificates

**Time:** ~2-3 minutes on first run

## 3. Set Up Database

```bash
make database
```

This runs:
- Drop existing database (if any)
- Create fresh database
- Run all migrations

**Time:** ~5 seconds

## 4. Load GTFS Data

### Option A: From ZIP (Recommended)

```bash
docker compose exec php bin/console app:gtfs:load \
  --source=https://your-transit-agency.com/gtfs.zip
```

### Option B: From ArcGIS (If agency provides it)

First, set environment variables in `.env.local`:

```env
MTW_ARCGIS_ROUTE=https://...
MTW_ARCGIS_STOP=https://...
MTW_ARCGIS_TRIP=https://...
MTW_ARCGIS_STOP_TIME=https://...
```

Then load:

```bash
docker compose exec php bin/console app:gtfs:load --mode=arcgis
```

**Time:** 30 seconds - 5 minutes depending on feed size

## 5. Configure Realtime Feed

Edit `compose.override.yaml` (create if missing):

```yaml
services:
  pyparser:
    environment:
      VEH_URL: "https://your-agency.com/gtfs-realtime/VehiclePositions.pb"
      TRIP_URL: "https://your-agency.com/gtfs-realtime/TripUpdates.pb"
      ALERT_URL: "https://your-agency.com/gtfs-realtime/Alerts.pb"
```

Restart the parser:

```bash
docker compose up -d pyparser
```

## 6. Verify It Works

### Check Realtime Data

```bash
curl -sk https://localhost/api/realtime | jq '.vehicles | length'
```

Should return a number > 0.

### Check Headway Scores

```bash
make score-tick
curl -sk https://localhost/api/score | jq '.scores | length'
```

Should return route/direction groups with grades.

### Check Status Enrichment

```bash
curl -sk https://localhost/api/realtime | jq '.vehicles[0].status'
```

Should show color, severity, deviation_sec (or null if trip IDs don't match).

## 7. Submit Test Feedback

```bash
curl -X POST https://localhost/api/vehicle-feedback \
  -H 'Content-Type: application/json' \
  -d '{"vehicleId":"veh-123","vote":"on_time"}'
```

## Troubleshooting

### "No vehicles in feed"

**Cause:** Realtime feed URL is wrong or feed is down

**Fix:**
```bash
# Check pyparser logs
docker compose logs pyparser

# Verify URLs are accessible
curl -I https://your-agency.com/gtfs-realtime/VehiclePositions.pb
```

### "Status is always null"

**Cause:** Trip IDs in realtime feed don't match static database

**Fix:**
1. Reload static data (may be outdated)
2. Check if agency uses different trip IDs for realtime vs static
3. See [CLAUDE.md Known Issues](../../CLAUDE.md#known-issues--limitations)

### "Database migrations fail"

**Cause:** Old schema conflicts

**Fix:**
```bash
make database  # Drops and recreates from scratch
```

### "Out of memory during GTFS load"

**Cause:** Large feed (>10k trips)

**Fix:**
Add to `.env.local`:
```env
PHP_MEMORY_LIMIT=1024M
```

Then:
```bash
docker compose down
docker compose up -d
```

## Next Steps

- [Development Guide](setup.md) - Deep dive into dev environment
- [API Documentation](../api/endpoints.md) - All endpoints and schemas
- [Architecture Overview](../architecture/overview.md) - How it all works
- [Testing Guide](testing.md) - Writing and running tests

## Common Commands

```bash
# Start containers
make docker-up

# Stop containers
make docker-down

# Open PHP shell
make docker-php

# Run tests
make test-phpunit

# Fix code style
make cs-fix

# View logs
docker compose logs -f scheduler
docker compose logs -f pyparser

# Check Redis data
docker compose exec redis redis-cli HGETALL mtw:vehicles
```

## Development Workflow

1. Make code changes in `src/`
2. Run tests: `make test-phpunit`
3. Fix style: `make cs-fix`
4. Commit changes
5. Push to GitHub

FrankenPHP worker mode auto-reloads on file changes (no restart needed).

## Support

- [Documentation](../README.md)
- [GitHub Issues](https://github.com/yourusername/mind-the-wait/issues)
- [CLAUDE.md](../../CLAUDE.md) for AI assistant context
