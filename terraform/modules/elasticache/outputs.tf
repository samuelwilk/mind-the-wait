output "endpoint" {
  description = "Redis cache endpoint"
  value       = aws_elasticache_cluster.this.cache_nodes[0].address
}

output "port" {
  description = "Redis cache port"
  value       = aws_elasticache_cluster.this.cache_nodes[0].port
}

output "cluster_id" {
  description = "Redis cluster ID"
  value       = aws_elasticache_cluster.this.cluster_id
}
