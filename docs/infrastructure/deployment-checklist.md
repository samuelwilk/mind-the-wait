# AWS Deployment Checklist - mind-the-wait.ca

## Status: In Progress

**Domain**: mind-the-wait.ca ✅ (Purchased)
**Target Cost**: $70/month (Budget configuration)
**Estimated Timeline**: 3 weeks

---

## Week 1: Infrastructure Setup (Current)

### Day 1-2: Prerequisites ⏳ IN PROGRESS

#### 1.1 AWS Account Setup
- [ ] Create AWS account (or verify existing account access)
  - Go to https://aws.amazon.com/
  - Sign up or sign in
  - Verify email address
  - Add payment method

- [ ] Enable MFA (Multi-Factor Authentication) for security
  - Go to IAM → My Security Credentials
  - Activate MFA on your root account
  - Use Google Authenticator or similar app

- [ ] Create IAM user for Terraform
  ```bash
  # In AWS Console:
  # 1. Go to IAM → Users → Add User
  # 2. Username: terraform-admin
  # 3. Access type: Programmatic access ✓
  # 4. Attach policies: AdministratorAccess (for initial setup)
  # 5. Download CSV with access keys
  ```

- [ ] Set AWS Budget Alert
  ```bash
  # In AWS Console:
  # 1. Go to AWS Billing → Budgets
  # 2. Create budget: Monthly cost budget
  # 3. Amount: $100
  # 4. Alerts: 80% ($80), 100% ($100), 120% ($120)
  # 5. Email: your-email@example.com
  ```

#### 1.2 Install Required Tools (macOS)

- [ ] Install Homebrew (if not already installed)
  ```bash
  /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
  ```

- [ ] Install Terraform
  ```bash
  brew tap hashicorp/tap
  brew install hashicorp/tap/terraform

  # Verify installation
  terraform version
  # Expected: Terraform v1.6+ or higher
  ```

- [ ] Install AWS CLI
  ```bash
  brew install awscli

  # Verify installation
  aws --version
  # Expected: aws-cli/2.x.x
  ```

- [ ] Configure AWS CLI credentials
  ```bash
  aws configure
  # AWS Access Key ID: (from CSV downloaded above)
  # AWS Secret Access Key: (from CSV)
  # Default region name: us-east-1
  # Default output format: json

  # Test connection
  aws sts get-caller-identity
  # Should return your account ID and user ARN
  ```

#### 1.3 Generate Strong Passwords

- [ ] Generate database password (32+ characters)
  ```bash
  # Use a password manager (1Password, Bitwarden, LastPass)
  # OR generate with OpenSSL:
  openssl rand -base64 32

  # Save this securely - you'll need it for terraform.tfvars
  ```

- [ ] Get OpenAI API key
  ```bash
  # 1. Go to https://platform.openai.com/api-keys
  # 2. Click "Create new secret key"
  # 3. Name: "mind-the-wait-production"
  # 4. Copy and save securely
  ```

**Checkpoint**: Do you have all prerequisites installed? Test with:
```bash
terraform version && aws sts get-caller-identity
```

---

### Day 3-4: Terraform Configuration ⏳ PENDING

#### 2.1 Update Budget Configuration

- [ ] Navigate to Terraform directory
  ```bash
  cd terraform/environments/prod
  ```

- [ ] Copy budget template
  ```bash
  cp terraform.tfvars.budget terraform.tfvars
  ```

- [ ] Edit terraform.tfvars with your values
  ```bash
  vim terraform.tfvars
  # OR use your preferred editor
  ```

- [ ] Update these values:
  ```hcl
  domain_name = "mind-the-wait.ca"  # ✅ Your domain

  database_password = "YOUR_32_CHAR_PASSWORD_HERE"  # From step 1.3

  openai_api_key = "sk-proj-..."  # From step 1.3

  gtfs_static_url = "https://apps2.saskatoon.ca/transit/..."  # Verify this URL still works

  # Everything else can stay as default for budget config
  ```

