output "alb_arn" {
  description = "ALB ARN"
  value       = aws_lb.this.arn
}

output "alb_dns_name" {
  description = "ALB DNS name"
  value       = aws_lb.this.dns_name
}

output "alb_zone_id" {
  description = "ALB Route 53 zone ID"
  value       = aws_lb.this.zone_id
}

output "target_group_arn" {
  description = "Target group ARN for PHP service"
  value       = aws_lb_target_group.php.arn
}

output "https_listener_arn" {
  description = "HTTPS listener ARN"
  value       = aws_lb_listener.https.arn
}
