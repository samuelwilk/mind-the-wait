variable "project_name" {
  description = "Project name for resource naming"
  type        = string
}

variable "environment" {
  description = "Environment (production, staging)"
  type        = string
  default     = "production"
}

variable "droplet_size" {
  description = "DigitalOcean droplet size slug"
  type        = string
  default     = "s-1vcpu-2gb" # $12/mo - 1 vCPU, 2GB RAM, 50GB SSD
}

variable "region" {
  description = "DigitalOcean region"
  type        = string
  default     = "tor1" # Toronto
}

variable "ssh_public_key" {
  description = "SSH public key for deploy user"
  type        = string
}
