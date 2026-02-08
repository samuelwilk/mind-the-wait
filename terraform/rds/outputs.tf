output "rds_endpoint" {
  description = "RDS endpoint address"
  value       = aws_db_instance.this.endpoint
}

output "rds_address" {
  description = "RDS hostname (without port)"
  value       = aws_db_instance.this.address
}

output "rds_port" {
  description = "RDS port"
  value       = aws_db_instance.this.port
}

output "database_url" {
  description = "Full database URL for .env.prod"
  value       = "postgresql://${aws_db_instance.this.username}:PASSWORD@${aws_db_instance.this.address}:${aws_db_instance.this.port}/${aws_db_instance.this.db_name}?serverVersion=16&charset=utf8"
  sensitive   = true
}