- [ ] Verify no secrets in git
  ```bash
  # terraform.tfvars should be in .gitignore already
  git status
  # Should NOT show terraform.tfvars as a change
  ```

**Checkpoint**: Your terraform.tfvars file is configured with real secrets?

---

### Day 5-6: Bootstrap Terraform Backend ⏳ PENDING

#### 3.1 Create Bootstrap Resources

Terraform needs an S3 bucket and DynamoDB table to store state and prevent concurrent modifications.

- [ ] Create bootstrap script
  ```bash
  cd terraform
  mkdir -p bootstrap
  ```

- [ ] Create `terraform/bootstrap/bootstrap.sh`:
  ```bash
  #!/bin/bash
  # Bootstrap Terraform backend resources

  set -e

  AWS_REGION="us-east-1"
  S3_BUCKET="mind-the-wait-terraform-state"
  DYNAMODB_TABLE="mind-the-wait-terraform-locks"

  echo "🚀 Bootstrapping Terraform backend..."
  echo "   Region: ${AWS_REGION}"
  echo "   S3 Bucket: ${S3_BUCKET}"
  echo "   DynamoDB Table: ${DYNAMODB_TABLE}"
  echo ""

  # Create S3 bucket
  echo "Creating S3 bucket..."
  aws s3 mb "s3://${S3_BUCKET}" --region "${AWS_REGION}" || echo "Bucket already exists"

  # Enable versioning
  echo "Enabling versioning..."
  aws s3api put-bucket-versioning \
    --bucket "${S3_BUCKET}" \
    --versioning-configuration Status=Enabled

  # Enable encryption
  echo "Enabling encryption..."
  aws s3api put-bucket-encryption \
    --bucket "${S3_BUCKET}" \
    --server-side-encryption-configuration '{
      "Rules": [{
        "ApplyServerSideEncryptionByDefault": {
          "SSEAlgorithm": "AES256"
        }
      }]
    }'

  # Block public access
  echo "Blocking public access..."
  aws s3api put-public-access-block \
    --bucket "${S3_BUCKET}" \
    --public-access-block-configuration \
      BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

  # Create DynamoDB table
  echo "Creating DynamoDB table..."
  aws dynamodb create-table \
    --table-name "${DYNAMODB_TABLE}" \
    --attribute-definitions AttributeName=LockID,AttributeType=S \
    --key-schema AttributeName=LockID,KeyType=HASH \
    --billing-mode PAY_PER_REQUEST \
    --region "${AWS_REGION}" || echo "Table already exists"

  echo ""
  echo "✅ Bootstrap complete!"
  echo ""
  echo "Backend resources created:"
  echo "  📦 S3 Bucket: ${S3_BUCKET}"
  echo "  🔒 DynamoDB Table: ${DYNAMODB_TABLE}"
  echo ""
  echo "Next step: cd ../environments/prod && terraform init"
  ```

- [ ] Make script executable and run
  ```bash
  chmod +x bootstrap.sh
  ./bootstrap.sh
  ```

- [ ] Verify resources created
  ```bash
  # Check S3 bucket
  aws s3 ls | grep mind-the-wait-terraform-state

  # Check DynamoDB table
  aws dynamodb describe-table --table-name mind-the-wait-terraform-locks --query 'Table.TableStatus'
  # Expected: "ACTIVE"
  ```

**Checkpoint**: S3 bucket and DynamoDB table exist?

---

### Day 7: Initial Terraform Deployment ⏳ PENDING

⚠️ **IMPORTANT**: This step will create AWS resources and start incurring costs (~$70/month)

#### 4.1 Initialize Terraform

- [ ] Navigate to production environment
  ```bash
  cd terraform/environments/prod
  ```

- [ ] Initialize Terraform (downloads providers, sets up backend)
  ```bash
  terraform init

  # Expected output:
  # Initializing the backend...
  # Successfully configured the backend "s3"!
  # Terraform has been successfully initialized!
  ```

#### 4.2 Create Terraform Plan

