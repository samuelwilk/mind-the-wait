# Production Architecture Diagram

## AWS ECS Fargate Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Internet / Users                           │
└────────────────────────────────┬────────────────────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │    Route 53 (DNS)       │
                    │  yourdomain.com         │
                    │  → ALB DNS              │
                    └────────────┬────────────┘
                                 │
                    ┌────────────▼────────────────────────┐
                    │   Certificate Manager (ACM)         │
                    │   SSL Certificate (FREE)            │
                    └────────────┬────────────────────────┘
                                 │
                    ┌────────────▼────────────────────────┐
                    │  Application Load Balancer (ALB)    │
                    │  - HTTPS Listener (443)             │
                    │  - HTTP → HTTPS Redirect (80)       │
                    │  - Target Group: ECS php service    │
                    └────────────┬────────────────────────┘
                                 │
┌────────────────────────────────┼────────────────────────────────────┐
│                            VPC (10.0.0.0/16)                         │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │              Public Subnets (10.0.1.0/24, 10.0.2.0/24)        │ │
│  │                                                                │ │
│  │  ┌─────────────────────────────────────────────────────────┐  │ │
│  │  │            ECS Cluster: mind-the-wait-prod              │  │ │
│  │  │                                                          │  │ │
│  │  │  ┌──────────────────────────────────────────────────┐   │  │ │
│  │  │  │  ECS Service: php                                │   │  │ │
│  │  │  │  - Task: 0.5 vCPU, 1 GB RAM                      │   │  │ │
│  │  │  │  - Image: ECR php image                          │   │  │ │
│  │  │  │  - Port: 8080 (HTTP)                             │   │  │ │
│  │  │  │  - Desired Count: 1 (auto-scale to 3)            │   │  │ │
│  │  │  │  - Health Check: /api/realtime                   │   │  │ │
│  │  │  └────────────┬─────────────────────────────────────┘   │  │ │
│  │  │               │ Reads/Writes                             │  │ │
│  │  │  ┌────────────▼─────────────────────────────────────┐   │  │ │
│  │  │  │  ECS Service: pyparser                           │   │  │ │
│  │  │  │  - Task: 0.25 vCPU, 0.5 GB RAM                   │   │  │ │
│  │  │  │  - Image: ECR pyparser image                     │   │  │ │
│  │  │  │  - No exposed ports                              │   │  │ │
│  │  │  │  - Desired Count: 1                              │   │  │ │
│  │  │  │  - Polls GTFS-RT feeds → writes to Redis        │   │  │ │
│  │  │  └────────────┬─────────────────────────────────────┘   │  │ │
│  │  │               │ Writes                                   │  │ │
│  │  │  ┌────────────▼─────────────────────────────────────┐   │  │ │
│  │  │  │  ECS Service: scheduler                          │   │  │ │
│  │  │  │  - Task: 0.25 vCPU, 0.5 GB RAM                   │   │  │ │
│  │  │  │  - Image: ECR php image                          │   │  │ │
│  │  │  │  - No exposed ports                              │   │  │ │
│  │  │  │  - Desired Count: 1                              │   │  │ │
│  │  │  │  - Runs: score-tick (30s), cache-warm (2AM)     │   │  │ │
│  │  │  └──────────────────────────────────────────────────┘   │  │ │
│  │  └──────────────────────────────────────────────────────────┘  │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │           Private Subnets (10.0.10.0/24, 10.0.11.0/24)        │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │  RDS PostgreSQL (Multi-AZ optional)                      │ │ │
│  │  │  - Instance: db.t4g.micro                                │ │ │
│  │  │  - Engine: PostgreSQL 16                                 │ │ │
│  │  │  - Storage: 20 GB gp3 SSD (auto-scaling to 100 GB)      │ │ │
│  │  │  - Backup: 7 days retention                             │ │ │
│  │  │  - Endpoint: mindthewait.xxxxx.us-east-1.rds.amazonaws  │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  │                                                                │ │
│  │  ┌──────────────────────────────────────────────────────────┐ │ │
│  │  │  ElastiCache Redis (Single-AZ)                           │ │ │
│  │  │  - Instance: cache.t4g.micro                             │ │ │
│  │  │  - Engine: Redis 7.x                                     │ │ │
│  │  │  - Endpoint: mindthewait.xxxxx.cache.amazonaws.com      │ │ │
│  │  └──────────────────────────────────────────────────────────┘ │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  Security Groups:                                                    │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ ALB SG:         Allow 80, 443 from 0.0.0.0/0                │   │
│  │ ECS Tasks SG:   Allow 8080 from ALB SG                      │   │
│  │ RDS SG:         Allow 5432 from ECS Tasks SG                │   │
│  │ Redis SG:       Allow 6379 from ECS Tasks SG                │   │
│  └─────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                          Supporting Services                         │
├─────────────────────────────────────────────────────────────────────┤
│  ECR (Elastic Container Registry)                                   │
│  - Repository: mind-the-wait/php                                    │
│  - Repository: mind-the-wait/pyparser                               │
│  - Lifecycle: Keep last 10 images                                   │
├─────────────────────────────────────────────────────────────────────┤
│  Systems Manager Parameter Store (Secrets)                          │
│  - /prod/mindthewait/database_url                                   │
│  - /prod/mindthewait/redis_url                                      │
│  - /prod/mindthewait/openai_api_key                                 │
│  - /prod/mindthewait/gtfs_static_url                                │
├─────────────────────────────────────────────────────────────────────┤
│  CloudWatch Logs                                                    │
│  - Log Group: /ecs/mind-the-wait-prod/php                           │
│  - Log Group: /ecs/mind-the-wait-prod/pyparser                      │
│  - Log Group: /ecs/mind-the-wait-prod/scheduler                     │
│  - Retention: 7 days                                                │
├─────────────────────────────────────────────────────────────────────┤
│  CloudWatch Alarms                                                  │
│  - CPU Utilization > 80% → SNS Alert                                │
│  - Memory Utilization > 80% → SNS Alert                             │
│  - ALB 5xx Errors > 10/min → SNS Alert                              │
│  - RDS Storage < 20% → SNS Alert                                    │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                       CI/CD Pipeline (GitHub Actions)                │
├─────────────────────────────────────────────────────────────────────┤
│  1. Push to main branch                                             │
│  2. Release Please creates release PR with changelog                │
│  3. Merge release PR → GitHub Release created                       │
│  4. GitHub Action triggered on release:                             │
│     a. Build Docker images (php, pyparser)                          │
│     b. Push images to ECR with :latest and :v1.2.3 tags             │
│     c. Update ECS task definitions with new image                   │
│     d. Deploy to ECS (rolling update)                               │
│     e. Health check verification                                    │
│     f. Rollback on failure                                          │
└─────────────────────────────────────────────────────────────────────┘
```

## Network Flow

### 1. User Request Flow
```
User Browser
  → Route 53 DNS (yourdomain.com)
  → ALB (HTTPS:443)
  → ECS php Task (HTTP:8080)
  → Reads from Redis (vehicle positions, scores)
  → Queries PostgreSQL (routes, stops, performance data)
  → Returns HTML/JSON response
