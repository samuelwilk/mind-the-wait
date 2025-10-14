# Production Deployment Summary & Next Steps

## Executive Summary

**Estimated Monthly Cost: $85-100**

This comprehensive plan outlines deploying mind-the-wait to AWS ECS Fargate with:
- Fully managed infrastructure (RDS, ElastiCache, Fargate)
- Automated CI/CD with Release Please
- Infrastructure as Code with Terraform
- Support for Phase 5 data collection (5-10 GB/year growth)
- Production-ready security and monitoring

## Quick Reference

| Component | Service | Monthly Cost |
|-----------|---------|--------------|
| Compute | ECS Fargate (3 tasks) | $36 |
| Database | RDS PostgreSQL db.t4g.micro | $14 |
| Cache | ElastiCache Redis cache.t4g.micro | $12 |
| Load Balancer | Application Load Balancer | $23 |
| DNS | Route 53 | $1 |
| Logging | CloudWatch | $5 |
| Container Registry | ECR | $1 |
| **Total** | | **$92/month** |

## Documentation Overview

### 1. Cost Estimation (`aws-cost-estimation.md`)
- **Phase 5 Storage**: 4-5 GB/year growth, negligible cost impact
- **Scaling**: 10x traffic = +$80/month
- **Reserved Instances**: Save 35% after 3 months
- **Optimization tips**: Start small, scale based on metrics

### 2. Architecture Diagram (`architecture-diagram.md`)
- **Network Flow**: User → Route 53 → ALB → ECS → RDS/Redis
- **Security Groups**: Least-privilege access rules
- **High Availability**: Multi-AZ options (+$40/month)
- **Monitoring**: CloudWatch dashboards and alerts

### 3. Terraform Structure (`terraform-structure.md`)
- **Official Modules**: VPC, RDS, ECS, ALB
- **Environment Separation**: prod/ and staging/
- **State Management**: S3 backend with DynamoDB locking
- **Bootstrap Script**: One-time setup for S3/DynamoDB

### 4. CI/CD Workflow (`cicd-workflow.md`)
- **Release Please**: Automated changelog and versioning
- **GitHub Actions**: Build, test, deploy
- **Deployment**: Rolling updates with health checks
- **Rollback**: Manual workflow for emergencies

## Implementation Roadmap

### Week 1: Infrastructure Setup (8-12 hours)

**Day 1-2: Prerequisites**
- [ ] Purchase domain name ($10-15/year)
- [ ] Create/verify AWS account
- [ ] Install Terraform locally
- [ ] Install AWS CLI
- [ ] Generate AWS access keys

**Day 3-4: Terraform Bootstrap**
- [ ] Run bootstrap script (S3 + DynamoDB)
- [ ] Create `terraform/environments/prod/terraform.tfvars`
- [ ] Generate strong database password
- [ ] Add all secrets to tfvars (gitignored)

**Day 5-6: Initial Deployment**
- [ ] `terraform init`
- [ ] `terraform plan` (review carefully)
- [ ] `terraform apply` (creates all resources)
- [ ] Verify outputs (ALB DNS, RDS endpoint, etc.)

**Day 7: DNS Configuration**
- [ ] Update domain registrar nameservers to Route 53
- [ ] Wait for ACM certificate validation (5-30 minutes)
- [ ] Test HTTPS access to ALB DNS

### Week 2: Application Deployment (6-8 hours)

**Day 1-2: GitHub Setup**
- [ ] Add GitHub secrets (AWS credentials, app secrets)
- [ ] Create Release Please config files
- [ ] Add GitHub Actions workflows
- [ ] Test workflows with dummy push

**Day 3: Docker Images**
- [ ] Push PHP image to ECR manually (first time)
- [ ] Push pyparser image to ECR manually
- [ ] Update ECS task definitions with image URIs
- [ ] Deploy services to ECS

**Day 4: Database Migration**
- [ ] Connect to RDS from local (SSH tunnel or bastion)
- [ ] Run `doctrine:migrations:migrate`
- [ ] Seed GTFS data: `app:gtfs:load`
- [ ] Verify tables created

**Day 5: Smoke Testing**
- [ ] Test ALB health check: `/api/realtime`
- [ ] Verify GTFS-RT parser writing to Redis
- [ ] Check scheduler running score-tick
- [ ] Test AI insight cache warming

**Day 6-7: Monitoring Setup**
- [ ] Create CloudWatch dashboard
- [ ] Configure alarms (CPU, memory, errors)
- [ ] Set up SNS topic for alerts
- [ ] Test Slack notifications

### Week 3: Production Readiness (4-6 hours)