- [ ] Run plan to preview changes
  ```bash
  terraform plan -out=tfplan

  # This will take 1-2 minutes
  # Review the output carefully!
  ```

- [ ] Review what will be created
  ```bash
  # Look for these resources (should be ~50-60 resources):
  # - aws_vpc.main
  # - aws_subnet.public[0,1]
  # - aws_subnet.private[0,1]
  # - aws_security_group.* (multiple)
  # - aws_lb.main
  # - aws_db_instance.main
  # - aws_elasticache_cluster.main
  # - aws_ecs_cluster.main
  # - aws_ecs_service.* (php, pyparser, scheduler)
  # - aws_route53_zone.main
  # - aws_acm_certificate.main
  # - etc.

  # Cost estimate:
  # Plan: 60 to add, 0 to change, 0 to destroy.
  ```

- [ ] **DECISION POINT**: Are you ready to create resources?
  - This will start incurring ~$70/month in costs
  - Resources will be created in us-east-1
  - You can always destroy later with `terraform destroy`

#### 4.3 Apply Terraform Plan

- [ ] Apply the plan (creates all resources)
  ```bash
  terraform apply tfplan

  # This will take 15-20 minutes!
  # - VPC, subnets, security groups: 2-3 min
  # - RDS database: 5-8 min
  # - ElastiCache Redis: 3-5 min
  # - ALB: 2-3 min
  # - ECS cluster/services: 5-7 min
  # - Route 53, ACM certificate: 1-2 min

  # Grab a coffee! ☕
  ```

- [ ] Wait for completion
  ```bash
  # Expected final output:
  # Apply complete! Resources: 60 added, 0 changed, 0 destroyed.
  #
  # Outputs:
  # alb_dns_name = "mind-the-wait-prod-alb-1234567890.us-east-1.elb.amazonaws.com"
  # certificate_validation_records = { ... }
  # ecs_cluster_name = "mind-the-wait-prod"
  # ...
  ```

- [ ] Save outputs
  ```bash
  terraform output -json > outputs.json
  cat outputs.json
  ```

**Checkpoint**: All resources created successfully? No errors?

---

## Week 2: DNS Configuration & Application Deployment ⏳ PENDING

### Day 8: Configure DNS

#### 5.1 Update Domain Nameservers

- [ ] Get Route 53 nameservers
  ```bash
  terraform output certificate_validation_records
  # Copy the nameserver values (ns-xxxx.awsdns-xx.org, etc.)
  ```

- [ ] Update domain registrar (where you bought mind-the-wait.ca)
  ```bash
  # 1. Log into your domain registrar
  # 2. Go to DNS settings for mind-the-wait.ca
  # 3. Replace nameservers with Route 53 nameservers
  # 4. Save changes
  #
  # ⚠️ DNS propagation takes 10 minutes to 48 hours
  # Usually works within 1-2 hours
  ```

- [ ] Verify DNS propagation
  ```bash
  # Wait 30 minutes, then test:
  dig mind-the-wait.ca NS

  # Should return Route 53 nameservers
  # If not, wait longer and try again
  ```

#### 5.2 Wait for SSL Certificate

- [ ] Check ACM certificate status
  ```bash
  aws acm list-certificates --region us-east-1

  # Look for Status: ISSUED
  # If Status: PENDING_VALIDATION, wait for DNS to propagate
  ```

- [ ] Once DNS propagates, certificate auto-validates (5-30 min)
  ```bash
  # AWS will automatically validate via DNS
  # No action needed, just wait
  ```

**Checkpoint**: SSL certificate status = ISSUED?

---

### Day 9-10: Build and Push Docker Images ⏳ PENDING

#### 6.1 Get ECR Repository URLs

- [ ] Get ECR URLs from Terraform
  ```bash
  cd terraform/environments/prod
  terraform output ecr_repository_urls

  # Example output:
  # {
  #   "php" = "123456789012.dkr.ecr.us-east-1.amazonaws.com/mind-the-wait/php"
  #   "pyparser" = "123456789012.dkr.ecr.us-east-1.amazonaws.com/mind-the-wait/pyparser"
  # }
  ```

