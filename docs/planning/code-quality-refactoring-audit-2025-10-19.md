# Code Quality Refactoring Audit (2025-10-19)

## Scope & Method

- Reviewed every service under `src/Service/**` against the Phase 1–4 goals in `docs/planning/CODE_QUALITY_REFACTORING.md`.
- Focus areas: _no direct `EntityManager` usage in services_, _repository queries returning DTOs/value objects_, _elimination of array-shaped magic data_, _chart construction pushed into presets/builders_.
- Inspected for raw SQL, unchecked array access, or orchestration logic that belongs in repositories/DTOs.

## Summary Status

| Area / Service | Status | Key Findings | Recommended Next Steps |
| --- | --- | --- | --- |
| **Dashboard** |  |  |  |
| RoutePerformanceService | ✅ Aligned | Uses DTO-driven repositories (`RoutePerformanceHeatmapBucketDto`, `RoutePerformanceDailyDto`) and chart presets only. | Maintain. |
| WeatherAnalysisService | ✅ Aligned | All queries encapsulated in repositories; charts driven by DTOs/presets. | Maintain. |
| OverviewService | ⚠️ Partial | Still hydrates raw Redis arrays + entity collections; multiple aggregation loops in-service. | Extract snapshot/score adapters (`RealtimeSnapshotDto`, `RouteScoreDto`), push historical trend query into repository, add dedicated DTO for top/bottom performers. |
| InsightGeneratorService | ⚠️ Partial | Uses associative arrays for stats payloads; cache keys rely on `serialize`. | Introduce typed stats DTOs (`WinterOperationsStatsDto`, etc.) to improve safety and align with plan. |
| **History** |  |  |  |
| ArrivalLogger | ✅ Aligned | Pure DTO → entity mapping, repositories handle persistence. | Maintain. |
| PerformanceAggregator | ⚠️ Partial | Operates on entity instances but pulls large collections into PHP; calculations OK yet could benefit from dedicated `RoutePerformanceMetrics` value object returned by repository to reduce in-service loops. | Add aggregation query to repository returning DTO with counts/averages; service orchestrates only. |
| **Bunching** | ❌ Not Compliant | `BunchingDetector` issues: direct `EntityManager` access, raw SQL, entity lookups in service, magic arrays, no DTOs, synchronous flush per incident. | Create `ArrivalLogBunchingViewRepository` to expose window-function result DTO, inject `RouteRepository`/`StopRepository` to resolve relations, add `BunchingIncidentDto`; batch-save via repo. |
| **Prediction** | ⚠️ Partial | `ArrivalPredictor` mixes Redis payloads, repositories, and math; heavy associative-array usage, recursive calls. | Introduce adapters (`RealtimeSnapshotDto`, `TripUpdatePredictionDto`), move snapshot filtering to helper class, isolate scheduling queries in repository. |
| **Realtime** |  |  |  |
| RealtimeSnapshotService | ✅ Aligned | Delegates to repository + status service; no direct DB access. | Maintain. |
| VehicleStatusService | ⚠️ Partial | Consumes untyped snapshot arrays, returns mixed arrays; numerous magic keys and string literals. | Model snapshot data via DTOs (`VehicleSnapshotDto`, `StopPredictionDto`); return a typed `VehicleStatusCollection`. |
| HeuristicTrafficReasonProvider | ✅ Aligned | No persistence concerns; pure function with DTO input. | Maintain. |
| **Headway** | ✅ Aligned | Utility services without DB access; calculations wrapped in pure methods. | Maintain. |
| **Weather** | ⚠️ Partial | `WeatherService` persists entities directly; mapping logic fine but still constructs entities in service. | Delegate upsert to repository with DTO-to-entity translation helper; service should pass `WeatherObservationDto`. |
| **Proximity** | ✅ Aligned | Pure calculations. | Maintain. |
| **GtfsFeatureAdapter** | ⚠️ Partial | Adapter still exposes arrays and string constants; lacks typed output per plan. | Replace with typed feature DTOs (RouteFeatureDto, StopFeatureDto, etc.) and leverage enums for keys. |

Status legend: ✅ aligned, ⚠️ partial compliance (refactor backlog), ❌ not compliant with plan.

## Detailed Observations

### Dashboard Services
- **RoutePerformanceService** already reflects the refactor goals: repositories return DTOs, the service orchestrates chart presets only.
- **OverviewService** mixes Redis snapshot arrays, entity collections, and manual averaging. Calculations like `calculateTrendVsYesterday()` should move into repository layer or dedicated query services returning typed stats. Recommend introducing DTOs for realtime scores and delegating historical aggregates to repositories.
- **InsightGeneratorService** currently takes loose `array<string,mixed>` payloads. Converting these stats to immutable DTOs will ensure shared structure between chart + narrative generation and opens the door for PHPStan level bump.

### History & Bunching
- **ArrivalLogger** is compliant.
- **PerformanceAggregator** loops over ArrivalLog entities, calculates metrics locally, and saves per route. Moving the heavy-lifting into `ArrivalLogRepository` (SQL aggregations returning `RoutePerformanceMetricsDto`) would align with the plan and reduce PHP-level data churn.
- **BunchingDetector** is the largest gap: direct SQL, `getEntityManager()` usage, entity lookups, and per-row flushes. Needs a repository-driven approach with DTOs and batched persistence.

### Prediction & Realtime
- **ArrivalPredictor** still orchestrates predictions using associative arrays from Redis snapshots. Introduce typed adapters for snapshot vehicles/trips and extract repository helpers for recurring lookups to stay within plan guidelines.
- **VehicleStatusService** similarly manipulates arrays. Wrapping snapshot + status into DTOs or collections will meet the “replace array access with typed DTOs” objective.

### Weather
- **WeatherService** converts API payloads straight into entities. Plan calls for DTO/value objects at the service boundary; create `WeatherObservationDto`, have repository handle persistence/mapping.

### Shared Utilities
- **GtfsFeatureAdapter** normalises ArcGIS payloads but still returns scalars/arrays. Plan suggests replacing “magic strings” with enums/DTOs; consider creating feature DTOs or value objects.

## Recommended Roadmap

1. **BunchingDetector rewrite (High)** – move SQL + entity lookups into repositories, return typed DTOs, batch persist incidents. Unlocks removal of `getEntityManager()` from services.
2. **Realtime DTO layer (High)** – model snapshot/score payloads (`RealtimeSnapshotDto`, `RouteScoreDto`, `VehicleSnapshotDto`) and update `OverviewService`, `ArrivalPredictor`, `VehicleStatusService` to consume them.
3. **WeatherService DTO handoff (Medium)** – create DTO and repository upsert helper to remove direct entity mutation in service.
4. **Insight + Overview stats DTOs (Medium)** – convert array stats to typed objects; update caching keys (e.g., `md5(json_encode(statsDto))`).
5. **GtfsFeature typed adapter (Low)** – convert to DTO-based API, retire raw array access.

Document owner: _open_. Update this audit after each refactor milestone to keep Phase tracking in sync.
