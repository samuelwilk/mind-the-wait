# ECS Auto-Scaling Configuration Guide

This guide explains how to enable, disable, and configure auto-scaling for the PHP web service in the mind-the-wait production environment.

## Current Configuration

**Status:** Auto-scaling is **DISABLED** for production

**Rationale:**
- Low, predictable traffic patterns in production
- Auto-scaling adds operational complexity without benefit
- Fixed task count (1) provides stable, cost-effective operation
- Eliminates false-positive CloudWatch alarms (`AlarmLow` staying in "In alarm" state)

**Services:**
- **PHP Web Service:** Fixed at 1 task (auto-scaling disabled)
- **PyParser:** Fixed at 1 task (no auto-scaling)
- **Scheduler High-Freq:** Fixed at 1 task (no auto-scaling)
- **Scheduler Low-Freq:** Fixed at 1 task (no auto-scaling)

---

## When to Enable Auto-Scaling

Consider enabling auto-scaling when you observe:

1. **Sustained high CPU utilization:**
   - Average CPU > 70% for 10+ minutes during peak hours
   - Check CloudWatch: `ECSServiceAverageCPUUtilization` metric

2. **Response time degradation:**
   - API response times > 2 seconds
   - ALB target response time metrics show degradation

3. **Memory pressure:**
   - Memory utilization consistently > 80%
   - OOM (Out of Memory) errors in CloudWatch logs

4. **Traffic growth:**
   - Expecting significant traffic increase (marketing campaign, feature launch)
   - 2x+ increase in requests per minute

---

## How to Enable Auto-Scaling

### Step 1: Configure Auto-Scaling Parameters

Edit `terraform/environments/prod/terraform.tfvars`:

```hcl
# Auto-Scaling - ENABLED for high-traffic operation
php_min_capacity = 1     # Minimum tasks (always at least 1)
php_max_capacity = 3     # Maximum tasks (scale up to 3)
php_cpu_target   = 70    # Target CPU % (scale up above 70%, scale down below 63%)
```

**Parameter Guidelines:**

| Parameter | Recommended Value | Notes |
|-----------|------------------|-------|
| `php_min_capacity` | `1` | Never set to 0 - service must always run |
| `php_max_capacity` | `2-3` | Conservative for budget config (each task costs ~$12/month) |
| `php_cpu_target` | `70` | Industry standard, balances responsiveness vs. cost |

**Cost Impact:**
- Each additional task costs ~$12/month (Fargate Spot)
- Max capacity of 3 = potential $36/month vs. $12/month fixed (1 task)
- Auto-scaling will scale down during low traffic to save costs

### Step 2: Apply Terraform Changes

```bash
cd terraform/environments/prod

# Preview changes
terraform plan

# Expected output:
# Plan: 2 to add, 1 to change, 0 to destroy.
#
# + aws_appautoscaling_target.this[0]
# + aws_appautoscaling_policy.cpu[0]
# ~ aws_ecs_service.this (desired_count ignored by lifecycle)

# Apply changes
terraform apply
```

### Step 3: Verify Auto-Scaling Configuration

```bash
# Check auto-scaling target
aws application-autoscaling describe-scalable-targets \
  --service-namespace ecs \
  --resource-ids service/mind-the-wait-prod/mind-the-wait-prod-php

# Expected output:
# ScalableTargets:
#   - MinCapacity: 1
#     MaxCapacity: 3
#     RoleARN: arn:aws:iam::...

# Check scaling policy
aws application-autoscaling describe-scaling-policies \
  --service-namespace ecs \
  --resource-id service/mind-the-wait-prod/mind-the-wait-prod-php

# Expected output:
# ScalingPolicies:
#   - PolicyName: mind-the-wait-prod-php-cpu-autoscaling
#     TargetTrackingScalingPolicyConfiguration:
#       TargetValue: 70.0
#       PredefinedMetricSpecification:
#         PredefinedMetricType: ECSServiceAverageCPUUtilization
```

### Step 4: Monitor CloudWatch Alarms

Two new alarms will be created automatically:

1. **TargetTracking-AlarmHigh** (Scale Up)
   - Triggers when: `CPUUtilization > 70%` for 3 consecutive minutes
   - Action: Increase desired count by 1 task
   - Max tasks: Limited by `php_max_capacity`

2. **TargetTracking-AlarmLow** (Scale Down)
   - Triggers when: `CPUUtilization < 63%` (90% of target) for 15 consecutive minutes
   - Action: Decrease desired count by 1 task
   - Min tasks: Limited by `php_min_capacity` (never scales below this)

