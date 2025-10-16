# Scheduler Fix Documentation

## Problem

The weather collection scheduler stopped running hourly after 6 AM despite being configured to run every hour (`0 * * * *`).

### Root Cause

The production scheduler container consumes from 6 different Symfony Scheduler transports simultaneously:
```bash
messenger:consume scheduler_score_tick scheduler_weather_collection scheduler_performance_aggregation scheduler_insight_cache_warming scheduler_bunching_detection scheduler_arrival_logging
```

**High-frequency messages dominate CPU time:**
- `scheduler_score_tick`: Every 30 seconds (120 messages/hour)
- `scheduler_arrival_logging`: Every 2 minutes (30 messages/hour)

**Low-frequency messages get starved:**
- `scheduler_weather_collection`: Every hour (1 message/hour)
- `scheduler_performance_aggregation`: Daily at 1 AM (1 message/day)
- `scheduler_insight_cache_warming`: Daily at 2 AM (1 message/day)
- `scheduler_bunching_detection`: Daily at 1 AM (1 message/day)

The worker is constantly busy processing high-frequency messages, preventing Symfony Scheduler from checking and dispatching the low-frequency recurring schedules.

## Solution: Split Schedulers into Two Services

Create two separate ECS services to isolate high-frequency from low-frequency schedulers.

### Architecture

```
┌─────────────────────────────────────────┐
│  High-Frequency Scheduler               │
│  - score_tick (every 30s)               │
│  - arrival_logging (every 2min)         │
│  CPU: Always busy, but that's expected  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  Low-Frequency Scheduler                │
│  - weather_collection (hourly)          │
│  - performance_aggregation (daily 1 AM) │
│  - insight_cache_warming (daily 2 AM)   │
│  - bunching_detection (daily 1 AM)      │
│  CPU: Mostly idle, picks up schedules   │
└─────────────────────────────────────────┘
```

## Implementation

### 1. Update Terraform Configuration

**File:** `terraform/environments/prod/main.tf`

**Current (lines 218-267):**
```hcl
# ECS Service: Scheduler (Cron Jobs)
module "ecs_service_scheduler" {
  source = "../../modules/ecs-service"

  project_name             = local.project_name
  environment              = local.environment
  service_name             = "scheduler"
  cluster_id               = module.ecs_cluster.cluster_id
  cluster_name             = module.ecs_cluster.cluster_name
  task_execution_role_arn  = module.ecs_cluster.task_execution_role_arn
  task_role_arn            = module.ecs_cluster.task_role_arn
  task_security_group_id   = module.networking.ecs_tasks_security_group_id
  subnet_ids               = module.networking.public_subnet_ids
  cpu                      = var.scheduler_cpu
  memory                   = var.scheduler_memory
  desired_count            = 1

  container_definitions = jsonencode([{
    name      = "scheduler"
    image     = "${module.ecr.repository_urls["php"]}:latest"
    essential = true

    command = ["php", "bin/console", "messenger:consume", "scheduler_score_tick", "scheduler_weather_collection", "scheduler_performance_aggregation", "scheduler_insight_cache_warming", "scheduler_bunching_detection", "scheduler_arrival_logging", "-vv"]

    environment = [
      { name = "APP_ENV", value = "prod" },
      { name = "APP_SECRET", value = var.app_secret },
      { name = "DATABASE_URL", value = local.database_url },
      { name = "REDIS_URL", value = local.redis_url },
      { name = "MESSENGER_TRANSPORT_DSN", value = local.messenger_transport_dsn },
      { name = "OPENAI_API_KEY", value = var.openai_api_key },
      { name = "MTW_GTFS_STATIC_URL", value = var.gtfs_static_url },
      { name = "MTW_ARCGIS_ROUTE", value = var.arcgis_routes_url },
      { name = "MTW_ARCGIS_STOP", value = var.arcgis_stops_url },
      { name = "MTW_ARCGIS_TRIP", value = var.arcgis_trips_url },
      { name = "MTW_ARCGIS_STOP_TIME", value = var.arcgis_stop_times_url }
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = module.ecs_cluster.log_group_name
        "awslogs-region"        = var.aws_region
        "awslogs-stream-prefix" = "scheduler"
      }
    }
  }])

  tags = local.common_tags
}
```

