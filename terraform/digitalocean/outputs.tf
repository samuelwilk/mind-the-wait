output "server_ip" {
  description = "Public IP address of the droplet"
  value       = module.droplet.server_ip
}

output "server_id" {
  description = "DigitalOcean droplet ID"
  value       = module.droplet.server_id
}

output "server_status" {
  description = "Droplet status"
  value       = module.droplet.server_status
}

output "ssh_command" {
  description = "SSH command to connect to the server"
  value       = "ssh deploy@${module.droplet.server_ip}"
}
