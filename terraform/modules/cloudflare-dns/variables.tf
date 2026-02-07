variable "domain_name" {
  description = "Domain name (e.g., mind-the-wait.ca)"
  type        = string
}

variable "server_ip" {
  description = "IP address to point DNS records to"
  type        = string
}

variable "cloudflare_proxy" {
  description = "Whether to proxy through Cloudflare"
  type        = bool
  default     = false # Disable for now - let Caddy handle SSL
}
