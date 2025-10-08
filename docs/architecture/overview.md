# Architecture Overview

High-level system design and data flow for mind-the-wait.

## System Goals

1. **Track realtime headways** - Measure observed time between consecutive vehicles
2. **Grade service quality** - Assign A-F grades based on headway consistency
3. **Enrich with status** - Provide delightful, human-friendly punctuality indicators
4. **Enable feedback** - Allow riders to vote on perceived punctuality

## Architecture Diagram

```
┌─────────────────┐
│  GTFS Static    │
│  (ZIP/ArcGIS)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐       ┌──────────────┐
│ GtfsLoadCommand │──────▶│  PostgreSQL  │
│  ETL Pipeline   │       │  (Doctrine)  │
└─────────────────┘       └──────┬───────┘
                                 │
                                 │ Static data
                                 ▼
┌─────────────────┐       ┌──────────────┐       ┌─────────────┐
│ GTFS-Realtime   │       │   Python     │       │    Redis    │
│  Protobuf Feed  │──────▶│   Sidecar    │──────▶│   (Predis)  │
└─────────────────┘       │  (pyparser)  │       │             │
                          └──────────────┘       └──────┬──────┘
                                                         │
                                                         │ Realtime data
                                                         ▼
┌─────────────────┐       ┌──────────────┐       ┌──────────────┐
│   Scheduler     │──────▶│  Symfony     │◀──────│ RealtimeRepo │
│  (30s cron)     │       │   Services   │       │              │
└─────────────────┘       └──────┬───────┘       └──────────────┘
                                 │
                                 ├──▶ VehicleGrouper
                                 ├──▶ HeadwayCalculator
                                 ├──▶ HeadwayScorer
                                 ├──▶ VehicleStatusService
                                 │
                                 ▼
                          ┌──────────────┐
                          │  REST APIs   │
                          │ (FrankenPHP) │
                          └──────┬───────┘
                                 │
                                 ▼
                          ┌──────────────┐
                          │   Frontend   │
                          │   (Future)   │
                          └──────────────┘
```

## Component Responsibilities

### Data Ingestion Layer

**GtfsLoadCommand** (`src/Command/GtfsLoadCommand.php`)
- Downloads/extracts GTFS ZIP or polls ArcGIS FeatureServer
- Parses CSV/JSON into Doctrine entities
- Bulk inserts into PostgreSQL
- Supports incremental updates with `ON CONFLICT` upserts

**Python Sidecar** (`pyparser/`)
- Continuously polls GTFS-RT protobuf feeds
- Parses VehiclePosition, TripUpdate, Alert messages
- Writes normalized JSON to Redis
- Runs in dedicated Docker container

### Persistence Layer

**PostgreSQL** (via Doctrine ORM)
- Static GTFS data: `route`, `stop`, `trip`, `stop_time` tables
- Relationships via foreign keys
- Indexed by `gtfs_id` for fast lookups

**Redis** (via Predis client)
- Transient realtime data (vehicles, trips, alerts, scores)
- Key-value store with hash structures
- 24-hour TTL on feedback counters

### Processing Layer

**VehicleGrouper** (`src/Service/Headway/VehicleGrouper.php`)
- Groups vehicles by `(route_id, direction)`
- Filters out vehicles missing critical data

**HeadwayCalculator** (`src/Service/Headway/HeadwayCalculator.php`)
- Computes observed headway using 3-tier fallback:
  1. TripUpdate predicted arrivals (preferred)
  2. Position interpolation with GPS coordinates
  3. Raw timestamp deltas (fallback)
- Returns median headway in seconds

**HeadwayScorer** (`src/Service/Headway/HeadwayScorer.php`)
- Orchestrates grouping + calculation
- Assigns A-F letter grades
- Writes scores back to Redis

**VehicleStatusService** (`src/Service/Realtime/VehicleStatusService.php`)
- Enriches vehicles with punctuality status
- Determines color, severity, and reason
- Integrates crowd feedback from Redis

**ArrivalPredictor** (`src/Service/Prediction/ArrivalPredictor.php`)
- Predicts vehicle arrival times at stops
- 3-tier fallback: TripUpdate → GPS interpolation → static schedule
- Calculates confidence levels (HIGH/MEDIUM/LOW)
- Enriches predictions with status + feedback

### API Layer

**StopPredictionController** (`src/Controller/StopPredictionController.php`)
- `GET /api/stops/{stopId}/predictions` - Arrival predictions with countdown timers

**RealtimeController** (`src/Controller/RealtimeController.php`)
- `GET /api/realtime` - HTTP snapshot
- `GET /api/realtime` (SSE) - Server-sent events stream

