# Hetzner Migration Design

**Date:** 2026-02-07
**Status:** Approved
**Goal:** Migrate from AWS ECS (~$70/mo) to Hetzner VPS (~$20/mo) while preserving historical data

## Context

- ECS is too expensive for a project with no users
- Need to continue collecting data for analysis features
- Production routes showing as "Unknown" due to GTFS route ID mismatch (since ~Dec 23)
- Historical data in RDS must be preserved

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                 Hetzner VPS (cx22 - €4/mo)              │
│                    Ubuntu 24.04                          │
├─────────────────────────────────────────────────────────┤
│  Docker Compose                                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │   Caddy     │  │    PHP      │  │   Redis     │     │
│  │  (proxy)    │←→│ (FrankenPHP)│←→│  (cache)    │     │
│  │  :80/:443   │  │    :8080    │  │   :6379     │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│         │                │                               │
│  ┌──────┴────────────────┴──────────────────────┐      │
│  │              Internal Network                  │      │
│  └──────────────────────────────────────────────┘      │
│         │                                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │  PyParser   │  │ Scheduler   │  │  Scheduler  │     │
│  │ (GTFS-RT)   │  │ (high-freq) │  │ (low-freq)  │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│                                                          │
│  ┌─────────────┐                                        │
│  │  Mercure    │                                        │
│  │   (SSE)     │                                        │
│  └─────────────┘                                        │
└─────────────────────────────────────────────────────────┘
                          │
                          │ PostgreSQL (port 5432)
                          ▼
         ┌────────────────────────────────────┐
         │     AWS RDS (keep existing)        │
         │     ca-central-1                   │
         │     db.t3.micro - ~$15/mo          │
         │     (publicly accessible: true)    │
         └────────────────────────────────────┘
```

## Cost Breakdown

| Component | Provider | Cost |
|-----------|----------|------|
| VPS (2 vCPU, 4GB) | Hetzner cx22 | €4.51/mo |
| PostgreSQL | AWS RDS (existing) | ~$15/mo |
| DNS/CDN | Cloudflare | Free |
| **Total** | | **~$20/mo** |

**Savings:** ~$50/month (from ~$70 ECS setup)

## Terraform Structure

```
terraform/
├── environments/
│   └── production/
│       ├── main.tf          # Hetzner VPS + Cloudflare DNS
│       ├── variables.tf
│       ├── terraform.tfvars
│       └── outputs.tf
└── modules/
    ├── vps/                  # Hetzner server provisioning
    │   └── main.tf          # Ubuntu 24.04, Docker, firewall, SSH
    └── dns/                  # Cloudflare DNS records
        └── main.tf          # A record → VPS IP
```

**Hetzner VPS config:**
- Type: cx22 (2 vCPU, 4GB RAM)
- Location: Nuremberg (nbg1)
- OS: Ubuntu 24.04
- Firewall: SSH (22), HTTP (80), HTTPS (443)

## Docker Compose

**Files:**
- `docker/compose.yaml` - Development (existing)
- `docker/compose.prod.yaml` - Production
- `docker/Caddyfile.prod` - Production Caddy config

**Services:**
- `caddy` - Reverse proxy with auto HTTPS
- `php` - FrankenPHP application
- `redis` - Cache (replaces ElastiCache)
- `pyparser` - GTFS-RT polling
- `scheduler-high-freq` - score_tick, mercure_broadcast, arrival_logging
- `scheduler-low-freq` - weather, aggregation, insights, bunching
- `mercure` - SSE broadcasting

## CI/CD Pipeline

GitHub Actions workflow:
1. **Test job** - PHPUnit on push to main
2. **Deploy job** - SSH to Hetzner on release/dispatch
   - Pull latest code
   - Create `.env.prod` from GitHub secrets
   - Build and deploy containers
   - Run migrations and warm cache

**GitHub Secrets:**
- `SERVER_IP` - Hetzner VPS IP
- `SSH_PRIVATE_KEY` - Deploy key
- `APP_SECRET`, `DATABASE_URL`, `OPENAI_API_KEY`, `MERCURE_JWT_SECRET`
- `GTFS_RT_VEHICLES_URL`, `GTFS_RT_TRIPS_URL`, `GTFS_RT_ALERTS_URL`

## Migration Steps

1. **Prepare RDS for external access**
   - Update Terraform: `publicly_accessible = true`
   - Add security group rule for Hetzner VPS IP
   - Apply changes

2. **Provision Hetzner VPS**
   - Run Terraform to create VPS
   - Cloud-init installs Docker, creates deploy user
   - Configure Cloudflare DNS → VPS IP

3. **Deploy application**
   - Clone repo to VPS
   - Create `.env.prod` with secrets
   - `docker compose -f docker/compose.prod.yaml up -d`
   - Run migrations, warm cache

4. **Fix route data**
   - Reload GTFS static data: `bin/console app:gtfs:load --mode=arcgis`
   - Investigate route ID mapping (old 1,2,3 → new 14573,14574...)
   - If needed: UPDATE query to fix orphaned historical data

5. **Cutover**
   - Update Cloudflare DNS to point to Hetzner
   - Verify site works
   - Tear down ECS infrastructure (keep RDS!)

## AWS Resources to Delete

- ECS cluster, services, task definitions
- ALB, target groups
- ElastiCache Redis
- ECR repositories
- Route 53 hosted zone (using Cloudflare instead)

## AWS Resources to Keep

- RDS PostgreSQL (all historical data)
- S3 terraform state bucket

## Data Integrity

Route ID mismatch occurred around Dec 23, 2025. Impact:
- ~6 weeks of route performance data with new IDs (14573, 14574...)
- Database has old route IDs (1, 2, 3...)
- Historical metrics preserved, just orphaned from route names

Fix approach after GTFS reload:
```sql
-- Map old→new route IDs by matching on short_name
SELECT
  old.gtfs_id as old_id,
  new.gtfs_id as new_id,
  new.short_name
FROM route old
JOIN route new ON old.short_name = new.short_name
WHERE old.gtfs_id != new.gtfs_id;
```

## Files to Create

- `terraform/environments/production/` (new Hetzner config)
- `terraform/modules/vps/`
- `terraform/modules/dns/`
- `docker/compose.prod.yaml`
- `docker/Caddyfile.prod`
- `.github/workflows/deploy-production.yml` (update)