**Important:**
- `AlarmLow` will often be in "In alarm" state during low-traffic periods
- This is **expected behavior** - it means auto-scaling wants to scale down but is already at minimum
- Do NOT set `php_min_capacity = 0` - service must always have at least 1 task running

---

## How to Disable Auto-Scaling

### Step 1: Comment Out Auto-Scaling Parameters

Edit `terraform/environments/prod/terraform.tfvars`:

```hcl
# Auto-Scaling - DISABLED for low-traffic production
# Auto-scaling adds complexity without benefit for predictable low traffic
# Enable by uncommenting and setting min_capacity when needed
# php_min_capacity = 1
# php_max_capacity = 2
# php_cpu_target   = 75
```

### Step 2: Apply Terraform Changes

```bash
cd terraform/environments/prod
terraform plan
# Expected: Plan: 0 to add, 0 to change, 2 to destroy.
# - aws_appautoscaling_target.this[0]
# - aws_appautoscaling_policy.cpu[0]

terraform apply
```

### Step 3: Verify Auto-Scaling Removed

```bash
# Should return empty results
aws application-autoscaling describe-scalable-targets \
  --service-namespace ecs \
  --resource-ids service/mind-the-wait-prod/mind-the-wait-prod-php

# CloudWatch alarms should be deleted automatically
# TargetTracking-AlarmHigh → deleted
# TargetTracking-AlarmLow → deleted
```

---

## Auto-Scaling Behavior

### Scale-Up Triggers

Auto-scaling will **increase task count** when:
- CPU utilization > 70% for 3 consecutive minutes
- Each scale-up adds 1 task
- Maximum tasks limited by `php_max_capacity`

**Timeline:**
```
00:00 - CPU spikes to 80%
00:03 - AlarmHigh triggers (3 minutes above threshold)
00:03 - ECS starts new task (takes ~90 seconds to launch)
00:04 - New task becomes healthy and receives traffic
00:05 - CPU drops to ~50% (2 tasks sharing load)
```

### Scale-Down Triggers

Auto-scaling will **decrease task count** when:
- CPU utilization < 63% (90% of 70% target) for 15 consecutive minutes
- Each scale-down removes 1 task
- Minimum tasks limited by `php_min_capacity`

**Timeline:**
```
00:00 - CPU drops to 50% (low traffic period)
00:15 - AlarmLow triggers (15 minutes below threshold)
00:15 - ECS stops 1 task (graceful shutdown)
00:16 - Remaining tasks absorb traffic
```

**Why 15 minutes for scale-down?**
- Prevents "flapping" (rapid scale up/down cycles)
- Allows time for traffic patterns to stabilize
- Reduces unnecessary task churn

---

## Monitoring Auto-Scaling

### CloudWatch Metrics

**Key Metrics to Monitor:**
- `ECSServiceAverageCPUUtilization` - Average CPU across all tasks
- `ECSServiceDesiredTaskCount` - Number of tasks auto-scaling wants
- `ECSServiceRunningTaskCount` - Number of tasks actually running
- `TargetResponseTime` - ALB response time (should stay < 2s)

**CloudWatch Dashboard Query:**
```
fields @timestamp, ServiceName, DesiredCount, RunningTaskCount, PendingTaskCount
| filter ServiceName = "mind-the-wait-prod-php"
| sort @timestamp desc
| limit 100
```

### Scaling Activity

View recent scaling activities:

```bash
aws application-autoscaling describe-scaling-activities \
  --service-namespace ecs \
  --resource-id service/mind-the-wait-prod/mind-the-wait-prod-php \
  --max-results 20
```

Example output:
```json
{
  "ActivityId": "...",
  "Description": "Setting desired count to 2.",
  "Cause": "monitor alarm TargetTracking-AlarmHigh in state ALARM triggered policy",
  "StartTime": "2025-10-21T14:35:00Z",
  "StatusCode": "Successful"
}
```

---

## Cost Implications

### Without Auto-Scaling (Current)

**Fixed Cost:** 1 task × 24 hours/day × 30 days = 720 task-hours/month
- Cost: ~$12/month (Fargate Spot)

### With Auto-Scaling (max_capacity = 3)

**Variable Cost:** Depends on traffic patterns

**Best Case (low traffic):**
- Average: 1 task for 90% of month + 2 tasks for 10% of month
- Cost: ~$15/month (25% increase)

