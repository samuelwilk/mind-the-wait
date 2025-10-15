# Known Issues

## Bunching Detection Schedule Not Running (2025-10-15)

**Status:** Open
**Priority:** Medium
**Discovered:** 2025-10-15 at 1:00 AM MT

### Description

The `BunchingDetectionSchedule` is configured to run daily at 1:00 AM but is NOT dispatching messages to the scheduler.

### Expected Behavior

- Scheduler consumes from `scheduler_bunching_detection` transport
- At 1:00 AM daily, `BunchingDetectionMessage` should be dispatched
- Handler processes previous day's arrival logs for bunching incidents

### Actual Behavior

- Scheduler is consuming from the transport (visible in logs)
- No `BunchingDetectionMessage` received at 1:00 AM
- No bunching detection logs in CloudWatch

### Evidence

Production logs from 2025-10-15 01:00 AM show:
- ✅ `WeatherCollectionMessage` dispatched and processed
- ✅ `PerformanceAggregationMessage` dispatched and processed
- ❌ `BunchingDetectionMessage` NOT dispatched

### Working Schedules (for comparison)

These schedules work correctly:
- `WeatherCollectionSchedule` (cron: `*/5 * * * *`) → ✅ Working
- `PerformanceAggregationSchedule` (cron: `0 1 * * *`) → ✅ Working
- `InsightCacheWarmingSchedule` (cron: `0 2 * * *`) → ✅ Working
- `ScoreTickSchedule` (cron: `*/30 * * * * *`) → ✅ Working

### Files Involved

- `src/Scheduler/BunchingDetectionSchedule.php` - Schedule definition (cron: `0 1 * * *`)
- `src/Scheduler/BunchingDetectionMessage.php` - Message class
- `src/MessageHandler/BunchingDetectionMessageHandler.php` - Handler
- `docker/compose.yaml` - Scheduler command includes `scheduler_bunching_detection`

### Root Cause FOUND ✅

**Production scheduler command is missing the transport!**

`terraform/environments/prod/main.tf:240` - Scheduler command:
```hcl
command = ["php", "bin/console", "messenger:consume",
  "scheduler_score_tick",
  "scheduler_weather_collection",
  "scheduler_performance_aggregation",
  "scheduler_insight_cache_warming",  # ← Missing scheduler_bunching_detection!
  "-vv"]
```

### Fix Required

Update `terraform/environments/prod/main.tf` line 240 to include all 5 transports:
```hcl
command = ["php", "bin/console", "messenger:consume",
  "scheduler_score_tick",
  "scheduler_weather_collection",
  "scheduler_performance_aggregation",
  "scheduler_insight_cache_warming",
  "scheduler_bunching_detection",  # ← ADD THIS
  "-vv"]
```

Then redeploy:
```bash
cd terraform/environments/prod
terraform apply
# or trigger GitHub Actions deployment
```

### Workaround

Manual execution:
```bash
docker compose exec php bin/console app:detect:bunching --date=2025-10-14
```

### Related

- Performance aggregation works correctly (same cron time)
- Both schedules should run at 1:00 AM daily
- Order: Bunching detection → Performance aggregation → Insight cache (2 AM)
