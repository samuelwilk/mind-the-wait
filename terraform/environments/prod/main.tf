# Main Terraform Configuration for mind-the-wait Production

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

  project_name       = local.project_name
  environment        = local.environment
  vpc_cidr           = var.vpc_cidr
  availability_zones = var.availability_zones
  public_subnets     = var.public_subnets
  private_subnets    = var.private_subnets

  tags = local.common_tags
}

# ECR Repositories
module "ecr" {
  source = "../../modules/ecr"

  project_name     = local.project_name
  repository_names = ["php", "pyparser"]

  tags = local.common_tags
}

# RDS PostgreSQL
module "rds" {
  source = "../../modules/rds"

  project_name            = local.project_name
  environment             = local.environment
  instance_class          = var.rds_instance_class
  allocated_storage       = var.rds_allocated_storage
  max_allocated_storage   = 100
  database_name           = var.database_name
  master_username         = var.database_username
  master_password         = var.database_password
  multi_az                = var.rds_multi_az
  backup_retention_period = 7
  subnet_ids              = module.networking.private_subnet_ids
  security_group_id       = module.networking.rds_security_group_id

  tags = local.common_tags
}

# ElastiCache Redis
module "elasticache" {
  source = "../../modules/elasticache"

  project_name      = local.project_name
  environment       = local.environment
  node_type         = var.redis_node_type
  subnet_ids        = module.networking.private_subnet_ids
  security_group_id = module.networking.redis_security_group_id

  tags = local.common_tags
}

# DNS and SSL Certificate
module "dns" {
  source = "../../modules/dns"

  domain_name  = var.domain_name
  alb_dns_name = module.alb.alb_dns_name
  alb_zone_id  = module.alb.alb_zone_id

  tags = local.common_tags
}

# Application Load Balancer
module "alb" {
  source = "../../modules/alb"

  project_name      = local.project_name
  environment       = local.environment
  vpc_id            = module.networking.vpc_id
  subnet_ids        = module.networking.public_subnet_ids
  security_group_id = module.networking.alb_security_group_id
  certificate_arn   = module.dns.certificate_arn
  health_check_path = "/api/realtime"

  tags = local.common_tags
}

# ECS Cluster
module "ecs_cluster" {
  source = "../../modules/ecs-cluster"

  project_name       = local.project_name
  environment        = local.environment
  log_retention_days = var.log_retention_days

  tags = local.common_tags
}

# Build connection strings
locals {
  database_url           = "postgresql://${var.database_username}:${var.database_password}@${module.rds.address}:${module.rds.port}/${var.database_name}"
  redis_url              = "redis://${module.elasticache.endpoint}:${module.elasticache.port}"
  messenger_transport_dsn = "redis://${module.elasticache.endpoint}:${module.elasticache.port}/messages"
}

# ECS Service: PHP (Web Application)
module "ecs_service_php" {
  source = "../../modules/ecs-service"

  project_name             = local.project_name
  environment              = local.environment
  service_name             = "php"
  cluster_id               = module.ecs_cluster.cluster_id
  cluster_name             = module.ecs_cluster.cluster_name
  task_execution_role_arn  = module.ecs_cluster.task_execution_role_arn
  task_role_arn            = module.ecs_cluster.task_role_arn
  task_security_group_id   = module.networking.ecs_tasks_security_group_id
  subnet_ids               = module.networking.public_subnet_ids
  cpu                      = var.php_cpu
  memory                   = var.php_memory
  desired_count            = var.php_desired_count
  target_group_arn         = module.alb.target_group_arn
  container_name           = "php"
  container_port           = 8080
  min_capacity             = var.php_min_capacity
  max_capacity             = var.php_max_capacity
  cpu_target               = var.php_cpu_target

  container_definitions = jsonencode([{
    name      = "php"
    image     = "${module.ecr.repository_urls["php"]}:latest"
    essential = true

    portMappings = [{
      containerPort = 8080
      protocol      = "tcp"
    }]

    environment = [
      { name = "APP_ENV", value = "prod" },
      { name = "APP_SECRET", value = var.app_secret },
      { name = "DATABASE_URL", value = local.database_url },
      { name = "REDIS_URL", value = local.redis_url },
      { name = "MESSENGER_TRANSPORT_DSN", value = local.messenger_transport_dsn },
      { name = "OPENAI_API_KEY", value = var.openai_api_key },
      { name = "MTW_GTFS_STATIC_URL", value = var.gtfs_static_url },
      { name = "MTW_ARCGIS_ROUTE", value = var.arcgis_routes_url },
      { name = "MTW_ARCGIS_STOP", value = var.arcgis_stops_url },
      { name = "MTW_ARCGIS_TRIP", value = var.arcgis_trips_url },
      { name = "MTW_ARCGIS_STOP_TIME", value = var.arcgis_stop_times_url }
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = module.ecs_cluster.log_group_name
        "awslogs-region"        = var.aws_region
        "awslogs-stream-prefix" = "php"
      }
    }
  }])

  tags = local.common_tags
}

