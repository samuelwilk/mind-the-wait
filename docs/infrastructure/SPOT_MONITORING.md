# Fargate Spot Monitoring Guide

## Overview

As of the Fargate Spot migration, all ECS services run on Spot instances for 70% cost savings. This guide explains how to monitor for interruptions and handle them if needed.

## Expected Behavior

**Interruption Rate:** ~5% (very low - AWS typically gives Spot a high priority)

**Interruption Notice:** AWS provides 2-minute warning via ECS task metadata before terminating a Spot task

**Recovery:** ECS automatically launches a replacement task when interrupted

## Monitoring for Interruptions

### 1. CloudWatch Logs Search

Check for Spot interruption notices in the past 24-48 hours:

```bash
# Search all service logs for Spot interruptions
for service in php pyparser scheduler-high-freq scheduler-low-freq; do
  echo "=== Checking $service ==="
  aws logs filter-log-events \
    --log-group-name /ecs/mind-the-wait-prod \
    --log-stream-name-prefix "$service" \
    --filter-pattern "SIGTERM" \
    --start-time $(date -u -d '48 hours ago' +%s)000 \
    --profile mind-the-wait \
    --region ca-central-1 \
    | jq -r '.events[] | "\(.timestamp | strflocaltime("%Y-%m-%d %H:%M:%S")): \(.message)"'
done
```

### 2. ECS Service Events

Check ECS service events for task replacement patterns:

```bash
# View recent service events
aws ecs describe-services \
  --cluster mind-the-wait-prod \
  --services \
    mind-the-wait-prod-php \
    mind-the-wait-prod-pyparser \
    mind-the-wait-prod-scheduler-high-freq \
    mind-the-wait-prod-scheduler-low-freq \
  --profile mind-the-wait \
  --region ca-central-1 \
  | jq -r '.services[] | .serviceName as $svc | .events[0:10][] | "[\($svc)] \(.createdAt): \(.message)"'
```

### 3. CloudWatch Metrics

Monitor task start/stop frequency (high churn indicates interruptions):

```bash
# Check task replacement rate over past week
aws cloudwatch get-metric-statistics \
  --namespace AWS/ECS \
  --metric-name RunningTaskCount \
  --dimensions \
    Name=ClusterName,Value=mind-the-wait-prod \
    Name=ServiceName,Value=mind-the-wait-prod-php \
  --start-time $(date -u -d '7 days ago' +%Y-%m-%dT%H:%M:%S) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
  --period 3600 \
  --statistics Average,Minimum,Maximum \
  --profile mind-the-wait \
  --region ca-central-1
```

If `Minimum` frequently drops to 0, interruptions are occurring.

### 4. Audit Script

Use the audit script to check current Spot usage:

```bash
./scripts/audit-aws-resources.sh
```

Look for the line:
```
✓ Using Fargate Spot (4 tasks) - saving 70%
```

## What to Do If Interruptions Are High

If you see >10% interruption rate (unlikely):

### Option 1: Accept Interruptions (Recommended)

Spot interruptions are normal and ECS handles them gracefully. For background services (schedulers, pyparser), 30-second interruptions are acceptable.

**No action needed.**

### Option 2: Hybrid Approach

Keep Spot for low-priority services, switch back to on-demand for critical ones:

```hcl
# terraform/environments/prod/main.tf

module "ecs_service_php" {
  # ... other config ...
  use_spot = false  # Switch back to on-demand for web app
}

module "ecs_service_scheduler_high_freq" {
  # ... other config ...
  use_spot = false  # Switch back to on-demand for high-freq tasks
}

# Keep pyparser and scheduler-low-freq on Spot
```

**Cost impact:** +$7/month for PHP, +$4/month for high-freq scheduler

### Option 3: Increase Task Count

Run 2+ tasks to ensure one is always available during interruptions:

```hcl
module "ecs_service_php" {
  # ... other config ...
  desired_count = 2  # Was 1
  use_spot = true    # Keep Spot
}
```

**Cost impact:** 2x compute cost (but still 70% cheaper than on-demand single task)

### Option 4: Switch Back to On-Demand

If Spot is causing too many issues (unlikely):

```hcl
# Disable Spot for all services
module "ecs_service_php" {
  use_spot = false
}
# ... repeat for all services
```

**Cost impact:** +$25/month (back to ~$36/month compute)

## Graceful Handling (Future Enhancement)

To handle Spot interruptions more gracefully, consider implementing:

1. **SIGTERM Handler** - Catch the 2-minute warning signal and finish in-flight work
2. **Health Check Adjustments** - Extend grace period during interruptions
3. **Circuit Breaker** - Enable deployment circuit breaker to auto-rollback on failures

Example SIGTERM handler (PHP):
```php
// src/EventListener/ShutdownListener.php
pcntl_signal(SIGTERM, function() {
    echo "Received SIGTERM, gracefully shutting down...\n";
    // Finish current message processing
    // Close database connections
    // Exit cleanly
    exit(0);
});
```

## Migration Timeline

**Phase 2 (Current):** Fargate Spot migration
- Date: 2025-10-19
- Services: All 4 ECS services switched to Spot
- Monitoring: Check logs weekly for first month

**Phase 3 (Planned):** Schedule-based scaling (only if needed)
- Turn off services during off-hours (midnight - 5 AM CST)
- Additional $40/month savings

**Phase 4 (Planned):** Aurora Serverless v2 (only if needed)
- Auto-pause database during off-hours
- Additional $30/month savings

## Monitoring Schedule

**Week 1-2:** Check daily for interruptions
```bash
./scripts/audit-aws-resources.sh
# Check CloudWatch logs
```

**Month 1:** Check weekly
- Review ECS service events
- Check for unusual task churn

**Ongoing:** Monthly review
- Review AWS Cost Explorer
- Verify Spot savings are realized (~$25/month)
- Check for any service degradation

## Contact

If you notice issues:
1. Check this guide first
2. Review CloudWatch logs
3. Consider hybrid approach (Option 2 above)
4. Document findings for future reference
