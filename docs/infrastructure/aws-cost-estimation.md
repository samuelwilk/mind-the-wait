# AWS ECS Production Deployment - Cost Estimation

## Executive Summary

**Recommended Monthly Cost: $85-100/month**

This estimate includes all AWS services needed for production deployment with automatic scaling, managed databases, and CI/CD infrastructure.

## Architecture Overview

### Current Docker Services → AWS Mapping

| Current Service | AWS Service | Purpose |
|----------------|-------------|---------|
| `php` (FrankenPHP) | ECS Fargate Task | Web application |
| `nginx` | Application Load Balancer | HTTPS termination, routing |
| `database` (PostgreSQL) | RDS PostgreSQL | Managed database |
| `redis` | ElastiCache Redis | Realtime data cache |
| `pyparser` | ECS Fargate Task | GTFS-RT feed parser |
| `scheduler` | ECS Fargate Task | Cron jobs (score-tick, cache warming) |

### Additional AWS Services

- **ECR (Elastic Container Registry)**: Store Docker images
- **Route 53**: DNS management for your domain
- **Certificate Manager (ACM)**: Free SSL certificates
- **VPC**: Network isolation (public/private subnets)
- **Systems Manager Parameter Store**: Secrets (free tier)
- **CloudWatch**: Logging and monitoring
- **S3**: Terraform state storage
- **IAM**: Security and access management

## Phase 5 Data Storage Requirements

### Current Data Volume (Development)
- Routes: ~15-20 (Saskatoon Transit)
- Stops: ~1,500
- Trips: ~13,000
- Stop Times: ~50,000

### Phase 5 - Real-Time Data Collection

**Daily Data Generation:**
```
Arrivals per day:
  20 routes × 50 trips/route × 20 stops/trip = 20,000 arrivals/day

Over 1 year:
  20,000 × 365 = 7.3M arrival records

Storage per arrival record: ~200 bytes
  7.3M × 200 bytes = 1.46 GB/year raw data

With indexes, foreign keys, and overhead:
  ~4-5 GB/year total database size
```

**3-Year Storage Projection:**
| Year | Arrival Records | Database Size | RDS Storage Needed |
|------|----------------|---------------|-------------------|
| Year 1 | 7.3M | 5 GB | 20 GB (minimum) |
| Year 2 | 14.6M | 10 GB | 20 GB |
| Year 3 | 21.9M | 15 GB | 20 GB |
| Year 5 | 36.5M | 25 GB | 30 GB |

**Storage Cost Growth:**
- Year 1-3: 20 GB @ $0.115/GB = **$2.30/month** (no increase)
- Year 5: 30 GB @ $0.115/GB = **$3.45/month** (+$1.15)

✅ **Storage is cheap** - scaling from 20GB to 100GB only adds ~$10/month

## Detailed Cost Breakdown

### Option A: Production-Ready ECS Fargate (Recommended)

#### Compute - ECS Fargate
```
php service (web app):
  - 0.5 vCPU, 1 GB RAM, 24/7
  - vCPU: 0.5 × $0.04048/hour × 730 hours = $14.78/month
  - Memory: 1 GB × $0.004445/GB-hour × 730 = $3.24/month
  - Subtotal: $18/month

pyparser service (GTFS-RT polling):
  - 0.25 vCPU, 0.5 GB RAM, 24/7
  - vCPU: 0.25 × $0.04048 × 730 = $7.39/month
  - Memory: 0.5 GB × $0.004445 × 730 = $1.62/month
  - Subtotal: $9/month

scheduler service (cron jobs):
  - 0.25 vCPU, 0.5 GB RAM, 24/7
  - Subtotal: $9/month

Total Fargate: $36/month
```

#### Database - RDS PostgreSQL
```
Instance: db.t4g.micro (2 vCPU, 1 GB RAM, Graviton2)
  - Compute: $0.016/hour × 730 = $11.68/month
  - Storage: 20 GB gp3 SSD × $0.115/GB = $2.30/month
  - Backup storage: 20 GB (free, same as allocated)
  - Subtotal: $14/month

Multi-AZ (optional, high availability):
  - Doubles cost to $28/month
  - Recommended for production, but start without it
```