**ScoreController** (`src/Controller/ScoreController.php`)
- `GET /api/score` - Current headway grades

**VehicleFeedbackController** (`src/Controller/VehicleFeedbackController.php`)
- `POST /api/vehicle-feedback` - Submit vote
- `GET /api/vehicle-feedback/{id}` - Retrieve summary

## Data Flow Sequence

### 1. Static Data Load
```
User → GtfsLoadCommand → PostgreSQL
  ↓
Routes, Stops, Trips, StopTimes stored
```

### 2. Realtime Polling
```
GTFS-RT Feed → Python Sidecar → Redis
  ↓
mtw:vehicles, mtw:trips, mtw:alerts updated every ~30s
```

### 3. Headway Scoring
```
Scheduler (30s) → ScoreTickCommand
  ↓
RealtimeRepository.getVehicles()
  ↓
VehicleGrouper.group() → HeadwayCalculator.calculate()
  ↓
HeadwayScorer.score() → Redis (mtw:score)
```

### 4. Status Enrichment
```
Client → GET /api/realtime
  ↓
RealtimeSnapshotService.snapshot()
  ↓
VehicleStatusService.enrichSnapshot()
  ↓
JSON response with status objects
```

### 5. Arrival Predictions
```
Client → GET /api/stops/{stopId}/predictions
  ↓
ArrivalPredictor.predictArrivalsForStop()
  ↓
For each active vehicle:
  - Try TripUpdate predictions (HIGH confidence)
  - Fallback to GPS interpolation (MEDIUM)
  - Fallback to static schedule (LOW)
  ↓
Enrich with VehicleStatus + feedback
  ↓
Return sorted predictions with countdown timers
```

### 6. Feedback Submission
```
Client → POST /api/vehicle-feedback
  ↓
VehicleFeedbackRepository.recordVote()
  ↓
Redis (mtw:vehicle_feedback:{id}) incremented
  ↓
Summary returned
```

## Technology Stack

| Layer | Technology | Purpose |
|-------|------------|---------|
| Web Server | FrankenPHP + Nginx | HTTP/2, HTTP/3, TLS |
| Application | Symfony 7.3 | PHP framework |
| ORM | Doctrine | Database abstraction |
| Database | PostgreSQL 16 | Static GTFS storage |
| Cache | Redis 7 | Realtime data + feedback |
| Parser | Python 3 | Protobuf deserialization |
| Container | Docker Compose | Development environment |
| Scheduler | Supercronic | Cron job orchestration |

## Scaling Considerations

### Horizontal Scaling
- **Web tier**: Multiple FrankenPHP instances behind load balancer
- **Worker tier**: Scale Symfony Messenger consumers
- **Parser tier**: Multiple Python sidecars with different feeds

### Vertical Scaling
- **Database**: Increase PostgreSQL memory for large GTFS datasets
- **Redis**: Increase memory for more vehicles/feedback

### Performance Optimizations
- Bulk SQL inserts for `stop_times` table (~50k rows)
- Preloaded direction maps to avoid N+1 queries
- Redis pipelining for batch operations
- Immutable DTOs reduce memory allocations

## Failure Modes & Resilience

| Failure | Impact | Mitigation |
|---------|--------|------------|
| GTFS-RT feed down | Stale vehicle data | Python sidecar retries, Redis holds last known state |
| PostgreSQL down | Can't load static data | Accept existing data, queue loads |
| Redis down | No realtime/scores | Application degrades gracefully (returns null) |
| Trip ID mismatch | Position fallback fails | HeadwayCalculator uses timestamp fallback |
| Missing lat/lon | Interpolation impossible | Falls back to timestamp headways |

## Security

- No authentication currently (public data)
- HTTPS enforced via nginx reverse proxy
- No user-generated content (feedback is counters only)
- Input validation on all POST endpoints
- SQL injection prevented by Doctrine ORM

## Monitoring

Key metrics to track:

- **Realtime data freshness**: `time() - snapshot['ts']`
- **Score coverage**: Percentage of routes with valid grades
- **Status coverage**: Percentage of vehicles with non-null status
- **Feedback volume**: Votes per hour
- **Headway calculation method**: Position vs timestamp ratio

## Future Enhancements

- [ ] Historical headway tracking (time-series database)
- [ ] Anomaly detection (sudden delays, bunching)
- [ ] Predictive arrival times
- [ ] Push notifications for delays
- [ ] Web dashboard UI
- [ ] Vehicle leaderboard (most punctual, worst offender)
- [ ] Route streak counters
