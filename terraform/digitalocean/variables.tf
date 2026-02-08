variable "digitalocean_token" {
  description = "DigitalOcean API token"
  type        = string
  sensitive   = true
}

variable "cloudflare_api_token" {
  description = "Cloudflare API token"
  type        = string
  sensitive   = true
}

variable "domain_name" {
  description = "Domain name for DNS"
  type        = string
  default     = "mind-the-wait.ca"
}

variable "droplet_size" {
  description = "DigitalOcean droplet size"
  type        = string
  default     = "s-1vcpu-2gb" # $12/mo - 1 vCPU, 2GB RAM, 50GB SSD
}

variable "region" {
  description = "DigitalOcean region"
  type        = string
  default     = "tor1" # Toronto - closest to Saskatoon
}

variable "ssh_public_key" {
  description = "SSH public key for deploy user"
  type        = string
}