#### Cache - ElastiCache Redis
```
Instance: cache.t4g.micro (2 vCPU, 0.5 GB RAM, Graviton2)
  - $0.016/hour × 730 = $11.68/month
```

#### Load Balancer - Application Load Balancer
```
ALB fixed cost: $0.0225/hour × 730 = $16.43/month
LCU charges (Load Balancer Capacity Units):
  - New connections: ~50/sec peak = 0.3 LCU
  - Active connections: ~500 = 0.5 LCU
  - Data processed: ~10 GB/month = 0.3 LCU
  - Total: ~1.1 LCU × $0.008/hour × 730 = $6.42/month

Subtotal: $23/month
```

#### DNS - Route 53
```
Hosted zone: $0.50/month
Query charges: 1M queries × $0.40/million = $0.40/month
Subtotal: $1/month
```

#### Container Registry - ECR
```
Storage: 2 GB images × $0.10/GB = $0.20/month
Data transfer: Negligible (within AWS)
```

#### SSL Certificates - ACM
```
Public certificates: FREE
```

#### Secrets - Systems Manager Parameter Store
```
Standard parameters (up to 10,000): FREE
Advanced parameters: Not needed
```

#### Networking - VPC
```
VPC, subnets, route tables, internet gateway: FREE

NAT Gateway (optional, for private subnet internet access):
  - $0.045/hour × 730 = $32.85/month
  - $0.045/GB data processed = ~$2/month
  - Subtotal: $35/month

⚠️ Cost Optimization: Use public subnets to avoid NAT Gateway
```

#### Logging & Monitoring - CloudWatch
```
Log ingestion: 5 GB × $0.50/GB = $2.50/month
Log storage: 5 GB × $0.03/GB = $0.15/month
Metrics: Mostly covered by free tier
Alarms: 10 alarms × $0.10/alarm = $1/month
Subtotal: $4/month
```

#### Data Transfer
```
Data out to internet:
  - First 100 GB/month: FREE
  - Low traffic site unlikely to exceed
  - Estimate: $0-2/month
```

### Total Monthly Cost - Option A

**Without NAT Gateway (Public Subnets):**
| Service | Monthly Cost |
|---------|--------------|
| ECS Fargate (3 tasks) | $36 |
| RDS PostgreSQL | $14 |
| ElastiCache Redis | $12 |
| Application Load Balancer | $23 |
| Route 53 | $1 |
| CloudWatch + Misc | $6 |
| **TOTAL** | **~$92/month** |

**With NAT Gateway (Private Subnets, More Secure):**
| Service | Monthly Cost |
|---------|--------------|
| All above | $92 |
| NAT Gateway | $35 |
| **TOTAL** | **~$127/month** |

### Option B: Cost-Optimized ECS Fargate

Reduce container sizes for lower traffic:
```
php service: 0.25 vCPU, 0.5 GB RAM = $9/month
pyparser: 0.25 vCPU, 0.5 GB RAM = $9/month
scheduler: 0.25 vCPU, 0.5 GB RAM = $9/month
Total Fargate: $27/month (saves $9/month)

Total: ~$83/month
```

### Option C: ECS on EC2 (More Management)

```
EC2 Instance: t3.small (2 vCPU, 2 GB RAM)
  - On-Demand: $0.0208/hour × 730 = $15.18/month
  - Reserved (1 year): ~$10/month (saves 35%)

Run all containers on single EC2 instance
Replace Fargate cost ($36) with EC2 cost ($15)

Total: ~$71/month (saves $21/month)
```

⚠️ **Tradeoff**: Less auto-scaling, more management, manual updates

### Option D: Alternative - AWS Lightsail (Simplest)

```
Lightsail Container Service: $40/month (2 vCPU, 4 GB RAM)
  - Includes load balancer, SSL, container orchestration

RDS PostgreSQL: $14/month
ElastiCache Redis: $12/month
Route 53: $1/month

Total: ~$67/month
```

⚠️ **Tradeoff**: Less Terraform support, limited to Lightsail features

