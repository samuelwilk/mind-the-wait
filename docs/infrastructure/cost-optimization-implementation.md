# AWS Cost Optimization Implementation Plan

## Objectives

- Bring active-development spend below **$8/day** by cutting always-on capacity and moving bursty work to on-demand execution.
- Preserve a clear path back to the “launch-ready” footprint with feature flags in Terraform.
- Add guardrails (budgets, anomaly alerts, runbooks) so future cost drift is caught within 24 hours.

## Baseline & Guardrails

1. **Confirm current inventory**
   - Run `terraform state list` in `terraform/environments/prod` to track managed resources.
   - Export the past 14 days of costs from AWS Cost Explorer, grouped by `Service` and the `Project=Mind-the-wait` tag.
   - Record baseline values in `docs/infrastructure/aws-cost-estimation.md`.
2. **Tighten budgets and alerts**
   - Adjust the existing monthly AWS Budget down to `$20` with alert thresholds at 40%, 60%, and 80% so spend spikes are caught early.
   - Enable Cost Anomaly Detection for the linked account if not already active.
   - Document alert recipients and escalation flow in `docs/infrastructure/security-checklist.md`.
3. **Decide on target modes**
   - Define three profiles: `launch` (current), `dev-low-traffic`, and `offline`.
   - Capture expectations (available endpoints, acceptable cold-starts) in `docs/infrastructure/budget-optimized-config.md`.

## Phase 1 – Fargate Savings (Target: 40% ECS reduction)

1. **Introduce capacity providers**
   - Update `modules/ecs-cluster` to create an `aws_ecs_capacity_provider` for `FARGATE` and `FARGATE_SPOT`.
   - Attach a cluster capacity provider strategy preferring spot (weight 4) with on-demand as fallback (weight 1).
2. **Refactor ECS services**
   - Replace `launch_type = "FARGATE"` in `modules/ecs-service/main.tf` with a `capacity_provider_strategy` block that consumes the cluster defaults.
   - Expose a Terraform variable to flip spot usage per-service in case critical traffic requires on-demand capacity.
3. **Deployment validation**
   - Run `terraform plan -var-file=terraform.tfvars.dev-lite` (create file) and check that no service is recreated unnecessarily.
   - After apply, monitor ECS events to ensure tasks start on spot within 10 minutes.
4. **Risks & mitigations**
   - Spot interruptions: enable “graceful shutdown” in app (catch SIGTERM) and document in `docs/architecture/runtime-behaviour.md`.
   - Ensure CloudWatch alarms are in place for failed deployments.

## Phase 2 – Scale-to-Zero for Background Work (Target: 30% ECS reduction)

1. **Parameterize desired counts**
   - Add variables `pyparser_desired_count`, `scheduler_high_freq_desired_count`, and `scheduler_low_freq_desired_count` with defaults of 0 in `variables.tf`.
   - Update the corresponding module calls in `main.tf` to use the new variables.
2. **Event-driven execution**
   - Create an `eventbridge` module that defines rules to trigger ECS tasks:
     - Vehicle/trip polling every minute.
     - Weather/aggregation jobs hourly.
   - Use `aws_cloudwatch_event_target` with `aws_ecs_task_definition` run commands.
3. **Runtime changes**
   - Add wrapper scripts or entrypoint arguments that terminate after completing a single batch (so scheduled tasks exit cleanly).
   - Push cron-specific configuration into `docs/architecture/runtime-jobs.md`.
4. **Testing**
   - Dry-run EventBridge rules using `events test-event-pattern`.
   - Validate log output shows successful completion within expected duration.

## Phase 3 – Consolidate Services (Target: remove two always-on tasks)

1. **Multi-container PHP task**
   - Extend the PHP service task definition to include containers for high- and low-frequency schedulers that run under `supervisor` or sidecar processes.
   - Ensure each worker has resource limits (CPU shares/memory reservation) to avoid starvation.
2. **Kill dedicated scheduler services**
   - Remove `module "ecs_service_scheduler_high_freq"` and `module "ecs_service_scheduler_low_freq"` once consolidation succeeds.
   - Update `docs/infrastructure/deployment-checklist.md` with the new deployment steps.
3. **Performance gates**
   - Monitor PHP task CPU/memory after consolidation. If >70% average, revert to separate services using the Terraform feature flags added in Phase 2.

## Phase 4 – Optional Infrastructure Downgrades

1. **Redis toggle**
   - Wrap `module "elasticache"` in a boolean `enable_redis` variable (default `false` for dev).
   - For dev, configure the application to fall back to an in-task Redis container or a Symfony ArrayAdapter.
   - Update the runbook in `docs/development/backend/redis.md` (create if missing).
2. **RDS stop/start automation**
   - Add `aws_scheduler_schedule` resources that stop the DB at 01:00 and start at 07:00 local time when `enable_db_sleep = true`.
   - Document manual override commands in `docs/infrastructure/deployment-checklist.md`.
3. **Container Insights toggle**
   - Parameterize `modules/ecs-cluster/main.tf:setting` so dev profiles can disable it.

## Rollout Timeline

| Phase | Duration | Primary Owner | Dependencies |
|-------|----------|---------------|--------------|
| Baseline & Guardrails | 1 day | Infra Lead | None |
| Phase 1 | 1-2 days | Terraform Maintainer | Budget profile committed |
| Phase 2 | 3-4 days | Backend + Infra | EventBridge IAM permissions |
| Phase 3 | 2 days | Backend | Phase 2 complete |
| Phase 4 | 2-3 days | Infra | App supports Redis fallback |

## Success Criteria

- Daily cost (Cost Explorer, tag filtered) ≤ **$8** for three consecutive weeks while running dev profile.
- Automated alerts trigger within one hour for spikes >20% above baseline.
- Terraform workspaces cleanly switch between `launch`, `dev-low-traffic`, and `offline` modes using documented variable files.
- Developers can spin the dev profile up/down with <15 minutes of manual effort.

## Follow-Up Work

- Publish a monthly cost report template in `docs/planning/monthly-cost-review.md`.
- Evaluate migrating static assets + API read endpoints to CloudFront/S3 once marketing begins.
- Revisit managed services sizing post-launch and decide whether to re-enable multi-AZ databases or provisioned Redis.