#### 6.2 Login to ECR

- [ ] Authenticate Docker with ECR
  ```bash
  aws ecr get-login-password --region us-east-1 | \
    docker login --username AWS --password-stdin \
    123456789012.dkr.ecr.us-east-1.amazonaws.com

  # Expected: Login Succeeded
  ```

#### 6.3 Build and Push PHP Image

- [ ] Build PHP image
  ```bash
  cd /Users/sam/Repos/mind-the-wait

  docker build -t mind-the-wait-php:latest .
  ```

- [ ] Tag and push to ECR
  ```bash
  # Get your ECR URL from step 6.1
  ECR_PHP="123456789012.dkr.ecr.us-east-1.amazonaws.com/mind-the-wait/php"

  docker tag mind-the-wait-php:latest ${ECR_PHP}:latest
  docker tag mind-the-wait-php:latest ${ECR_PHP}:v0.1.0

  docker push ${ECR_PHP}:latest
  docker push ${ECR_PHP}:v0.1.0
  ```

#### 6.4 Build and Push Python Parser Image

- [ ] Build pyparser image
  ```bash
  cd pyparser
  docker build -t mind-the-wait-pyparser:latest .
  cd ..
  ```

- [ ] Tag and push to ECR
  ```bash
  ECR_PYPARSER="123456789012.dkr.ecr.us-east-1.amazonaws.com/mind-the-wait/pyparser"

  docker tag mind-the-wait-pyparser:latest ${ECR_PYPARSER}:latest
  docker tag mind-the-wait-pyparser:latest ${ECR_PYPARSER}:v0.1.0

  docker push ${ECR_PYPARSER}:latest
  docker push ${ECR_PYPARSER}:v0.1.0
  ```

**Checkpoint**: Both images pushed to ECR?

---

### Day 11: Deploy Application to ECS ⏳ PENDING

#### 7.1 Update ECS Task Definitions

The task definitions need to reference your ECR images. Terraform should have created them already, but we need to update with actual image URIs.

- [ ] Force new ECS deployment
  ```bash
  # This will pull the :latest images from ECR
  aws ecs update-service \
    --cluster mind-the-wait-prod \
    --service mind-the-wait-prod-php \
    --force-new-deployment

  aws ecs update-service \
    --cluster mind-the-wait-prod \
    --service mind-the-wait-prod-pyparser \
    --force-new-deployment

  aws ecs update-service \
    --cluster mind-the-wait-prod \
    --service mind-the-wait-prod-scheduler \
    --force-new-deployment
  ```

- [ ] Wait for services to become stable (5-10 min)
  ```bash
  aws ecs wait services-stable \
    --cluster mind-the-wait-prod \
    --services mind-the-wait-prod-php

  # Repeat for other services
  ```

#### 7.2 Run Database Migrations

- [ ] Connect to ECS task to run migrations
  ```bash
  # Get task ARN
  TASK_ARN=$(aws ecs list-tasks \
    --cluster mind-the-wait-prod \
    --service-name mind-the-wait-prod-php \
    --query 'taskArns[0]' \
    --output text)

  # Enable ECS Exec (if not already enabled)
  aws ecs execute-command \
    --cluster mind-the-wait-prod \
    --task ${TASK_ARN} \
    --container php \
    --interactive \
    --command "/bin/sh"

  # Inside container:
  php bin/console doctrine:migrations:migrate --no-interaction
  exit
  ```

#### 7.3 Load GTFS Data

- [ ] Seed GTFS static data
  ```bash
  # Same process as migrations
  aws ecs execute-command \
    --cluster mind-the-wait-prod \
    --task ${TASK_ARN} \
    --container php \
    --interactive \
    --command "/bin/sh"

  # Inside container:
  php bin/console app:gtfs:load
  # This will take 2-5 minutes
  exit
  ```

**Checkpoint**: Services running? Database migrated? GTFS data loaded?

