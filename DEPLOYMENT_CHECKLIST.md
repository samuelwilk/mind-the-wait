# Scheduler Split Deployment Checklist

**Date:** 2025-10-16
**Purpose:** Deploy split scheduler architecture to fix weather collection reliability issue
**Expected Duration:** 10-15 minutes
**Cost Impact:** +$10.37/month

## Pre-Deployment Status

✅ **Local Testing:** Split schedulers tested and verified working locally
✅ **Code Changes:** All changes committed (docker-compose.yaml, terraform configs)
✅ **Documentation:** SCHEDULER_FIX.md contains full technical details

**Current Production Issue:**
- Weather collection stopped at 6 AM, hasn't run since
- Root cause: ArrivalLoggingMessage (10-15 min processing time) blocks Symfony Scheduler from checking hourly triggers
- Quick restart applied at 8:40 AM, but issue will recur

## Deployment Steps

### 1. Verify Current State

```bash
# Check current scheduler status
aws ecs describe-services --cluster mind-the-wait-prod \
  --services mind-the-wait-prod-scheduler \
  --region ca-central-1 --profile mind-the-wait \
  --query 'services[0].[serviceName,runningCount,desiredCount]'

# Check latest weather update
curl -s https://mind-the-wait.ca/api/database-stats | jq '.latest_weather'
```

**Expected:** Weather still showing 6:00 AM observation (stale)

### 2. Navigate to Terraform Directory

```bash
cd terraform/environments/prod
```

### 3. Initialize/Validate Terraform (if needed)

```bash
terraform init
terraform validate
```

### 4. Review Terraform Plan

```bash
terraform plan
```

**Expected changes:**
- ❌ **Destroy:** `module.ecs_service_scheduler` (old single scheduler)
- ✅ **Create:** `module.ecs_service_scheduler_high_freq` (score_tick, arrival_logging)
- ✅ **Create:** `module.ecs_service_scheduler_low_freq` (weather, aggregation, insights, bunching)
- 📝 **Update:** outputs (service names)

**Review checklist:**
- [ ] 1 service being destroyed (scheduler)
- [ ] 2 services being created (scheduler-high-freq, scheduler-low-freq)
- [ ] No changes to php, pyparser, database, redis, networking
- [ ] Low-freq scheduler has CPU=256, Memory=512 (reduced resources)

### 5. Apply Terraform Changes

```bash
terraform apply
```

Type `yes` when prompted.

**Timeline:**
- Service destruction: ~30 seconds
- Service creation: ~2-3 minutes (pulling images, starting tasks)
- **Total:** ~3-5 minutes

**What's happening:**
1. Old scheduler service stopped and removed
2. High-frequency scheduler service created and started
3. Low-frequency scheduler service created and started
4. Both services pull latest PHP image from ECR
5. Tasks start and begin consuming from their respective transports

### 6. Monitor Deployment

**Check service status:**
```bash
# List all services
aws ecs list-services --cluster mind-the-wait-prod \
  --region ca-central-1 --profile mind-the-wait

# Should see: scheduler-high-freq, scheduler-low-freq (NOT scheduler)
```

**Check tasks are running:**
```bash
# High-freq scheduler
aws ecs list-tasks --cluster mind-the-wait-prod \
  --service-name mind-the-wait-prod-scheduler-high-freq \
  --region ca-central-1 --profile mind-the-wait

# Low-freq scheduler
aws ecs list-tasks --cluster mind-the-wait-prod \
  --service-name mind-the-wait-prod-scheduler-low-freq \
  --region ca-central-1 --profile mind-the-wait
```

**Expected:** Each service has 1 running task

### 7. Verify Logs

**High-frequency scheduler (should see messages immediately):**
```bash
aws logs tail /ecs/mind-the-wait-prod --region ca-central-1 \
  --profile mind-the-wait --follow | grep "scheduler-high-freq"
```

**Expected:**
- `Consuming messages from transports "scheduler_score_tick, scheduler_arrival_logging"`
- `Received message App\Scheduler\ScoreTickMessage` (within 30 seconds)
- `Received message App\Scheduler\ArrivalLoggingMessage` (within 2 minutes)

**Low-frequency scheduler:**
```bash
aws logs tail /ecs/mind-the-wait-prod --region ca-central-1 \
  --profile mind-the-wait --follow | grep "scheduler-low-freq"
```

