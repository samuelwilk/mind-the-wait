# Outputs for mind-the-wait Production Environment

output "alb_dns_name" {
  description = "ALB DNS name (use for testing before DNS propagates)"
  value       = module.alb.alb_dns_name
}

output "domain_name" {
  description = "Your domain name"
  value       = var.domain_name
}

output "route53_name_servers" {
  description = "Route 53 name servers - Update these in your domain registrar"
  value       = module.dns.name_servers
}

output "rds_endpoint" {
  description = "RDS PostgreSQL endpoint"
  value       = module.rds.endpoint
  sensitive   = true
}

output "redis_endpoint" {
  description = "ElastiCache Redis endpoint"
  value       = "${module.elasticache.endpoint}:${module.elasticache.port}"
}

output "ecr_repository_urls" {
  description = "ECR repository URLs for pushing Docker images"
  value       = module.ecr.repository_urls
}

output "ecs_cluster_name" {
  description = "ECS cluster name"
  value       = module.ecs_cluster.cluster_name
}

output "ecs_services" {
  description = "ECS service names"
  value = {
    php                  = module.ecs_service_php.service_name
    pyparser             = module.ecs_service_pyparser.service_name
    scheduler_high_freq  = module.ecs_service_scheduler_high_freq.service_name
    scheduler_low_freq   = module.ecs_service_scheduler_low_freq.service_name
  }
}

output "next_steps" {
  description = "Next steps after deployment"
  value = <<-EOT

  ✅ Infrastructure deployed successfully!

  Next steps:

  1. Update DNS:
     - Go to your domain registrar (where you bought ${var.domain_name})
     - Update nameservers to Route 53 nameservers (see 'route53_name_servers' output)
     - Wait 10-30 minutes for DNS propagation

  2. Test ALB endpoint (before DNS):
     curl https://${module.alb.alb_dns_name}/api/realtime

  3. Build and push Docker images:
     aws ecr get-login-password --region ${var.aws_region} --profile mind-the-wait | docker login --username AWS --password-stdin ${split("/", module.ecr.repository_urls["php"])[0]}

     # Build and push PHP image
     docker build -t ${module.ecr.repository_urls["php"]}:latest .
     docker push ${module.ecr.repository_urls["php"]}:latest

     # Build and push pyparser image
     docker build -t ${module.ecr.repository_urls["pyparser"]}:latest ./pyparser
     docker push ${module.ecr.repository_urls["pyparser"]}:latest

  4. Force ECS deployment:
     aws ecs update-service --cluster ${module.ecs_cluster.cluster_name} --service ${module.ecs_service_php.service_name} --force-new-deployment --profile mind-the-wait
     aws ecs update-service --cluster ${module.ecs_cluster.cluster_name} --service ${module.ecs_service_pyparser.service_name} --force-new-deployment --profile mind-the-wait
     aws ecs update-service --cluster ${module.ecs_cluster.cluster_name} --service ${module.ecs_service_scheduler_high_freq.service_name} --force-new-deployment --profile mind-the-wait
     aws ecs update-service --cluster ${module.ecs_cluster.cluster_name} --service ${module.ecs_service_scheduler_low_freq.service_name} --force-new-deployment --profile mind-the-wait

  5. Run database migrations:
     # Get task ARN
     TASK_ARN=$(aws ecs list-tasks --cluster ${module.ecs_cluster.cluster_name} --service-name ${module.ecs_service_php.service_name} --query 'taskArns[0]' --output text --profile mind-the-wait)

     # Enable ECS Exec and run migrations
     aws ecs execute-command --cluster ${module.ecs_cluster.cluster_name} --task $TASK_ARN --container php --interactive --command "/bin/sh" --profile mind-the-wait
     # Inside container: php bin/console doctrine:migrations:migrate --no-interaction

  6. Test your site:
     https://${var.domain_name}

  EOT
}