---

### Day 12: Verify Application Works ⏳ PENDING

#### 8.1 Test ALB Endpoint

- [ ] Get ALB DNS name
  ```bash
  cd terraform/environments/prod
  terraform output alb_dns_name

  # Example: mind-the-wait-prod-alb-1234567890.us-east-1.elb.amazonaws.com
  ```

- [ ] Test health check endpoint
  ```bash
  ALB_DNS="mind-the-wait-prod-alb-1234567890.us-east-1.elb.amazonaws.com"

  curl https://${ALB_DNS}/api/realtime

  # Expected: JSON response with vehicle data
  # Or empty array if no vehicles currently running
  ```

#### 8.2 Test Domain

- [ ] Test your domain (if DNS has propagated)
  ```bash
  curl https://mind-the-wait.ca/api/realtime

  # If this works, your site is live! 🎉
  ```

- [ ] Open in browser
  ```bash
  open https://mind-the-wait.ca

  # Should see your dashboard!
  ```

**Checkpoint**: Application accessible via https://mind-the-wait.ca?

---

## Week 3: CI/CD & Production Readiness ⏳ PENDING

### Day 13-14: Configure GitHub Actions

#### 9.1 Add GitHub Secrets

- [ ] Go to GitHub repo → Settings → Secrets and variables → Actions

- [ ] Add these secrets:
  ```
  AWS_ACCESS_KEY_ID = (IAM user access key)
  AWS_SECRET_ACCESS_KEY = (IAM user secret)
  AWS_REGION = us-east-1
  ECR_REPOSITORY_PHP = mind-the-wait/php
  ECR_REPOSITORY_PYPARSER = mind-the-wait/pyparser
  ECS_CLUSTER = mind-the-wait-prod
  ECS_SERVICE_PHP = mind-the-wait-prod-php
  ECS_SERVICE_PYPARSER = mind-the-wait-prod-pyparser
  ECS_SERVICE_SCHEDULER = mind-the-wait-prod-scheduler
  ```

#### 9.2 Create GitHub Workflows

- [ ] Create `.github/workflows/release-please.yml`
  ```bash
  # Copy from docs/infrastructure/cicd-workflow.md
  ```

- [ ] Create `.github/workflows/test.yml`
  ```bash
  # Copy from docs/infrastructure/cicd-workflow.md
  ```

- [ ] Create `.github/workflows/deploy-production.yml`
  ```bash
  # Copy from docs/infrastructure/cicd-workflow.md
  ```

- [ ] Commit and push workflows
  ```bash
  git add .github/workflows/
  git commit -m "ci: add GitHub Actions workflows for testing and deployment"
  git push origin main
  ```

#### 9.3 Test CI/CD

- [ ] Make a test change
  ```bash
  # Make a small change to trigger CI
  echo "# Production deployment" >> README.md
  git add README.md
  git commit -m "docs: add production deployment note"
  git push origin main
  ```

- [ ] Verify workflows run
  ```bash
  # Go to GitHub → Actions tab
  # Should see "Test & Lint" workflow running
  ```

**Checkpoint**: CI/CD workflows running successfully?

---

### Day 15-16: Monitoring & Alerts

#### 10.1 Create CloudWatch Dashboard

- [ ] Create dashboard via AWS Console
  ```bash
  # Or create via Terraform (add monitoring module)
  ```

- [ ] Add metrics:
  - ECS CPU/Memory utilization
  - ALB request count, latency, errors
  - RDS connections, CPU, storage
  - Redis cache hits/misses

#### 10.2 Set Up Alarms

- [ ] Create SNS topic for alerts
  ```bash
  aws sns create-topic --name mind-the-wait-prod-alerts

  aws sns subscribe \
    --topic-arn arn:aws:sns:us-east-1:123456789012:mind-the-wait-prod-alerts \
    --protocol email \
    --notification-endpoint your-email@example.com

  # Check email and confirm subscription
  ```

