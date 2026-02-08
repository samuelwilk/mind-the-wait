# RDS Module - PostgreSQL Database

locals {
  name_suffix = var.identifier_suffix != "" ? "-${var.identifier_suffix}" : ""
  base_name   = "${var.project_name}-${var.environment}${local.name_suffix}"
}

resource "aws_db_subnet_group" "this" {
  name       = "${local.base_name}-db-subnet-group"
  subnet_ids = var.subnet_ids

  tags = merge(var.tags, {
    Name = "${local.base_name}-db-subnet-group"
  })
}

resource "aws_db_instance" "this" {
  identifier = local.base_name

  # When restoring from snapshot, these are inherited from the snapshot
  snapshot_identifier = var.snapshot_identifier
  engine              = var.snapshot_identifier == null ? "postgres" : null
  engine_version      = var.snapshot_identifier == null ? var.engine_version : null
  allocated_storage   = var.snapshot_identifier == null ? var.allocated_storage : null
  db_name             = var.snapshot_identifier == null ? var.database_name : null
  username            = var.snapshot_identifier == null ? var.master_username : null
  password            = var.snapshot_identifier == null ? var.master_password : null

  instance_class        = var.instance_class
  max_allocated_storage = var.max_allocated_storage
  storage_type          = "gp3"

  port = 5432

  multi_az               = var.multi_az
  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [var.security_group_id]
  publicly_accessible    = var.publicly_accessible

  backup_retention_period = var.backup_retention_period
  backup_window           = "03:00-04:00"  # 3-4 AM local time
  maintenance_window      = "sun:04:00-sun:05:00"  # Sunday 4-5 AM

  enabled_cloudwatch_logs_exports = ["postgresql"]

  deletion_protection       = true
  skip_final_snapshot       = false
  final_snapshot_identifier = "${local.base_name}-final-snapshot-${formatdate("YYYY-MM-DD-hhmm", timestamp())}"

  tags = merge(var.tags, {
    Name = "${local.base_name}-db"
  })

  lifecycle {
    ignore_changes = [
      final_snapshot_identifier,
      snapshot_identifier
    ]
  }
}