## Additional Costs (One-Time or External)

### Domain Name
- Purchase: $10-15/year (~$1/month)
- Renewal: $10-15/year

### OpenAI API (AI Insights)
- With 24-hour caching: $0.05/month
- Negligible

### GitHub Actions (CI/CD)
- Public repos: FREE unlimited minutes
- Private repos: 2,000 minutes/month free, then $0.008/minute
- Estimate: FREE (within limits)

### AWS Free Tier (First 12 Months)
If you're on a new AWS account:
- RDS: 750 hours/month db.t2.micro (not t4g)
- ECR: 500 MB storage/month free
- CloudWatch: 10 custom metrics, 5 GB logs free
- ALB: Not in free tier
- ElastiCache: Not in free tier
- Fargate: Not in free tier

⚠️ Free tier savings are minimal for this architecture (~$5-10/month)

## Recommended Architecture: Option A (Public Subnets)

**Total: $85-100/month**

### Why This Option?
✅ Fully managed (RDS, ElastiCache, Fargate)
✅ Auto-scaling capability
✅ No server management
✅ Terraform-friendly with official modules
✅ Production-ready security
✅ Easy CI/CD integration
✅ Avoid NAT Gateway costs

### Cost Optimization Strategies

1. **Start Small, Scale Up**
   - Begin with 0.25 vCPU containers
   - Monitor CloudWatch metrics
   - Scale only when needed

2. **Reserved Instances (After 3 Months)**
   - RDS Reserved Instance (1 year): Save ~35%
   - ElastiCache Reserved Node (1 year): Save ~35%
   - Savings: ~$8-10/month

3. **Savings Plans (After 6 Months)**
   - Fargate Compute Savings Plan: Save ~20-50%
   - Savings: ~$5-15/month

4. **Lifecycle Policies**
   - ECR: Delete old images (save $0.10/GB)
   - CloudWatch: 7-day log retention (save $0.03/GB/month)
   - RDS: 7-day backup retention (free tier)

5. **Monitoring & Alerts**
   - Set CloudWatch alarms for cost anomalies
   - Budget alerts at $80, $100, $120/month
   - Use Cost Explorer to track spending

## Scaling for Growth

### If Traffic Increases 10x
```
Current: $92/month
+ More Fargate tasks: +$50/month
+ Larger RDS instance: +$30/month (db.t4g.small)
+ More ALB LCU: +$10/month
+ Data transfer: +$20/month
= ~$200/month
```

### If Data Grows to 100 GB (Year 5+)
```
Current: $92/month
+ RDS storage increase: +$10/month (80 GB more)
= ~$102/month
```

## Phase 5 Impact on Costs

Phase 5 features (Historical Analysis, Predictions, Benchmarking):
- **Storage**: Minimal increase (see table above)
- **Compute**: No increase (same analysis workload)
- **API calls**: No increase (same dashboard)
- **Estimated Phase 5 cost increase**: $0-5/month

## Summary & Recommendation

| Option | Monthly Cost | Management | Best For |
|--------|--------------|------------|----------|
| **A: ECS Fargate (Public)** | **$85-100** | **Low** | **Learning, Production** ✅ |
| B: ECS Fargate (Private) | $125-140 | Low | High security needs |
| C: ECS on EC2 | $70-85 | Medium | Cost optimization |
| D: Lightsail | $65-70 | Low | Simplicity over flexibility |

**Recommendation: Option A (ECS Fargate, Public Subnets)**

This gives you:
- Modern serverless architecture
- Full Terraform support with official modules
- Automatic scaling capability
- Production-grade security (can add NAT later)
- Learning opportunity for ECS/Fargate
- Reasonable cost (~$92/month)
- Easy CI/CD with GitHub Actions + Release Please

**Next Steps:**
1. Purchase domain name
2. Create AWS account (or use existing)
3. Set up Terraform project structure
4. Configure GitHub secrets and CI/CD
5. Implement infrastructure as code
6. Deploy and test

**Estimated Setup Time:** 8-12 hours for initial Terraform + CI/CD setup