- [ ] Create CloudWatch alarms
  - ECS CPU > 70%
  - ECS Memory > 80%
  - ALB 5xx errors > 10/min
  - RDS storage < 20%

**Checkpoint**: Receiving test alerts?

---

### Day 17-18: Final Testing & Optimization

#### 11.1 Load Testing

- [ ] Test application under load
  ```bash
  # Use a tool like Apache Bench
  ab -n 1000 -c 10 https://mind-the-wait.ca/
  ```

- [ ] Monitor CloudWatch during test
  - Check CPU/Memory stay under 70%
  - Check response times stay under 2s

#### 11.2 Security Review

- [ ] Verify security groups
  - Only necessary ports open
  - No public access to RDS/Redis
  - ALB only accepts 80/443

- [ ] Enable AWS CloudTrail (optional, for audit logs)
  - Tracks all API calls
  - Useful for security investigations

#### 11.3 Cost Review

- [ ] Check AWS Cost Explorer
  ```bash
  # Go to AWS Console → Cost Management → Cost Explorer
  # Review last 7 days of costs
  # Should be ~$2-3/day ($70/month)
  ```

- [ ] Set up cost anomaly detection
  ```bash
  # AWS Console → Cost Management → Cost Anomaly Detection
  # Create monitor for total costs
  ```

**Checkpoint**: Everything working smoothly? Costs as expected?

---

### Day 19-21: Documentation & Handoff

#### 12.1 Document Production Setup

- [ ] Update CLAUDE.md with production details
  - Production URL
  - AWS resources
  - Monitoring links
  - Emergency procedures

- [ ] Create runbooks
  - How to deploy
  - How to rollback
  - How to check logs
  - Common troubleshooting

#### 12.2 Create Backup/Recovery Plan

- [ ] Test RDS snapshot restore
  ```bash
  # Create manual snapshot
  aws rds create-db-snapshot \
    --db-instance-identifier mind-the-wait-prod \
    --db-snapshot-identifier mind-the-wait-prod-test-snapshot

  # Verify snapshot creation
  aws rds describe-db-snapshots \
    --db-snapshot-identifier mind-the-wait-prod-test-snapshot

  # Delete test snapshot after verification
  aws rds delete-db-snapshot \
    --db-snapshot-identifier mind-the-wait-prod-test-snapshot
  ```

#### 12.3 Celebrate! 🎉

- [ ] **Production is live!**
  - https://mind-the-wait.ca
  - AWS infrastructure deployed
  - CI/CD automated
  - Monitoring active
  - Ready for Phase 5 data collection!

---

## Emergency Contacts & Resources

### If Something Goes Wrong

**Service Down:**
```bash
# Check ECS service status
aws ecs describe-services --cluster mind-the-wait-prod --services mind-the-wait-prod-php

# Check CloudWatch logs
aws logs tail /ecs/mind-the-wait-prod/php --follow

# Force redeploy
aws ecs update-service --cluster mind-the-wait-prod --service mind-the-wait-prod-php --force-new-deployment
```

**Database Issues:**
```bash
# Check RDS status
aws rds describe-db-instances --db-instance-identifier mind-the-wait-prod

# Restore from snapshot (last resort)
# Go to RDS Console → Snapshots → Restore
```

**High Costs:**
```bash
# Check what's expensive
aws ce get-cost-and-usage --time-period Start=2024-01-01,End=2024-01-31 --granularity DAILY --metrics BlendedCost

# Common culprits: NAT Gateway, unattached EBS, old ECR images
```

### Support Resources

- AWS Support: https://console.aws.amazon.com/support/
- Terraform AWS Provider: https://registry.terraform.io/providers/hashicorp/aws/
- Project docs: `docs/infrastructure/`

---

## Current Status

- [x] Domain purchased: mind-the-wait.ca
- [ ] AWS account configured
- [ ] Terraform initialized
- [ ] Infrastructure deployed
- [ ] Application running
- [ ] CI/CD configured
- [ ] Monitoring active

**Next Step**: Day 1-2 Prerequisites (see above)