**Day 1: Security Hardening**
- [ ] Review security group rules
- [ ] Enable RDS encryption (default)
- [ ] Configure backup retention
- [ ] Test disaster recovery (restore from snapshot)

**Day 2: Performance Tuning**
- [ ] Load test with artillery/k6
- [ ] Adjust ECS task sizes if needed
- [ ] Configure auto-scaling policies
- [ ] Verify cache hit rates

**Day 3: Documentation**
- [ ] Document runbooks (deployment, rollback, troubleshooting)
- [ ] Create on-call guide
- [ ] Document secrets rotation process
- [ ] Update CLAUDE.md with production URLs

**Day 4: Go Live**
- [ ] Announce launch
- [ ] Monitor metrics closely for 24 hours
- [ ] Fix any issues
- [ ] Celebrate! 🎉

### Ongoing: Phase 5 Preparation

**Week 4+: Data Collection**
- [ ] Verify arrival logs being collected
- [ ] Monitor database growth
- [ ] Set up automated reports
- [ ] Plan Phase 5 features

## Risk Assessment & Mitigation

### High-Priority Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Cost overruns | High | Medium | Set AWS Budget alerts, monitor daily |
| Data loss | Critical | Low | RDS automated backups, test restores |
| Deployment failures | Medium | Medium | Staging environment, rollback workflow |
| GTFS feed downtime | Medium | Medium | Fallback URLs, cached data |
| Security breach | Critical | Low | Security groups, IAM policies, secrets rotation |

### Cost Overrun Prevention

```bash
# Set up AWS Budget
aws budgets create-budget \
  --account-id $(aws sts get-caller-identity --query Account --output text) \
  --budget file://budget.json \
  --notifications-with-subscribers file://notifications.json

# budget.json
{
  "BudgetName": "MonthlyECSBudget",
  "BudgetLimit": {
    "Amount": "100",
    "Unit": "USD"
  },
  "TimeUnit": "MONTHLY",
  "BudgetType": "COST"
}

# Monitor daily
aws ce get-cost-and-usage \
  --time-period Start=2024-01-01,End=2024-01-31 \
  --granularity DAILY \
  --metrics BlendedCost
```

## Decision Matrix

### Should I deploy to production now?

✅ **Deploy Now If:**
- You have $100-150/month budget
- Need real production data for Phase 5
- Want to learn AWS ECS/Terraform
- Have 2-3 weeks for setup
- Comfortable with cloud operations

⏸️ **Wait If:**
- Budget constrained (<$80/month)
- Not ready for 24/7 uptime responsibility
- Need more development testing
- Prefer simpler deployment (consider DigitalOcean App Platform)

### Alternative: Cost-Optimized VPS

If AWS cost is prohibitive, consider:

**DigitalOcean App Platform**
- **Cost**: $20-40/month (all-inclusive)
- **Pros**: Simpler, cheaper, managed database
- **Cons**: Less scalable, fewer AWS services

**Linode/Hetzner VPS**
- **Cost**: $10-20/month
- **Pros**: Very cheap, full control
- **Cons**: Manual management, docker-compose only

**AWS Lightsail**
- **Cost**: $65-80/month (middle ground)
- **Pros**: Simpler than ECS, still AWS ecosystem
- **Cons**: Less Terraform support, limited features

## Key Terraform Commands

### Daily Operations

```bash
# Plan changes
terraform plan -out=tfplan

# Apply changes
terraform apply tfplan

# View outputs
terraform output -json

# Show current state
terraform show

# Destroy everything (careful!)
terraform destroy  # DANGER: Use only for teardown
```

### Troubleshooting

```bash
# Refresh state
terraform refresh

# Re-import resource
terraform import aws_instance.example i-1234567890abcdef0

# View logs
terraform show -json | jq

# Unlock state (if locked)
terraform force-unlock LOCK_ID
```

## GitHub Actions Tips

### Triggering Deployments

```bash
# Normal flow: merge release PR
1. Push to main
2. Release Please creates/updates release PR
3. Review changelog
4. Merge release PR → Triggers deployment

# Manual deployment
gh workflow run deploy-production.yml

# Rollback
gh workflow run rollback.yml -f version=v1.2.0

# View logs
gh run list
gh run view <run-id> --log
```

### Debugging Failed Deployments

```bash
# Check ECS deployment status
aws ecs describe-services \
  --cluster mind-the-wait-prod \
  --services mind-the-wait-prod-php \
  --query 'services[0].deployments'

# View task failures
aws ecs describe-tasks \
  --cluster mind-the-wait-prod \
  --tasks <task-arn>

# Check container logs
aws logs tail /ecs/mind-the-wait-prod/php --follow
```

## Success Metrics

