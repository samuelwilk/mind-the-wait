# ECS Service Module - Reusable for multiple services

# Task Definition
resource "aws_ecs_task_definition" "this" {
  family                   = "${var.project_name}-${var.environment}-${var.service_name}"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = var.cpu
  memory                   = var.memory
  execution_role_arn       = var.task_execution_role_arn
  task_role_arn            = var.task_role_arn

  container_definitions = var.container_definitions

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-${var.service_name}"
  })
}

# ECS Service
resource "aws_ecs_service" "this" {
  name            = "${var.project_name}-${var.environment}-${var.service_name}"
  cluster         = var.cluster_id
  task_definition = aws_ecs_task_definition.this.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = var.subnet_ids
    security_groups  = [var.task_security_group_id]
    assign_public_ip = true  # Required for public subnets without NAT Gateway
  }

  # Load balancer configuration (only if target_group_arn is provided)
  dynamic "load_balancer" {
    for_each = var.target_group_arn != null ? [1] : []

    content {
      target_group_arn = var.target_group_arn
      container_name   = var.container_name
      container_port   = var.container_port
    }
  }

  # Auto-scaling is handled by separate resources below

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-${var.service_name}"
  })

  lifecycle {
    ignore_changes = [desired_count]  # Let auto-scaling manage this
  }
}

# Auto-scaling target (only if min_capacity is set)
resource "aws_appautoscaling_target" "this" {
  count = var.min_capacity != null ? 1 : 0

  max_capacity       = var.max_capacity
  min_capacity       = var.min_capacity
  resource_id        = "service/${var.cluster_name}/${aws_ecs_service.this.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

# Auto-scaling policy based on CPU
resource "aws_appautoscaling_policy" "cpu" {
  count = var.min_capacity != null ? 1 : 0

  name               = "${var.project_name}-${var.environment}-${var.service_name}-cpu-autoscaling"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.this[0].resource_id
  scalable_dimension = aws_appautoscaling_target.this[0].scalable_dimension
  service_namespace  = aws_appautoscaling_target.this[0].service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }

    target_value = var.cpu_target
  }
}
