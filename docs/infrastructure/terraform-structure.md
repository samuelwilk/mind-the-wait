# Terraform Infrastructure Structure

## Project Layout

```
terraform/
├── README.md                       # Terraform setup instructions
├── .gitignore                      # Ignore .terraform/, *.tfstate
├── .terraform-version              # Specify Terraform version (1.6+)
│
├── environments/
│   ├── prod/
│   │   ├── main.tf                 # Root module for production
│   │   ├── variables.tf            # Environment-specific variables
│   │   ├── terraform.tfvars        # Variable values (gitignored)
│   │   ├── terraform.tfvars.example # Example values (committed)
│   │   ├── outputs.tf              # Outputs (ALB DNS, RDS endpoint)
│   │   └── backend.tf              # S3 backend configuration
│   │
│   └── staging/ (optional)
│       └── ... (same structure)
│
└── modules/
    ├── networking/
    │   ├── main.tf                 # VPC, subnets, security groups
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    ├── ecs-cluster/
    │   ├── main.tf                 # ECS cluster, task definitions
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    ├── ecs-service/
    │   ├── main.tf                 # ECS service, auto-scaling
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    ├── rds/
    │   ├── main.tf                 # RDS PostgreSQL instance
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    ├── elasticache/
    │   ├── main.tf                 # ElastiCache Redis cluster
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    ├── alb/
    │   ├── main.tf                 # Application Load Balancer
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    ├── dns/
    │   ├── main.tf                 # Route 53, ACM certificate
    │   ├── variables.tf
    │   ├── outputs.tf
    │   └── README.md
    │
    └── ecr/
        ├── main.tf                 # ECR repositories
        ├── variables.tf
        ├── outputs.tf
        └── README.md
```

## Terraform Backend Configuration

### S3 Backend for State Management

**terraform/environments/prod/backend.tf**
```hcl
terraform {
  backend "s3" {
    bucket         = "mind-the-wait-terraform-state"
    key            = "prod/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "mind-the-wait-terraform-locks"

    # Prevent concurrent modifications
    # DynamoDB table must exist with LockID (String) as primary key
  }

  required_version = ">= 1.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.5"
    }
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Environment = "production"
      Project     = "mind-the-wait"
      ManagedBy   = "terraform"
    }
  }
}
```

### Bootstrap Script (Run Once)

**terraform/bootstrap/bootstrap.sh**
```bash
#!/bin/bash
# Creates S3 bucket and DynamoDB table for Terraform state

AWS_REGION="us-east-1"
S3_BUCKET="mind-the-wait-terraform-state"
DYNAMODB_TABLE="mind-the-wait-terraform-locks"

# Create S3 bucket for state
aws s3 mb "s3://${S3_BUCKET}" --region "${AWS_REGION}"

# Enable versioning
aws s3api put-bucket-versioning \
  --bucket "${S3_BUCKET}" \
  --versioning-configuration Status=Enabled

# Enable encryption
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
aws s3api put-public-access-block \
  --bucket "${S3_BUCKET}" \
  --public-access-block-configuration \
    BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

# Create DynamoDB table for state locking
aws dynamodb create-table \
  --table-name "${DYNAMODB_TABLE}" \
  --attribute-definitions AttributeName=LockID,AttributeType=S \
  --key-schema AttributeName=LockID,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST \
  --region "${AWS_REGION}"

echo "✅ Terraform backend resources created"
echo "   S3 Bucket: ${S3_BUCKET}"
echo "   DynamoDB Table: ${DYNAMODB_TABLE}"
```

## Root Module (Production)

