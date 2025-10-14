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

# HTTP Listener - Temporary: Forward traffic directly
# After certificate validation, change type to "redirect" and add redirect block
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.this.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.php.arn
  }
}

# HTTPS Listener - Commented until certificate is validated
# Uncomment after DNS propagation and certificate validation complete
# resource "aws_lb_listener" "https" {
#   load_balancer_arn = aws_lb.this.arn
#   port              = 443
#   protocol          = "HTTPS"
#   ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
#   certificate_arn   = var.certificate_arn
#
#   default_action {
#     type             = "forward"
#     target_group_arn = aws_lb_target_group.php.arn
#   }
# }
