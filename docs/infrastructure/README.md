# Infrastructure Documentation

## 🚨 Cost Optimization Alert

**Experiencing high AWS costs?** See **[AWS_COST_OPTIMIZATION.md](AWS_COST_OPTIMIZATION.md)** for aggressive cost-cutting strategies:
- Reduce from $255/month → **$82-103/month** (60-68% savings)
- **Quick win:** Fargate Spot alone → $150/month (40% savings in 2 hours!)
- Schedule-based scaling (run only during transit hours)
- Aurora Serverless v2 (true auto-pause)
- **Best approach:** Start with Fargate Spot for maximum ROI

---

## Quick Start Guide

### Choose Your Configuration

| Config | Cost | Traffic Capacity | Best For |
|--------|------|------------------|----------|
| **Budget** | **$70/month** | 0-100 users/day | Initial deployment, learning AWS |
| **Standard** | **$92/month** | 100-500 users/day | Production, better performance |
| **🔥 Spot (Quick Win)** | **~$150/month** | Dev/light prod | Fargate Spot only (2 hours setup) |
| **🔥 Development** | **$82-103/month** | Dev/testing | Spot + scheduling + Aurora (full optimization) |

**Recommendation:** Start with Spot config (easiest, 40% savings), add scheduling later if needed

## Documentation Files

### 0. 🔥 AWS Cost Optimization (`AWS_COST_OPTIMIZATION.md`) ⭐ **SAVE MONEY**
Aggressive cost reduction strategies for development phase:
- **Phase 2 (RECOMMENDED):** Fargate Spot (40% discount) - 2 hours → $150/month
- **Phase 3:** Schedule-based scaling (transit hours only) - 1 week → $110/month
- **Phase 4:** Aurora Serverless (true auto-pause) - 2-3 hours → $82-103/month
- Includes Lambda functions, EventBridge rules, audit scripts
- **Note:** Phase 1 (scheduler consolidation) removed to preserve architecture

**Key Takeaway:** Reduce $255/month → $150/month in 2 hours (Fargate Spot), or $82-103/month with full optimization (60-68% savings)

### 1. Cost Estimation (`aws-cost-estimation.md`)
Comprehensive cost breakdown for AWS ECS deployment:
- Detailed pricing for all AWS services
- Phase 5 storage projections (4-5 GB/year)
- Comparison of 4 deployment options
- Cost optimization strategies

**Key Takeaway:** $85-100/month for fully managed production infrastructure

### 2. Budget-Optimized Config (`budget-optimized-config.md`) ⭐ **START HERE**
Step-by-step guide for $70/month deployment:
- Smaller ECS tasks (0.25 vCPU instead of 0.5)
- Intel instances instead of Graviton2
- Performance expectations and monitoring
- When and how to upgrade to standard

**Key Takeaway:** Save $22/month with minimal performance impact

### 3. Architecture Diagram (`architecture-diagram.md`)
Visual and technical architecture overview:
- Network topology (VPC, subnets, security groups)
- Service flow diagrams
- High availability considerations
- Disaster recovery strategy

**Key Takeaway:** ECS Fargate + RDS + ElastiCache + ALB architecture

### 4. Terraform Structure (`terraform-structure.md`)
Infrastructure as Code implementation guide:
- Complete Terraform project layout
- Official AWS modules usage
- S3 backend setup with state locking
- Example configurations for all services

**Key Takeaway:** Modular Terraform with official AWS provider modules

### 5. CI/CD Workflow (`cicd-workflow.md`)
Automated deployment pipeline:
- Release Please for semantic versioning
- GitHub Actions for build/test/deploy
- Automated releases on merge to main
- Manual rollback procedures

**Key Takeaway:** Push to main → Release Please → Deploy automatically

### 6. Deployment Summary (`deployment-summary.md`)
3-week implementation roadmap:
- Week 1: Infrastructure setup (8-12 hours)
- Week 2: Application deployment (6-8 hours)
- Week 3: Production readiness (4-6 hours)
- Risk assessment and mitigation

