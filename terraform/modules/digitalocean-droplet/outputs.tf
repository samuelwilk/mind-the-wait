output "server_ip" {
  description = "Public IP address of the droplet"
  value       = digitalocean_droplet.web.ipv4_address
}

output "server_id" {
  description = "DigitalOcean droplet ID"
  value       = digitalocean_droplet.web.id
}

output "server_status" {
  description = "Droplet status"
  value       = digitalocean_droplet.web.status
}