**Expected:**
- `Consuming messages from transports "scheduler_weather_collection, scheduler_performance_aggregation, ..."`
- No messages yet (weather runs hourly at top of hour)

### 8. Wait for Next Weather Collection

**When:** Next full hour (e.g., 10:00 AM if deployed at 9:30 AM)

**Monitor:**
```bash
# Watch for weather collection
aws logs tail /ecs/mind-the-wait-prod --region ca-central-1 \
  --profile mind-the-wait --since 5m | grep -i weather
```

**Expected at 10:00 AM:**
```
10:00:00 INFO [app] Starting scheduled weather collection
10:00:03 INFO [app] Scheduled weather collection completed ["temperature" => "X","condition" => "..."]
```

**Verify via API:**
```bash
curl -s https://mind-the-wait.ca/api/database-stats | jq '.latest_weather'
```

**Expected:** `observed_at` shows current hour (e.g., "2025-10-16 10:00:00")

## Success Criteria

- [ ] Both scheduler services running (1 task each)
- [ ] High-freq scheduler processing ScoreTickMessage every 30s
- [ ] High-freq scheduler processing ArrivalLoggingMessage every 2min
- [ ] Low-freq scheduler idle (waiting for hourly trigger)
- [ ] Weather collection runs at next hour (10:00 AM)
- [ ] API database-stats shows updated weather timestamp
- [ ] Live weather banner shows "Last updated: [current hour]"

## Rollback Plan

If deployment fails or weather collection doesn't work:

### Option A: Terraform Rollback

```bash
cd terraform/environments/prod

# Revert main.tf and outputs.tf
git checkout HEAD~1 terraform/environments/prod/main.tf
git checkout HEAD~1 terraform/environments/prod/outputs.tf

# Re-apply
terraform apply
```

### Option B: Manual AWS Console

1. Go to ECS Console → mind-the-wait-prod cluster
2. Stop both new scheduler services
3. Create new service using previous task definition (mind-the-wait-prod-scheduler)

## Post-Deployment Monitoring

### First Hour (Immediate)
- [ ] Both schedulers remain running (no crashes)
- [ ] High-freq continues processing messages
- [ ] No ERROR logs in either scheduler

### First 24 Hours
- [ ] Weather updates hourly (check at 11 AM, 12 PM, 1 PM, etc.)
- [ ] Performance aggregation runs at 1:00 AM (next night)
- [ ] Insight cache warming runs at 2:00 AM (next night)
- [ ] ArrivalLogging completes without blocking weather

### Check Commands

**Service health:**
```bash
aws ecs describe-services --cluster mind-the-wait-prod \
  --services mind-the-wait-prod-scheduler-high-freq mind-the-wait-prod-scheduler-low-freq \
  --region ca-central-1 --profile mind-the-wait \
  --query 'services[*].[serviceName,runningCount,desiredCount]'
```

**Recent weather observations:**
```bash
curl -s https://mind-the-wait.ca/api/database-stats | jq '.latest_weather'
```

**Check for errors:**
```bash
aws logs filter-log-events --log-group-name /ecs/mind-the-wait-prod \
  --region ca-central-1 --profile mind-the-wait \
  --start-time $(date -u -d '1 hour ago' +%s)000 \
  --filter-pattern "?ERROR ?CRITICAL" \
  --query 'events[*].message' --output text
```

## Cost Tracking

**Before:** $20.72/month (1 scheduler @ 512 CPU, 1024 MB)
**After:** $31.09/month (2 schedulers: 512+256 CPU, 1024+512 MB)
**Increase:** +$10.37/month (~$125/year)

**Monitor costs:**
1. AWS Console → Cost Explorer
2. Filter: Service = ECS
3. Compare October vs November spend

## Notes

- **No data loss:** All existing data preserved during deployment
- **Brief downtime:** Schedulers will miss 30-60 seconds of messages during switchover
- **Weather gap:** Will still have 6 AM - deployment hour gap (acceptable one-time loss)
- **Future prevention:** Split architecture prevents recurrence of blocking issue

## Completion

Date completed: _____________
Deployed by: _____________
Weather collection verified at: _____________ (hour)
Any issues: _____________

---

**Reference Documentation:**
- Technical details: `SCHEDULER_FIX.md`
- Root cause analysis: SCHEDULER_FIX.md section "Problem"
- Architecture diagram: SCHEDULER_FIX.md section "Architecture"