**Key Takeaway:** 18-26 hours total setup time, systematic approach

## Quick Decision Tree

```
Do you have $100+/month budget?
├─ Yes ──> Do you need production-grade performance now?
│          ├─ Yes ──> Use STANDARD config ($92/month)
│          └─ No ───> Use BUDGET config ($70/month)
│
└─ No ───> Budget < $80/month?
           ├─ Yes ──> Consider DigitalOcean ($40/month)
           └─ No ───> Use BUDGET config ($70/month)
```

## Getting Started (5 Steps)

### Step 1: Prerequisites (30 minutes)
```bash
# Install required tools
brew install terraform awscli

# Purchase domain name
# https://www.namecheap.com or https://domains.google.com
# Cost: $10-15/year

# Create AWS account
# https://aws.amazon.com/
```

### Step 2: Review Documentation (1 hour)
1. Read `budget-optimized-config.md` (15 min)
2. Skim `architecture-diagram.md` (15 min)
3. Review `terraform-structure.md` (15 min)
4. Read `cicd-workflow.md` (15 min)

### Step 3: Configure Terraform (30 minutes)
```bash
cd terraform/environments/prod

# Use budget configuration
cp terraform.tfvars.budget terraform.tfvars

# Edit with your values
vim terraform.tfvars
# Update:
# - domain_name
# - database_password (use strong 32+ char password)
# - openai_api_key
# - gtfs_static_url (if different)
```

### Step 4: Bootstrap & Deploy (2-4 hours)
```bash
# Bootstrap backend (run once)
cd terraform/bootstrap
chmod +x bootstrap.sh
./bootstrap.sh

# Deploy infrastructure
cd ../environments/prod
terraform init
terraform plan -out=tfplan
# Review plan carefully
terraform apply tfplan
```

### Step 5: Configure CI/CD (1-2 hours)
```bash
# Add GitHub secrets (see cicd-workflow.md)
# - AWS_ACCESS_KEY_ID
# - AWS_SECRET_ACCESS_KEY
# - AWS_REGION
# - (and others listed in cicd-workflow.md)

# Create workflow files
# Copy from cicd-workflow.md to .github/workflows/
```

## Cost Summary Comparison

### Budget Configuration ($70/month)
```
ECS Fargate (smaller tasks):    $27
RDS db.t3.micro:                 $11
ElastiCache cache.t3.micro:      $10
Application Load Balancer:       $18
Route 53 + CloudWatch + ECR:      $4
─────────────────────────────────────
Total:                           $70/month
```

### Standard Configuration ($92/month)
```
ECS Fargate (standard tasks):    $36
RDS db.t4g.micro:                $14
ElastiCache cache.t4g.micro:     $12
Application Load Balancer:       $23
Route 53 + CloudWatch + ECR:      $7
─────────────────────────────────────
Total:                           $92/month
```

### After 3 Months with Reserved Instances
```
Budget: $70 → $60/month (save $10)
Standard: $92 → $80/month (save $12)
```

## Monitoring & Upgrading

### Check if You Need to Upgrade

**Week 1:** Check metrics daily
```bash
# View ECS CPU utilization
aws cloudwatch get-metric-statistics \
  --namespace AWS/ECS \
  --metric-name CPUUtilization \
  --dimensions Name=ServiceName,Value=mind-the-wait-prod-php \
  --start-time $(date -u -d '1 day ago' +%Y-%m-%dT%H:%M:%S) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
  --period 3600 \
  --statistics Average
```

**Month 1:** Weekly review
- CPU average < 50% ✅ Budget is fine
- CPU average > 70% ⚠️ Consider upgrading
- Memory > 80% ⚠️ Upgrade soon

**Month 3:** Optimization review
- If stable on budget → Apply for Reserved Instances
- If maxed out → Upgrade to standard → Then Reserved Instances

### Upgrade Process

