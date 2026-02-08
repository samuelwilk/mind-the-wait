output "server_ip" {
  description = "VPS public IP address"
  value       = module.vps.server_ip
}

output "ssh_command" {
  description = "SSH command to connect"
  value       = "ssh deploy@${module.vps.server_ip}"
}

output "deploy_instructions" {
  description = "Next steps after provisioning"
  value       = <<-EOF

    Server provisioned successfully!

    1. Wait 2-3 minutes for cloud-init to complete
    2. SSH into server: ssh deploy@${module.vps.server_ip}
    3. Clone the repo: git clone https://github.com/your-org/mind-the-wait.git
    4. Deploy: cd mind-the-wait && docker compose -f docker/compose.prod.yaml up -d

    DNS will propagate within a few minutes.
  EOF
}