**Replace with:**
```hcl
# ECS Service: High-Frequency Scheduler (score_tick, arrival_logging)
module "ecs_service_scheduler_high_freq" {
  source = "../../modules/ecs-service"

  project_name             = local.project_name
  environment              = local.environment
  service_name             = "scheduler-high-freq"
  cluster_id               = module.ecs_cluster.cluster_id
  cluster_name             = module.ecs_cluster.cluster_name
  task_execution_role_arn  = module.ecs_cluster.task_execution_role_arn
  task_role_arn            = module.ecs_cluster.task_role_arn
  task_security_group_id   = module.networking.ecs_tasks_security_group_id
  subnet_ids               = module.networking.public_subnet_ids
  cpu                      = var.scheduler_cpu
  memory                   = var.scheduler_memory
  desired_count            = 1

  container_definitions = jsonencode([{
    name      = "scheduler-high-freq"
    image     = "${module.ecr.repository_urls["php"]}:latest"
    essential = true

    command = ["php", "bin/console", "messenger:consume", "scheduler_score_tick", "scheduler_arrival_logging", "-vv"]

    environment = [
      { name = "APP_ENV", value = "prod" },
      { name = "APP_SECRET", value = var.app_secret },
      { name = "DATABASE_URL", value = local.database_url },
      { name = "REDIS_URL", value = local.redis_url },
      { name = "MESSENGER_TRANSPORT_DSN", value = local.messenger_transport_dsn },
      { name = "MTW_GTFS_STATIC_URL", value = var.gtfs_static_url },
      { name = "MTW_ARCGIS_ROUTE", value = var.arcgis_routes_url },
      { name = "MTW_ARCGIS_STOP", value = var.arcgis_stops_url },
      { name = "MTW_ARCGIS_TRIP", value = var.arcgis_trips_url },
      { name = "MTW_ARCGIS_STOP_TIME", value = var.arcgis_stop_times_url }
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = module.ecs_cluster.log_group_name
        "awslogs-region"        = var.aws_region
        "awslogs-stream-prefix" = "scheduler-high-freq"
      }
    }
  }])

  tags = local.common_tags
}

# ECS Service: Low-Frequency Scheduler (weather, aggregation, insights, bunching)
module "ecs_service_scheduler_low_freq" {
  source = "../../modules/ecs-service"

  project_name             = local.project_name
  environment              = local.environment
  service_name             = "scheduler-low-freq"
  cluster_id               = module.ecs_cluster.cluster_id
  cluster_name             = module.ecs_cluster.cluster_name
  task_execution_role_arn  = module.ecs_cluster.task_execution_role_arn
  task_role_arn            = module.ecs_cluster.task_role_arn
  task_security_group_id   = module.networking.ecs_tasks_security_group_id
  subnet_ids               = module.networking.public_subnet_ids
  cpu                      = 256  # Lower CPU for low-frequency tasks
  memory                   = 512  # Lower memory for low-frequency tasks
  desired_count            = 1

  container_definitions = jsonencode([{
    name      = "scheduler-low-freq"
    image     = "${module.ecr.repository_urls["php"]}:latest"
    essential = true

    command = ["php", "bin/console", "messenger:consume", "scheduler_weather_collection", "scheduler_performance_aggregation", "scheduler_insight_cache_warming", "scheduler_bunching_detection", "-vv"]

    environment = [
      { name = "APP_ENV", value = "prod" },
      { name = "APP_SECRET", value = var.app_secret },
      { name = "DATABASE_URL", value = local.database_url },
      { name = "REDIS_URL", value = local.redis_url },
      { name = "MESSENGER_TRANSPORT_DSN", value = local.messenger_transport_dsn },
      { name = "OPENAI_API_KEY", value = var.openai_api_key },
      { name = "MTW_GTFS_STATIC_URL", value = var.gtfs_static_url },
      { name = "MTW_ARCGIS_ROUTE", value = var.arcgis_routes_url },
      { name = "MTW_ARCGIS_STOP", value = var.arcgis_stops_url },
      { name = "MTW_ARCGIS_TRIP", value = var.arcgis_trips_url },
      { name = "MTW_ARCGIS_STOP_TIME", value = var.arcgis_stop_times_url }
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = module.ecs_cluster.log_group_name
        "awslogs-region"        = var.aws_region
        "awslogs-stream-prefix" = "scheduler-low-freq"
      }
    }
  }])

  tags = local.common_tags
}
```