# ECS Service: Python Parser (GTFS-RT)
module "ecs_service_pyparser" {
  source = "../../modules/ecs-service"

  project_name             = local.project_name
  environment              = local.environment
  service_name             = "pyparser"
  cluster_id               = module.ecs_cluster.cluster_id
  cluster_name             = module.ecs_cluster.cluster_name
  task_execution_role_arn  = module.ecs_cluster.task_execution_role_arn
  task_role_arn            = module.ecs_cluster.task_role_arn
  task_security_group_id   = module.networking.ecs_tasks_security_group_id
  subnet_ids               = module.networking.public_subnet_ids
  cpu                      = var.pyparser_cpu
  memory                   = var.pyparser_memory
  desired_count            = 1

  container_definitions = jsonencode([{
    name      = "pyparser"
    image     = "${module.ecr.repository_urls["pyparser"]}:latest"
    essential = true

    environment = [
      { name = "REDIS_URL", value = local.redis_url },
      { name = "VEH_URL", value = var.gtfs_rt_vehicles_url },
      { name = "TRIP_URL", value = var.gtfs_rt_trips_url },
      { name = "ALERT_URL", value = var.gtfs_rt_alerts_url },
      { name = "POLL_SEC", value = "12" }
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = module.ecs_cluster.log_group_name
        "awslogs-region"        = var.aws_region
        "awslogs-stream-prefix" = "pyparser"
      }
    }
  }])

  tags = local.common_tags
}

# ECS Service: Scheduler (Cron Jobs)
module "ecs_service_scheduler" {
  source = "../../modules/ecs-service"

  project_name             = local.project_name
  environment              = local.environment
  service_name             = "scheduler"
  cluster_id               = module.ecs_cluster.cluster_id
  cluster_name             = module.ecs_cluster.cluster_name
  task_execution_role_arn  = module.ecs_cluster.task_execution_role_arn
  task_role_arn            = module.ecs_cluster.task_role_arn
  task_security_group_id   = module.networking.ecs_tasks_security_group_id
  subnet_ids               = module.networking.public_subnet_ids
  cpu                      = var.scheduler_cpu
  memory                   = var.scheduler_memory
  desired_count            = 1

  container_definitions = jsonencode([{
    name      = "scheduler"
    image     = "${module.ecr.repository_urls["php"]}:latest"
    essential = true

    command = ["php", "bin/console", "messenger:consume", "scheduler_score_tick", "scheduler_weather_collection", "scheduler_performance_aggregation", "scheduler_insight_cache_warming", "scheduler_bunching_detection", "scheduler_arrival_logging", "-vv"]

    environment = [
      { name = "APP_ENV", value = "prod" },
      { name = "APP_SECRET", value = var.app_secret },
      { name = "DATABASE_URL", value = local.database_url },
      { name = "REDIS_URL", value = local.redis_url },
      { name = "MESSENGER_TRANSPORT_DSN", value = local.messenger_transport_dsn },
      { name = "OPENAI_API_KEY", value = var.openai_api_key },
      { name = "MTW_GTFS_STATIC_URL", value = var.gtfs_static_url },
      { name = "MTW_ARCGIS_ROUTE", value = var.arcgis_routes_url },
      { name = "MTW_ARCGIS_STOP", value = var.arcgis_stops_url },
      { name = "MTW_ARCGIS_TRIP", value = var.arcgis_trips_url },
      { name = "MTW_ARCGIS_STOP_TIME", value = var.arcgis_stop_times_url }
    ]

    logConfiguration = {
      logDriver = "awslogs"
      options = {
        "awslogs-group"         = module.ecs_cluster.log_group_name
        "awslogs-region"        = var.aws_region
        "awslogs-stream-prefix" = "scheduler"
      }
    }
  }])

  tags = local.common_tags
}
