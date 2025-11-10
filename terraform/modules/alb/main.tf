# ALB Module - Application Load Balancer

resource "aws_lb" "this" {
  name               = "${var.project_name}-${var.environment}-alb"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [var.security_group_id]
  subnets            = var.subnet_ids

  enable_deletion_protection = false
  enable_http2              = true

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-alb"
  })
}

# Target Group for ECS PHP service
resource "aws_lb_target_group" "php" {
  name        = "${var.project_name}-${var.environment}-php-tg"
  port        = 8080
  protocol    = "HTTP"
  vpc_id      = var.vpc_id
  target_type = "ip"

  health_check {
    enabled             = true
    healthy_threshold   = 2
    unhealthy_threshold = 3
    timeout             = 5
    interval            = 30
    path                = var.health_check_path
    matcher             = "200"
  }

  deregistration_delay = 30

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-php-tg"
  })
}

# Target Group for Mercure service
resource "aws_lb_target_group" "mercure" {
  name        = "${var.project_name}-${var.environment}-mercure-tg"
  port        = 80
  protocol    = "HTTP"
  vpc_id      = var.vpc_id
  target_type = "ip"

  health_check {
    enabled             = true
    healthy_threshold   = 2
    unhealthy_threshold = 3
    timeout             = 5
    interval            = 30
    path                = "/.well-known/mercure"
    matcher             = "200,400"  # 400 is expected for Mercure without SSE params
  }

  # Longer timeout for SSE connections
  deregistration_delay = 60

  # Stickiness for SSE connections
  stickiness {
    type            = "lb_cookie"
    enabled         = true
    cookie_duration = 86400  # 24 hours
  }

  tags = merge(var.tags, {
    Name = "${var.project_name}-${var.environment}-mercure-tg"
  })
}

# HTTP Listener - Redirect to HTTPS
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.this.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"
    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}

# HTTPS Listener
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.this.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.php.arn
  }
}

# Listener Rule - Route Mercure traffic
resource "aws_lb_listener_rule" "mercure" {
  listener_arn = aws_lb_listener.https.arn
  priority     = 100

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.mercure.arn
  }

  condition {
    path_pattern {
      values = ["/.well-known/mercure*"]
    }
  }
}