### 2. Update Terraform Variables (Optional)

**File:** `terraform/environments/prod/variables.tf`

Add new variables for the low-frequency scheduler (optional, only if you want different defaults):

```hcl
variable "scheduler_low_freq_cpu" {
  description = "CPU units for low-frequency scheduler"
  type        = number
  default     = 256
}

variable "scheduler_low_freq_memory" {
  description = "Memory (MB) for low-frequency scheduler"
  type        = number
  default     = 512
}
```

### 3. Update Local Docker Compose (Optional)

**File:** `docker/compose.yaml`

For local development consistency, you can split the scheduler:

**Current:**
```yaml
  scheduler:
    build:
      context: ..
      dockerfile: docker/dev/Dockerfile
    depends_on:
      - php
      - redis
      - database
    working_dir: /var/www/app
    command: php bin/console messenger:consume scheduler_score_tick scheduler_weather_collection scheduler_performance_aggregation scheduler_insight_cache_warming scheduler_bunching_detection scheduler_arrival_logging -vv
    volumes:
      - ../:/var/www/app:cached
```

**Replace with:**
```yaml
  scheduler-high-freq:
    build:
      context: ..
      dockerfile: docker/dev/Dockerfile
    depends_on:
      - php
      - redis
      - database
    working_dir: /var/www/app
    command: php bin/console messenger:consume scheduler_score_tick scheduler_arrival_logging -vv
    volumes:
      - ../:/var/www/app:cached

  scheduler-low-freq:
    build:
      context: ..
      dockerfile: docker/dev/Dockerfile
    depends_on:
      - php
      - redis
      - database
    working_dir: /var/www/app
    command: php bin/console messenger:consume scheduler_weather_collection scheduler_performance_aggregation scheduler_insight_cache_warming scheduler_bunching_detection -vv
    volumes:
      - ../:/var/www/app:cached
```

## Cost Analysis

### Current Cost (1 Scheduler Service)
- **CPU**: 512 (0.5 vCPU)
- **Memory**: 1024 MB (1 GB)
- **Fargate Pricing (ca-central-1)**:
  - vCPU: $0.04656/hour
  - Memory: $0.00511/GB/hour
- **Monthly Cost**:
  - CPU: 0.5 × $0.04656 × 730 hours = **$16.99/month**
  - Memory: 1.0 × $0.00511 × 730 hours = **$3.73/month**
  - **Total**: **$20.72/month**

### New Cost (2 Scheduler Services)

**High-Frequency Scheduler:**
- **CPU**: 512 (0.5 vCPU) - same as before
- **Memory**: 1024 MB (1 GB) - same as before
- **Monthly Cost**:
  - CPU: 0.5 × $0.04656 × 730 hours = **$16.99/month**
  - Memory: 1.0 × $0.00511 × 730 hours = **$3.73/month**
  - **Subtotal**: **$20.72/month**

