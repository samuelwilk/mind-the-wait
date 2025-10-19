variable "project_name" {
  description = "Project name"
  type        = string
}

variable "environment" {
  description = "Environment name"
  type        = string
}

variable "service_name" {
  description = "Service name (php, pyparser, scheduler)"
  type        = string
}

variable "cluster_id" {
  description = "ECS cluster ID"
  type        = string
}

variable "cluster_name" {
  description = "ECS cluster name"
  type        = string
}

variable "task_execution_role_arn" {
  description = "Task execution role ARN"
  type        = string
}

variable "task_role_arn" {
  description = "Task role ARN"
  type        = string
}

variable "task_security_group_id" {
  description = "Security group ID for ECS tasks"
  type        = string
}

variable "subnet_ids" {
  description = "List of subnet IDs"
  type        = list(string)
}

variable "cpu" {
  description = "Task CPU units"
  type        = number
}

variable "memory" {
  description = "Task memory in MB"
  type        = number
}

variable "container_definitions" {
  description = "Container definitions JSON"
  type        = string
}

variable "desired_count" {
  description = "Desired number of tasks"
  type        = number
}

# Load balancer configuration (optional)
variable "target_group_arn" {
  description = "Target group ARN (null if no load balancer)"
  type        = string
  default     = null
}

variable "container_name" {
  description = "Container name for load balancer"
  type        = string
  default     = ""
}

variable "container_port" {
  description = "Container port for load balancer"
  type        = number
  default     = 8080
}

# Auto-scaling configuration (optional)
variable "min_capacity" {
  description = "Minimum number of tasks (null to disable auto-scaling)"
  type        = number
  default     = null
}

variable "max_capacity" {
  description = "Maximum number of tasks"
  type        = number
  default     = 3
}

variable "cpu_target" {
  description = "Target CPU utilization percentage for auto-scaling"
  type        = number
  default     = 70
}

variable "tags" {
  description = "Tags to apply to resources"
  type        = map(string)
  default     = {}
}

variable "use_spot" {
  description = "Use Fargate Spot for 70% cost savings (recommended for dev/staging)"
  type        = bool
  default     = false
}
