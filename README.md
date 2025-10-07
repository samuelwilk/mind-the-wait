# mind-the-wait

Transit headway monitoring for real-world GTFS agencies. The service ingests GTFS static schedules and GTFS-Realtime vehicle feeds, computes observed headways per route/direction, and grades service reliability in near real time. Redis stores the realtime snapshots, Symfony exposes APIs, and a Python sidecar maintains the GTFS-RT cache.

## Architecture Overview

- **Symfony 7 / FrankenPHP** – Core API and scoring services (`/api/score`, `/api/realtime`).
- **Doctrine + PostgreSQL** – Stores GTFS static data (routes, trips, stops, stop_times).
- **Redis (Predis client)** – Caches realtime vehicles, trip updates, alerts, and scores.
- **Python GTFS-RT sidecar** – Polls protobuf feeds and writes normalized JSON snapshots.
- **Headway pipeline** – `VehicleGrouper` → `HeadwayCalculator` → `HeadwayScorer` computes observed headways using TripUpdate predictions with interpolation/timestamp fallbacks.

See `CLAUDE.md` and `IMPLEMENTATION_NOTES.md` for deeper component notes.

## Prerequisites

- Docker Desktop or Docker Engine + Compose v2.10+
- GNU Make
- `mkcert` (only if you need local HTTPS certificates)

## Getting Started

```bash
make docker-build   # build containers and start detached
make docker-up      # subsequent starts (no rebuild)
make docker-down    # stop containers
make docker-php     # interactive shell inside php container
```

### Environment

Copy `.env` → `.env.local` (or adjust existing) and provide:

```
MTW_GTFS_STATIC_URL=...       # GTFS static ZIP
MTW_GTFS_STATIC_FALLBACK=...  # optional local mirror
REDIS_URL=redis://redis:6379
DATABASE_URL=postgresql://app:app@database:5432/app
```

## Loading GTFS Data

```bash
# inside containers via console command
docker compose exec php bin/console app:gtfs:load --source=/data/agency.zip

# or ArcGIS endpoints if configured in env (.env.local)
docker compose exec php bin/console app:gtfs:load --mode=arcgis
```

The `pyparser` service must be running with valid GTFS-RT URLs (`VEH_URL`, `TRIP_URL`, `ALERT_URL`) in `compose.override.yaml` or environment.

## Running the Headway Scoring Loop

```bash
make score-tick                # manual scoring pass
docker compose logs -f scheduler   # watch automated ticks (every 30s)
docker compose exec redis redis-cli HGETALL mtw:score
curl -s https://localhost/api/realtime | jq '.vehicles[] | {id, status}'
```

## Vehicle Status & Rider Feedback

- `/api/realtime` now returns per-vehicle punctuality with a red/yellow/green indicator, severity (minor/major/critical), delay in seconds, and optional traffic heuristics.
- `/api/vehicle-feedback` (POST) accepts `{"vehicleId":"veh-1","vote":"late"}` to crowd-source perception of delays; `GET /api/vehicle-feedback/{vehicleId}` returns aggregated vote totals.
- Status calculations prioritise GTFS-RT `delay` values for the next upcoming stop; feedback counters reset roughly every 24 hours.

## Testing & Linting

```bash
make cs-dry-run   # check style
make cs-fix       # apply style fixes
make test-phpunit # runs phpunit inside container
```

## Database Utilities

```bash
make database                     # drop/create/migrate dev DB
make database-migrations-generate # generate new migration
make database-migrations-execute  # apply latest migrations

make database-test                # prepare test DB (drop/create/migrate/fixtures)
```

## Troubleshooting

- Ensure Redis, PostgreSQL, and pyparser containers are running (`docker compose ps`).
- If GTFS static imports fail for large feeds, raise PHP memory (`PHP_MEMORY_LIMIT`) and retry.
- For trip ID mismatches (static vs realtime), reload the static feed and confirm TripUpdate data aligns (see `IMPLEMENTATION_NOTES.md`).

## License

This project inherits the MIT license from the Symfony Docker template. See `LICENSE` (if present) for details.