```bash
cd terraform/environments/prod

# Switch to standard config
cp terraform.tfvars terraform.tfvars.budget.backup
cp terraform.tfvars.example terraform.tfvars
# Copy secrets from backup

# Plan upgrade
terraform plan -out=upgrade.tfplan

# Apply (causes ~5 min downtime for RDS/Redis)
terraform apply upgrade.tfplan
```

**Expected Downtime:** 5-10 minutes (RDS and Redis instance type changes)
**Traffic Impact:** ECS rolling update = zero downtime for app

## Support & Troubleshooting

### Common Issues

**Issue: Terraform state locked**
```bash
# Unlock if previous apply failed
terraform force-unlock LOCK_ID
```

**Issue: High AWS costs**
```bash
# Check Cost Explorer
aws ce get-cost-and-usage \
  --time-period Start=$(date -d '1 month ago' +%Y-%m-%d),End=$(date +%Y-%m-%d) \
  --granularity DAILY \
  --metrics BlendedCost

# Look for: NAT Gateway ($35/month), unattached EBS volumes, old ECR images
```

**Issue: ECS tasks failing**
```bash
# View task failures
aws ecs describe-tasks \
  --cluster mind-the-wait-prod \
  --tasks $(aws ecs list-tasks --cluster mind-the-wait-prod --service-name mind-the-wait-prod-php --query 'taskArns[0]' --output text)

# Check logs
aws logs tail /ecs/mind-the-wait-prod/php --follow
```

**Issue: Database connection errors**
```bash
# Check RDS security group allows ECS tasks
aws ec2 describe-security-groups \
  --filters Name=tag:Name,Values=mind-the-wait-prod-rds-sg

# Test connection from ECS task
aws ecs execute-command \
  --cluster mind-the-wait-prod \
  --task <task-id> \
  --interactive \
  --command "psql -h <rds-endpoint> -U mindthewait_admin -d mindthewait"
```

### Getting Help

**AWS Documentation:**
- [ECS Troubleshooting](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/troubleshooting.html)
- [RDS Troubleshooting](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/CHAP_Troubleshooting.html)
- [Terraform AWS Provider](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)

**Community Support:**
- [AWS re:Post](https://repost.aws/) - Official AWS Q&A
- [r/aws](https://reddit.com/r/aws) - AWS community
- [Terraform Community](https://discuss.hashicorp.com/) - Terraform help

**Cost Optimization:**
- [AWS Cost Explorer](https://aws.amazon.com/aws-cost-management/aws-cost-explorer/)
- [Trusted Advisor](https://aws.amazon.com/premiumsupport/technology/trusted-advisor/)

## Files Created

```
terraform/
└── environments/
    └── prod/
        ├── terraform.tfvars.budget      # Budget config ($70/month)
        └── terraform.tfvars.example     # Standard config ($92/month)

docs/infrastructure/
├── README.md                            # This file
├── aws-cost-estimation.md               # Detailed cost breakdown
├── budget-optimized-config.md           # Budget deployment guide ⭐
├── architecture-diagram.md              # Architecture overview
├── terraform-structure.md               # Terraform implementation
├── cicd-workflow.md                     # CI/CD pipeline setup
└── deployment-summary.md                # 3-week implementation plan
```

## Next Steps

✅ **Decided to deploy?**
1. Start with `budget-optimized-config.md`
2. Follow the 5 steps in "Getting Started" above
3. Use the 3-week roadmap in `deployment-summary.md`

⏸️ **Still evaluating?**
1. Review `aws-cost-estimation.md` for detailed costs
2. Read `architecture-diagram.md` to understand the setup
3. Consider alternatives (DigitalOcean, Linode, etc.)

❓ **Have questions?**
1. Check the "Support & Troubleshooting" section above
2. Review the specific documentation file for your question
3. Open an issue in the repository

---

**Estimated Time to Production:** 18-26 hours over 3 weeks

**Estimated Monthly Cost:** $70-92 depending on configuration

**Phase 5 Ready:** Yes - infrastructure scales easily for data collection