**terraform/environments/prod/main.tf**
```hcl
locals {
  project_name = "mind-the-wait"
  environment  = "prod"

  common_tags = {
    Environment = local.environment
    Project     = local.project_name
    ManagedBy   = "terraform"
  }
}

# Networking Module
module "networking" {
  source = "../../modules/networking"

  project_name = local.project_name
  environment  = local.environment
  vpc_cidr     = var.vpc_cidr

  availability_zones = var.availability_zones
  public_subnets     = var.public_subnets
  private_subnets    = var.private_subnets

  tags = local.common_tags
}

# ECR Repositories
module "ecr" {
  source = "../../modules/ecr"

  project_name    = local.project_name
  repository_names = ["php", "pyparser"]

  tags = local.common_tags
}

# RDS PostgreSQL
module "rds" {
  source = "../../modules/rds"

  project_name       = local.project_name
  environment        = local.environment
  instance_class     = var.rds_instance_class
  allocated_storage  = var.rds_allocated_storage
  engine_version     = "16.1"

  vpc_id                = module.networking.vpc_id
  subnet_ids            = module.networking.private_subnet_ids
  allowed_security_group_ids = [module.ecs_cluster.task_security_group_id]

  database_name = var.database_name
  master_username = var.database_username
  master_password = var.database_password # From tfvars (gitignored)

  backup_retention_period = 7
  multi_az               = var.rds_multi_az

  tags = local.common_tags
}

# ElastiCache Redis
module "elasticache" {
  source = "../../modules/elasticache"

  project_name   = local.project_name
  environment    = local.environment
  node_type      = var.redis_node_type
  engine_version = "7.0"

  vpc_id                     = module.networking.vpc_id
  subnet_ids                 = module.networking.private_subnet_ids
  allowed_security_group_ids = [module.ecs_cluster.task_security_group_id]

  tags = local.common_tags
}

# Application Load Balancer
module "alb" {
  source = "../../modules/alb"

  project_name = local.project_name
  environment  = local.environment

  vpc_id         = module.networking.vpc_id
  subnet_ids     = module.networking.public_subnet_ids
  certificate_arn = module.dns.certificate_arn

  health_check_path = "/api/realtime"

  tags = local.common_tags
}

# DNS & SSL Certificate
module "dns" {
  source = "../../modules/dns"

  domain_name  = var.domain_name
  alb_dns_name = module.alb.dns_name
  alb_zone_id  = module.alb.zone_id

  tags = local.common_tags
}

# ECS Cluster
module "ecs_cluster" {
  source = "../../modules/ecs-cluster"

  project_name = local.project_name
  environment  = local.environment

  vpc_id     = module.networking.vpc_id
  subnet_ids = module.networking.public_subnet_ids

  tags = local.common_tags
}

# ECS Service: PHP (Web App)
module "ecs_service_php" {
  source = "../../modules/ecs-service"

  project_name = local.project_name
  environment  = local.environment
  service_name = "php"

  cluster_id            = module.ecs_cluster.cluster_id
  task_security_group_id = module.ecs_cluster.task_security_group_id
  subnet_ids            = module.networking.public_subnet_ids

  cpu    = var.php_cpu
  memory = var.php_memory

  container_definitions = templatefile("${path.module}/task-definitions/php.json.tpl", {
    image              = "${module.ecr.repository_urls["php"]}:latest"
    log_group          = "/ecs/${local.project_name}-${local.environment}/php"
    region             = var.aws_region
    database_url       = "postgresql://${var.database_username}:${var.database_password}@${module.rds.endpoint}/${var.database_name}"
    redis_url          = "redis://${module.elasticache.endpoint}:6379"
    openai_api_key     = var.openai_api_key
    gtfs_static_url    = var.gtfs_static_url
    app_env            = "prod"
  })

  desired_count = var.php_desired_count

  # Auto-scaling
  min_capacity = 1
  max_capacity = 3
  cpu_target   = 70

  # Load balancer
  target_group_arn = module.alb.target_group_arn
  container_name   = "php"
  container_port   = 8080

  tags = local.common_tags
}

# ECS Service: Python Parser (GTFS-RT)
module "ecs_service_pyparser" {
  source = "../../modules/ecs-service"

  project_name = local.project_name
  environment  = local.environment
  service_name = "pyparser"

  cluster_id             = module.ecs_cluster.cluster_id
  task_security_group_id = module.ecs_cluster.task_security_group_id
  subnet_ids             = module.networking.public_subnet_ids

  cpu    = var.pyparser_cpu
  memory = var.pyparser_memory

  container_definitions = templatefile("${path.module}/task-definitions/pyparser.json.tpl", {
    image     = "${module.ecr.repository_urls["pyparser"]}:latest"
    log_group = "/ecs/${local.project_name}-${local.environment}/pyparser"
    region    = var.aws_region
    redis_url = "redis://${module.elasticache.endpoint}:6379"
  })

  desired_count = 1

  # No load balancer (internal service)
  target_group_arn = null

  tags = local.common_tags
}

# ECS Service: Scheduler (Cron Jobs)
module "ecs_service_scheduler" {
  source = "../../modules/ecs-service"

  project_name = local.project_name
  environment  = local.environment
  service_name = "scheduler"

  cluster_id             = module.ecs_cluster.cluster_id
  task_security_group_id = module.ecs_cluster.task_security_group_id
  subnet_ids             = module.networking.public_subnet_ids

  cpu    = var.scheduler_cpu
  memory = var.scheduler_memory

  container_definitions = templatefile("${path.module}/task-definitions/scheduler.json.tpl", {
    image          = "${module.ecr.repository_urls["php"]}:latest"
    log_group      = "/ecs/${local.project_name}-${local.environment}/scheduler"
    region         = var.aws_region
    database_url   = "postgresql://${var.database_username}:${var.database_password}@${module.rds.endpoint}/${var.database_name}"
    redis_url      = "redis://${module.elasticache.endpoint}:6379"
    openai_api_key = var.openai_api_key
    command        = ["php", "bin/console", "messenger:consume", "scheduler_default", "-vv"]
  })

  desired_count = 1

  # No load balancer
  target_group_arn = null

  tags = local.common_tags
}
```

