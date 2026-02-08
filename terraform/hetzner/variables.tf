variable "hetzner_token" {
  description = "Hetzner Cloud API token"
  type        = string
  sensitive   = true
}

variable "cloudflare_api_token" {
  description = "Cloudflare API token"
  type        = string
  sensitive   = true
}

variable "domain_name" {
  description = "Domain name"
  type        = string
  default     = "mind-the-wait.ca"
}

variable "server_type" {
  description = "Hetzner server type"
  type        = string
  default     = "cx23"
}

variable "location" {
  description = "Hetzner datacenter"
  type        = string
  default     = "nbg1" # Nuremberg
}

variable "ssh_public_key" {
  description = "SSH public key for deploy user"
  type        = string
}