### Week 1 (Infrastructure)
- [ ] All Terraform resources created successfully
- [ ] Domain resolves to ALB
- [ ] SSL certificate valid
- [ ] Can access ALB DNS via HTTPS

### Week 2 (Application)
- [ ] ECS tasks running healthy
- [ ] Database migrations completed
- [ ] GTFS data loaded (~50k records)
- [ ] API returns vehicle positions

### Week 3 (Production)
- [ ] Dashboard accessible at production URL
- [ ] Real-time scores updating every 30s
- [ ] AI insights cached and serving
- [ ] No 5xx errors in CloudWatch

### Month 1 (Stability)
- [ ] 99.5%+ uptime
- [ ] <2 second page load times
- [ ] 5+ days of performance data collected
- [ ] AWS costs under $100/month

## Emergency Contacts & Runbooks

### Critical Issues

**Service Down (5xx errors)**
1. Check CloudWatch logs for errors
2. Verify ECS tasks are running
3. Check RDS/Redis connectivity
4. Rollback to previous version if needed

**Database Full**
1. Check RDS storage metrics
2. Increase allocated storage (no downtime)
3. Run cleanup: `DELETE FROM arrival_logs WHERE date < NOW() - INTERVAL '1 year'`

**High Costs**
1. Check Cost Explorer for spike
2. Look for orphaned resources (NAT Gateway, volumes)
3. Reduce ECS task sizes
4. Consider Reserved Instances

**Data Loss**
1. Identify scope (all data vs partial)
2. Restore from RDS snapshot
3. Replay GTFS data load
4. Verify data integrity

## Cost Optimization Checklist

### Month 1
- [ ] Monitor daily costs in AWS Cost Explorer
- [ ] Verify no unexpected charges
- [ ] Check for unused resources
- [ ] Review CloudWatch Logs retention (7 days)

### Month 3
- [ ] Purchase RDS Reserved Instance (save 35%)
- [ ] Purchase ElastiCache Reserved Node (save 35%)
- [ ] Review ECR image lifecycle (delete old images)
- [ ] **Estimated savings**: $8-10/month

### Month 6
- [ ] Consider Fargate Savings Plan (save 20-50%)
- [ ] Evaluate Multi-AZ upgrade ($40 more for HA)
- [ ] Review and tune auto-scaling policies
- [ ] **Estimated savings**: $10-20/month

### Month 12
- [ ] Renew Reserved Instances
- [ ] Evaluate RDS storage (may need 30-40 GB)
- [ ] Consider multi-region backup
- [ ] Review year-over-year cost trends

## Support Resources

### AWS Documentation
- [ECS Best Practices](https://docs.aws.amazon.com/AmazonECS/latest/bestpracticesguide/intro.html)
- [RDS Backup and Restore](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/CHAP_CommonTasks.BackupRestore.html)
- [Cost Optimization](https://aws.amazon.com/architecture/cost-optimization/)

### Terraform
- [AWS Provider Docs](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)
- [Official Modules](https://registry.terraform.io/namespaces/terraform-aws-modules)

### Community
- [r/aws](https://reddit.com/r/aws)
- [AWS re:Post](https://repost.aws/)
- [Terraform Community](https://discuss.hashicorp.com/)

## Final Checklist Before Launch

### Infrastructure
- [ ] All Terraform resources deployed
- [ ] DNS configured and resolving
- [ ] SSL certificate active
- [ ] Security groups reviewed
- [ ] Backups configured and tested

### Application
- [ ] Docker images in ECR
- [ ] ECS services running
- [ ] Database migrated
- [ ] GTFS data loaded
- [ ] Secrets configured

### CI/CD
- [ ] GitHub secrets added
- [ ] Release Please configured
- [ ] Workflows tested
- [ ] Rollback procedure documented

### Monitoring
- [ ] CloudWatch dashboards created
- [ ] Alarms configured
- [ ] Budget alerts set up
- [ ] Slack notifications working

### Documentation
- [ ] Runbooks written
- [ ] On-call procedures defined
- [ ] Secrets documented (in password manager)
- [ ] Team trained

## Next Steps

1. **Review all documentation** in `docs/infrastructure/`
2. **Discuss budget** and get approval for ~$100/month
3. **Purchase domain name** via Namecheap, Google Domains, etc.
4. **Schedule 2-3 weeks** for implementation
5. **Create AWS account** or prepare existing account
6. **Begin Week 1 tasks** (Infrastructure Setup)

---

**Questions or concerns?** Review the risk assessment and decision matrix to ensure this deployment approach aligns with your goals and resources.

**Ready to proceed?** Start with Week 1, Day 1 tasks and work through systematically. The infrastructure will be ready for Phase 5 data collection immediately upon deployment.