**Worst Case (sustained high traffic):**
- Average: 3 tasks for 50% of month + 1 task for 50% of month
- Cost: ~$24/month (100% increase)

**Recommendation:**
- Use fixed capacity (no auto-scaling) for predictable, low-traffic apps
- Use auto-scaling when traffic is unpredictable or has clear peaks
- Monitor actual scaling activity to optimize `min_capacity` and `max_capacity`

---

## Troubleshooting

### Auto-Scaling Not Triggering

**Problem:** CPU > 70% but no scale-up

**Solutions:**
1. Check alarm state:
   ```bash
   aws cloudwatch describe-alarms --alarm-names \
     $(aws cloudwatch describe-alarms --query 'MetricAlarms[?contains(AlarmName, `TargetTracking`)].AlarmName' --output text)
   ```

2. Verify metric is being collected:
   ```bash
   aws cloudwatch get-metric-statistics \
     --namespace AWS/ECS \
     --metric-name CPUUtilization \
     --dimensions Name=ServiceName,Value=mind-the-wait-prod-php \
                  Name=ClusterName,Value=mind-the-wait-prod \
     --start-time $(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%S) \
     --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
     --period 60 \
     --statistics Average
   ```

3. Check IAM permissions:
   - ECS service needs `application-autoscaling:*` permissions
   - Task execution role should have standard ECS permissions

### AlarmLow Always in "In alarm" State

**Problem:** `TargetTracking-AlarmLow` shows red "In alarm" status

**Explanation:** This is **expected behavior** for low-traffic apps
- Alarm triggers when CPU < 63% (90% of 70% target)
- Auto-scaling wants to scale down but already at minimum (`php_min_capacity = 1`)
- Alarm stays in "In alarm" state because condition persists

**Solutions:**
1. **Disable auto-scaling** (recommended for low-traffic apps) - see above
2. **Lower CPU target** to match actual usage: `php_cpu_target = 10`
3. **Ignore the alarm** - it's not a problem, just informational

### Too Many Scale Up/Down Events

**Problem:** Service scales up and down every few minutes (flapping)

**Solutions:**
1. Increase CPU target: `php_cpu_target = 80` (more headroom before scale-up)
2. Increase max capacity: `php_max_capacity = 4` (less aggressive scaling)
3. Use custom scaling policy with longer cooldown periods

---

## Advanced Configuration

### Custom Scaling Policy

For more control, replace target tracking with step scaling:

```hcl
# In terraform/modules/ecs-service/main.tf

resource "aws_appautoscaling_policy" "cpu_step" {
  name               = "${var.project_name}-${var.environment}-${var.service_name}-cpu-step"
  policy_type        = "StepScaling"
  resource_id        = aws_appautoscaling_target.this[0].resource_id
  scalable_dimension = aws_appautoscaling_target.this[0].scalable_dimension
  service_namespace  = aws_appautoscaling_target.this[0].service_namespace

  step_scaling_policy_configuration {
    adjustment_type         = "ChangeInCapacity"
    cooldown                = 300  # 5 minutes
    metric_aggregation_type = "Average"

    step_adjustment {
      metric_interval_lower_bound = 0
      metric_interval_upper_bound = 20
      scaling_adjustment          = 1  # Add 1 task if CPU 70-90%
    }

    step_adjustment {
      metric_interval_lower_bound = 20
      scaling_adjustment          = 2  # Add 2 tasks if CPU > 90%
    }
  }
}
```

### Memory-Based Auto-Scaling

Add memory-based scaling in addition to CPU:

```hcl
resource "aws_appautoscaling_policy" "memory" {
  count = var.min_capacity != null ? 1 : 0

  name               = "${var.project_name}-${var.environment}-${var.service_name}-memory-autoscaling"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.this[0].resource_id
  scalable_dimension = aws_appautoscaling_target.this[0].scalable_dimension
  service_namespace  = aws_appautoscaling_target.this[0].service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageMemoryUtilization"
    }

    target_value = 80  # Target 80% memory utilization
  }
}
```

---

## References

- [AWS ECS Auto Scaling Documentation](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/service-auto-scaling.html)
- [Target Tracking Scaling Policies](https://docs.aws.amazon.com/autoscaling/application/userguide/application-auto-scaling-target-tracking.html)
- [Fargate Spot Pricing](https://aws.amazon.com/fargate/pricing/)
- Project Docs: `docs/infrastructure/AWS_COST_OPTIMIZATION.md`
- Project Docs: `docs/infrastructure/budget-optimized-config.md`
