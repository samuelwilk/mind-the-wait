output "endpoint" {
  description = "RDS instance endpoint"
  value       = aws_db_instance.this.endpoint
}

output "address" {
  description = "RDS instance address (hostname)"
  value       = aws_db_instance.this.address
}

output "port" {
  description = "RDS instance port"
  value       = aws_db_instance.this.port
}

output "instance_id" {
  description = "RDS instance ID"
  value       = aws_db_instance.this.id
}

output "arn" {
  description = "RDS instance ARN"
  value       = aws_db_instance.this.arn
}