**terraform/environments/prod/variables.tf**
```hcl
variable "aws_region" {
  description = "AWS region for resources"
  type        = string
  default     = "us-east-1"
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR block for VPC"
  type        = string
  default     = "10.0.0.0/16"
}

variable "availability_zones" {
  description = "Availability zones for subnets"
  type        = list(string)
  default     = ["us-east-1a", "us-east-1b"]
}

variable "public_subnets" {
  description = "CIDR blocks for public subnets"
  type        = list(string)
  default     = ["10.0.1.0/24", "10.0.2.0/24"]
}

variable "private_subnets" {
  description = "CIDR blocks for private subnets"
  type        = list(string)
  default     = ["10.0.10.0/24", "10.0.11.0/24"]
}

# RDS Variables
variable "rds_instance_class" {
  description = "RDS instance class"
  type        = string
  default     = "db.t4g.micro"
}

variable "rds_allocated_storage" {
  description = "RDS allocated storage in GB"
  type        = number
  default     = 20
}

variable "rds_multi_az" {
  description = "Enable RDS Multi-AZ"
  type        = bool
  default     = false
}

variable "database_name" {
  description = "Database name"
  type        = string
  default     = "mindthewait"
}

variable "database_username" {
  description = "Database master username"
  type        = string
  sensitive   = true
}

variable "database_password" {
  description = "Database master password"
  type        = string
  sensitive   = true
}

# Redis Variables
variable "redis_node_type" {
  description = "ElastiCache node type"
  type        = string
  default     = "cache.t4g.micro"
}

# ECS Task Sizing
variable "php_cpu" {
  description = "CPU units for PHP task (1024 = 1 vCPU)"
  type        = number
  default     = 512
}

variable "php_memory" {
  description = "Memory for PHP task in MB"
  type        = number
  default     = 1024
}

variable "php_desired_count" {
  description = "Desired count of PHP tasks"
  type        = number
  default     = 1
}

variable "pyparser_cpu" {
  type    = number
  default = 256
}

variable "pyparser_memory" {
  type    = number
  default = 512
}

variable "scheduler_cpu" {
  type    = number
  default = 256
}

variable "scheduler_memory" {
  type    = number
  default = 512
}

# Application Secrets
variable "openai_api_key" {
  description = "OpenAI API key for AI insights"
  type        = string
  sensitive   = true
}

variable "gtfs_static_url" {
  description = "GTFS static feed URL"
  type        = string
}
```

**terraform/environments/prod/terraform.tfvars.example**
```hcl
# Copy this file to terraform.tfvars and fill in real values
# terraform.tfvars is gitignored for security

aws_region  = "us-east-1"
domain_name = "transit.yourdomain.com"

# Database (use strong password!)
database_username = "mindthewait"
database_password = "CHANGE_ME_STRONG_PASSWORD"

# Application secrets
openai_api_key  = "sk-..."
gtfs_static_url = "https://apps2.saskatoon.ca/transit/..."

# Resource sizing (adjust based on load)
php_cpu           = 512
php_memory        = 1024
php_desired_count = 1

rds_instance_class    = "db.t4g.micro"
rds_allocated_storage = 20
rds_multi_az          = false

redis_node_type = "cache.t4g.micro"
```

