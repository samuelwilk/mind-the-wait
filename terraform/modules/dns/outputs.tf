output "zone_id" {
  description = "Route 53 hosted zone ID"
  value       = aws_route53_zone.this.zone_id
}

output "name_servers" {
  description = "Route 53 name servers"
  value       = aws_route53_zone.this.name_servers
}

output "certificate_arn" {
  description = "ACM certificate ARN"
  value       = aws_acm_certificate_validation.this.certificate_arn
}

output "domain_name" {
  description = "Domain name"
  value       = var.domain_name
}