```

### 2. GTFS-RT Polling Flow (Every 5-10 seconds)
```
pyparser Task
  → Polls external GTFS-RT API (protobuf)
  → Parses vehicle positions, trip updates, alerts
  → Writes JSON to Redis keys (mtw:vehicles, mtw:trips, mtw:alerts)
```

### 3. Scoring Flow (Every 30 seconds)
```
scheduler Task
  → Runs bin/console app:score:tick
  → Reads Redis (vehicle positions)
  → Calculates headways and grades
  → Writes scores to Redis (mtw:score)
```

### 4. Cache Warming Flow (Daily at 2:00 AM)
```
scheduler Task
  → Runs bin/console app:warm-insight-cache
  → Reads PostgreSQL (performance stats)
  → Calls OpenAI API (7 insights)
  → Writes to Symfony cache (24-hour TTL)
```

## High Availability Considerations

### Current Setup (Cost-Optimized)
- **Single AZ**: All resources in one availability zone
- **RDS**: Single instance (no Multi-AZ)
- **ElastiCache**: Single node (no replication)
- **ECS Tasks**: Can run in multiple AZs (free)
- **ALB**: Multi-AZ by default
- **Estimated Downtime**: ~4 hours/year (99.95% uptime)

### High Availability Upgrade (+$40/month)
- **RDS Multi-AZ**: +$14/month (automatic failover)
- **ElastiCache Cluster**: +$12/month (Redis replication)
- **Multi-Region**: +$14/month (cross-region backup)
- **Estimated Downtime**: ~30 minutes/year (99.99% uptime)

**Recommendation**: Start with single-AZ, upgrade after 3-6 months

## Disaster Recovery

### Backup Strategy
```
RDS:
  - Automated daily backups (7 days retention)
  - Manual snapshots before major changes
  - Point-in-time recovery (5 minutes RPO)

