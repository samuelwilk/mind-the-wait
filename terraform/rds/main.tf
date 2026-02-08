# AWS RDS Configuration for Mind-the-Wait
# This provides the PostgreSQL database accessible from Hetzner VPS

terraform {
  required_version = ">= 1.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  backend "s3" {
    bucket         = "mind-the-wait-terraform-state"
    key            = "rds/terraform.tfstate"
    region         = "ca-central-1"
    dynamodb_table = "mind-the-wait-terraform-locks"
    encrypt        = true
  }
}

provider "aws" {
  region = var.aws_region
}

locals {
  project_name = "mind-the-wait"

  common_tags = {
    Project   = local.project_name
    ManagedBy = "terraform"
  }
}

# VPC for RDS
resource "aws_vpc" "this" {
  cidr_block           = var.vpc_cidr
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds-vpc"
  })
}

# Internet Gateway (required for public RDS access)
resource "aws_internet_gateway" "this" {
  vpc_id = aws_vpc.this.id

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds-igw"
  })
}

# Public Subnets (RDS needs at least 2 AZs)
resource "aws_subnet" "public" {
  count                   = length(var.availability_zones)
  vpc_id                  = aws_vpc.this.id
  cidr_block              = var.public_subnets[count.index]
  availability_zone       = var.availability_zones[count.index]
  map_public_ip_on_launch = true

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds-public-${count.index + 1}"
  })
}

# Route Table for public subnets
resource "aws_route_table" "public" {
  vpc_id = aws_vpc.this.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.this.id
  }

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds-public-rt"
  })
}

# Associate subnets with route table
resource "aws_route_table_association" "public" {
  count          = length(var.availability_zones)
  subnet_id      = aws_subnet.public[count.index].id
  route_table_id = aws_route_table.public.id
}

# Security Group for RDS
resource "aws_security_group" "rds" {
  name_prefix = "${local.project_name}-rds-"
  description = "Security group for RDS PostgreSQL"
  vpc_id      = aws_vpc.this.id

  ingress {
    description = "PostgreSQL from Hetzner VPS"
    from_port   = 5432
    to_port     = 5432
    protocol    = "tcp"
    cidr_blocks = ["${var.hetzner_vps_ip}/32"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds-sg"
  })

  lifecycle {
    create_before_destroy = true
  }
}

# DB Subnet Group
resource "aws_db_subnet_group" "this" {
  name       = "${local.project_name}-rds-subnet-group"
  subnet_ids = aws_subnet.public[*].id

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds-subnet-group"
  })
}

# RDS PostgreSQL Instance (restored from snapshot)
resource "aws_db_instance" "this" {
  identifier = "${local.project_name}-rds"

  # Restore from snapshot
  snapshot_identifier = var.snapshot_identifier

  instance_class        = var.instance_class
  max_allocated_storage = 100
  storage_type          = "gp3"

  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [aws_security_group.rds.id]
  publicly_accessible    = true

  multi_az                = false
  backup_retention_period = 7
  backup_window           = "03:00-04:00"
  maintenance_window      = "sun:04:00-sun:05:00"

  enabled_cloudwatch_logs_exports = ["postgresql"]

  deletion_protection       = true
  skip_final_snapshot       = false
  final_snapshot_identifier = "${local.project_name}-rds-final-${formatdate("YYYY-MM-DD", timestamp())}"

  tags = merge(local.common_tags, {
    Name = "${local.project_name}-rds"
  })

  lifecycle {
    ignore_changes = [
      final_snapshot_identifier,
      snapshot_identifier
    ]
  }
}