**Low-Frequency Scheduler:**
- **CPU**: 256 (0.25 vCPU) - reduced from 512
- **Memory**: 512 MB (0.5 GB) - reduced from 1024
- **Monthly Cost**:
  - CPU: 0.25 × $0.04656 × 730 hours = **$8.50/month**
  - Memory: 0.5 × $0.00511 × 730 hours = **$1.87/month**
  - **Subtotal**: **$10.37/month**

### Cost Impact Summary

| Component | Current | New | Change |
|-----------|---------|-----|--------|
| High-Freq Scheduler | $20.72 | $20.72 | $0.00 |
| Low-Freq Scheduler | - | $10.37 | +$10.37 |
| **Total** | **$20.72/month** | **$31.09/month** | **+$10.37/month** |

**Additional Monthly Cost: $10.37 (~50% increase)**

### Cost Optimization Options

If you want to minimize cost increase:

1. **Option A: Reduce high-freq resources** (if current usage is low)
   - High-freq: CPU 256, Memory 512 MB → **$10.37/month**
   - Low-freq: CPU 256, Memory 512 MB → **$10.37/month**
   - **Total**: **$20.74/month** (essentially same as current)

2. **Option B: Further reduce low-freq resources**
   - Low-freq: CPU 256, Memory 256 MB → **$9.43/month**
   - **Savings**: $0.94/month

3. **Option C: Use Fargate Spot** (70% savings, but may be interrupted)
   - Not recommended for critical schedulers

## Deployment Steps

1. **Test locally first:**
   ```bash
   docker compose down
   # Update docker/compose.yaml with split schedulers
   docker compose up -d
   # Verify both schedulers are working
   docker compose logs scheduler-high-freq -f
   docker compose logs scheduler-low-freq -f
   ```

2. **Apply Terraform changes:**
   ```bash
   cd terraform/environments/prod
   terraform plan
   # Review the plan - should show:
   # - Delete: ecs_service_scheduler
   # - Create: ecs_service_scheduler_high_freq
   # - Create: ecs_service_scheduler_low_freq
   terraform apply
   ```

3. **Monitor deployment:**
   ```bash
   # Check services are running
   aws ecs list-services --cluster mind-the-wait-prod --region ca-central-1 --profile mind-the-wait

   # Check logs
   aws logs tail /ecs/mind-the-wait-prod --region ca-central-1 --profile mind-the-wait --follow
   ```

4. **Verify weather collection:**
   - Wait for next hour
   - Check: `https://mind-the-wait.ca/api/database-stats`
   - Verify `latest_weather.observed_at` updates hourly

## Rollback Plan

If something goes wrong:

```bash
cd terraform/environments/prod
# Revert main.tf to single scheduler
git checkout HEAD~1 terraform/environments/prod/main.tf
terraform apply
```

Or manually via AWS Console:
1. Stop both new scheduler services
2. Redeploy the original scheduler service from previous task definition

## Benefits

✅ **Reliability**: Low-frequency schedules no longer get starved
✅ **Predictability**: Weather updates reliably every hour
✅ **Observability**: Separate log streams for high-freq vs low-freq
✅ **Scalability**: Can tune resources independently
✅ **Debugging**: Easier to troubleshoot specific scheduler issues

## Trade-offs

❌ **Cost**: +$10.37/month (~$125/year)
⚠️ **Complexity**: 2 services instead of 1 to manage
⚠️ **Terraform state**: Requires destroy + recreate of scheduler service

## Alternative Solutions Considered

1. **Increase worker sleep time** - Doesn't fix root cause
2. **Use `--time-limit` on workers** - Causes constant restarts
3. **Configure Symfony Scheduler polling** - No such configuration exists
4. **Single worker with better prioritization** - Not supported by Messenger

## Recommendation

**Implement the split scheduler solution.** The $10/month cost is justified by:
- Preventing 2-4 hours of stale weather data daily
- Ensuring daily performance aggregation runs reliably
- Avoiding manual intervention and restarts

The architecture is cleaner and more maintainable long-term.