ElastiCache:
  - No persistence needed (realtime data, regenerates)
  - Application can rebuild Redis from GTFS-RT feeds

Application:
  - Infrastructure as Code (Terraform in git)
  - Docker images in ECR (versioned)
  - Secrets in Parameter Store (versioned)
```

### Recovery Time Objectives (RTO)
- **Total Infrastructure Loss**: 2-4 hours (rebuild from Terraform)
- **RDS Failure**: 5-10 minutes (Multi-AZ failover)
- **ECS Task Failure**: 1-2 minutes (auto-restart)
- **ALB Failure**: 0 minutes (AWS managed, Multi-AZ)

## Security Architecture

### Network Security
- **VPC Isolation**: All resources in private network
- **Security Groups**: Least-privilege access rules
- **No Public IPs**: ECS tasks use ALB for internet access
- **SSL/TLS**: HTTPS only (ACM certificate)

### Application Security
- **Secrets**: Never in code, only in Parameter Store
- **IAM Roles**: Task-level permissions (no AWS keys)
- **Container Scanning**: ECR scans for vulnerabilities
- **WAF (Optional)**: +$5/month (OWASP rules)

### Access Control
- **AWS Console**: MFA required
- **GitHub**: Branch protection, required reviews
- **Secrets**: Encrypted at rest (AWS KMS)

## Monitoring & Observability

### CloudWatch Dashboards
```
Performance Dashboard:
  - ECS CPU/Memory utilization
  - ALB request count, latency, errors
  - RDS connections, CPU, storage
  - Redis hits/misses, memory

Business Metrics Dashboard:
  - Active vehicles tracked
  - API requests/minute
  - Headway score distribution
  - AI insight cache hit rate
```

### Alerting Strategy
```
Critical Alerts (PagerDuty/Email):
  - ALB 5xx errors > 5% of requests
  - RDS CPU > 90% for 5 minutes
  - ECS task failures (any service)

Warning Alerts (Email):
  - ALB latency > 2 seconds
  - RDS storage < 20%
  - ECS CPU > 80% for 10 minutes
  - CloudWatch Logs errors spike

Info Alerts (Slack):
  - New deployment started
  - New release created
  - Daily cost report
```

## Cost Monitoring

### AWS Cost Explorer Tags
```
Tag all resources:
  - Environment: production
  - Project: mind-the-wait
  - ManagedBy: terraform
  - CostCenter: transit-monitoring

Track costs by:
  - Service (ECS, RDS, ALB)
  - Environment (prod, staging)
  - Monthly trends
```

### Budget Alerts
```
AWS Budgets (free tier):
  - Budget: $100/month
  - Alert at: $80 (80%), $100 (100%), $120 (120%)
  - Action: Email notification, Slack webhook
```

## Scalability Roadmap

### Current Capacity
- **Concurrent Users**: ~100-500
- **API Requests/Min**: ~500
- **Vehicles Tracked**: ~50
- **Database Size**: 5 GB

### Scale to 10x Traffic
```
Changes needed:
  - ECS php tasks: 1 → 3 (auto-scaling)
  - RDS instance: db.t4g.micro → db.t4g.small
  - ElastiCache: cache.t4g.micro → cache.t4g.small
  - ALB: No changes (handles it)

Cost increase: +$80/month
```

### Scale to Multiple Cities
```
Changes needed:
  - Deploy per-city stacks (isolated databases)
  - OR: Multi-tenant database with city_id partitioning
  - Shared infrastructure: ALB, ECR, Route 53

Cost per additional city: +$60/month (shared infra)
```