**terraform/environments/prod/outputs.tf**
```hcl
output "alb_dns_name" {
  description = "DNS name of the Application Load Balancer"
  value       = module.alb.dns_name
}

output "rds_endpoint" {
  description = "RDS instance endpoint"
  value       = module.rds.endpoint
  sensitive   = true
}

output "redis_endpoint" {
  description = "ElastiCache Redis endpoint"
  value       = module.elasticache.endpoint
}

output "ecr_repository_urls" {
  description = "ECR repository URLs"
  value       = module.ecr.repository_urls
}

output "ecs_cluster_name" {
  description = "ECS cluster name"
  value       = module.ecs_cluster.cluster_name
}

output "certificate_validation_records" {
  description = "DNS records for ACM certificate validation"
  value       = module.dns.certificate_validation_records
}
```

## Module Examples

### Networking Module (Official VPC Module)

**terraform/modules/networking/main.tf**
```hcl
# Using official AWS VPC Terraform module
module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "~> 5.0"

  name = "${var.project_name}-${var.environment}-vpc"
  cidr = var.vpc_cidr

  azs             = var.availability_zones
  public_subnets  = var.public_subnets
  private_subnets = var.private_subnets

  enable_nat_gateway = false  # Cost optimization
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-vpc"
  })
}

# Security Group for ALB
resource "aws_security_group" "alb" {
  name_prefix = "${var.project_name}-${var.environment}-alb-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-alb-sg"
  })
}

# Security Group for ECS Tasks
resource "aws_security_group" "ecs_tasks" {
  name_prefix = "${var.project_name}-${var.environment}-ecs-tasks-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port       = 8080
    to_port         = 8080
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-ecs-tasks-sg"
  })
}

# Security Group for RDS
resource "aws_security_group" "rds" {
  name_prefix = "${var.project_name}-${var.environment}-rds-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs_tasks.id]
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-rds-sg"
  })
}

# Security Group for Redis
resource "aws_security_group" "redis" {
  name_prefix = "${var.project_name}-${var.environment}-redis-"
  vpc_id      = module.vpc.vpc_id

  ingress {
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [aws_security_group.ecs_tasks.id]
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-redis-sg"
  })
}
```

### RDS Module (Official RDS Module)

**terraform/modules/rds/main.tf**
```hcl
module "rds" {
  source  = "terraform-aws-modules/rds/aws"
  version = "~> 6.0"

  identifier = "${var.project_name}-${var.environment}"

  engine               = "postgres"
  engine_version       = var.engine_version
  family               = "postgres16"
  major_engine_version = "16"
  instance_class       = var.instance_class

  allocated_storage     = var.allocated_storage
  max_allocated_storage = 100  # Auto-scaling
  storage_encrypted     = true

  db_name  = var.database_name
  username = var.master_username
  password = var.master_password
  port     = 5432

  multi_az               = var.multi_az
  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [var.security_group_id]

  backup_retention_period = var.backup_retention_period
  backup_window           = "03:00-04:00"  # 3-4 AM UTC
  maintenance_window      = "sun:04:00-sun:05:00"

  enabled_cloudwatch_logs_exports = ["postgresql"]

  deletion_protection = true
  skip_final_snapshot = false
  final_snapshot_identifier = "${var.project_name}-${var.environment}-final-snapshot"

  tags = var.tags
}

resource "aws_db_subnet_group" "main" {
  name       = "${var.project_name}-${var.environment}-db-subnet-group"
  subnet_ids = var.subnet_ids

  tags = var.tags
}
```

## Usage Instructions

### Initial Setup

```bash
# 1. Install Terraform
brew install terraform  # macOS
# or download from terraform.io

# 2. Bootstrap backend resources (run once)
cd terraform/bootstrap
chmod +x bootstrap.sh
./bootstrap.sh

# 3. Configure production environment
cd ../environments/prod
cp terraform.tfvars.example terraform.tfvars
# Edit terraform.tfvars with real values

# 4. Initialize Terraform
terraform init

# 5. Plan deployment
terraform plan -out=tfplan

# 6. Review plan, then apply
terraform apply tfplan
```

### Typical Workflow

```bash
# Make infrastructure changes
vim main.tf

# Format code
terraform fmt -recursive

# Validate
terraform validate

# Plan changes
terraform plan -out=tfplan

# Apply changes
terraform apply tfplan

# View outputs
terraform output
```

### Cost Estimation

```bash
# Install Infracost
brew install infracost

# Estimate costs
infracost breakdown --path .

# Compare changes
infracost diff --path .
```

## Next Steps

1. Review cost estimation document
2. Purchase domain name
3. Create AWS account or use existing
4. Set up GitHub secrets
5. Implement Terraform modules
6. Configure GitHub Actions CI/CD
7. Deploy and test
