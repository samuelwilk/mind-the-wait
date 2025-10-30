# Variables for mind-the-wait Production Environment

variable "aws_region" {
  description = "AWS region"
  type        = string
}

variable "domain_name" {
  description = "Domain name"
  type        = string
}

# Network Configuration
variable "vpc_cidr" {
  description = "CIDR block for VPC"
  type        = string
}

variable "availability_zones" {
  description = "List of availability zones"
  type        = list(string)
}

variable "public_subnets" {
  description = "List of public subnet CIDR blocks"
  type        = list(string)
}

variable "private_subnets" {
  description = "List of private subnet CIDR blocks"
  type        = list(string)
}

# RDS Configuration
variable "rds_instance_class" {
  description = "RDS instance class"
  type        = string
}

variable "rds_allocated_storage" {
  description = "RDS allocated storage in GB"
  type        = number
}

variable "rds_multi_az" {
  description = "Enable RDS Multi-AZ"
  type        = bool
}

variable "database_name" {
  description = "Database name"
  type        = string
}

variable "database_username" {
  description = "Database username"
  type        = string
  sensitive   = true
}

variable "database_password" {
  description = "Database password"
  type        = string
  sensitive   = true
}

# ElastiCache Configuration
variable "redis_node_type" {
  description = "ElastiCache node type"
  type        = string
}

# ECS Task Sizing - PHP
variable "php_cpu" {
  description = "CPU units for PHP task (256 = 0.25 vCPU)"
  type        = number
}

variable "php_memory" {
  description = "Memory for PHP task in MB"
  type        = number
}

variable "php_desired_count" {
  description = "Desired count of PHP tasks"
  type        = number
}

variable "php_min_capacity" {
  description = "Minimum capacity for PHP auto-scaling"
  type        = number
  default     = 1
}

variable "php_max_capacity" {
  description = "Maximum capacity for PHP auto-scaling"
  type        = number
  default     = 2
}

variable "php_cpu_target" {
  description = "Target CPU utilization for auto-scaling"
  type        = number
  default     = 75
}

# ECS Task Sizing - Python Parser
variable "pyparser_cpu" {
  description = "CPU units for pyparser task"
  type        = number
}

variable "pyparser_memory" {
  description = "Memory for pyparser task in MB"
  type        = number
}

# ECS Task Sizing - Scheduler
variable "scheduler_cpu" {
  description = "CPU units for scheduler task"
  type        = number
}

variable "scheduler_memory" {
  description = "Memory for scheduler task in MB"
  type        = number
}

# Application Configuration
variable "app_secret" {
  description = "Symfony APP_SECRET for encryption and security"
  type        = string
  sensitive   = true
}

variable "openai_api_key" {
  description = "OpenAI API key for AI-generated insights"
  type        = string
  sensitive   = true
}

variable "ga4_measurement_id" {
  description = "Google Analytics 4 Measurement ID (format: G-XXXXXXXXXX)"
  type        = string
  sensitive   = true
}

variable "gtfs_static_url" {
  description = "GTFS static feed URL"
  type        = string
}

variable "arcgis_routes_url" {
  description = "ArcGIS Routes FeatureServer URL"
  type        = string
}

variable "arcgis_stops_url" {
  description = "ArcGIS Stops FeatureServer URL"
  type        = string
}

variable "arcgis_trips_url" {
  description = "ArcGIS Trips FeatureServer URL"
  type        = string
}

variable "arcgis_stop_times_url" {
  description = "ArcGIS Stop Times FeatureServer URL"
  type        = string
}

# GTFS-RT (Realtime) Configuration
variable "gtfs_rt_vehicles_url" {
  description = "GTFS-RT Vehicle Positions feed URL"
  type        = string
}

variable "gtfs_rt_trips_url" {
  description = "GTFS-RT Trip Updates feed URL"
  type        = string
}

variable "gtfs_rt_alerts_url" {
  description = "GTFS-RT Service Alerts feed URL"
  type        = string
}

# CloudWatch Configuration
variable "log_retention_days" {
  description = "CloudWatch log retention in days"
  type        = number
  default     = 7
}

variable "alert_email" {
  description = "Email address for CloudWatch alarm notifications (Spot monitoring)"
  type        = string
  default     = ""
}

variable "tags" {
  description = "Additional tags"
  type        = map(string)
  default     = {}
}
