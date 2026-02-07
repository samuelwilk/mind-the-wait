variable "project_name" {
  description = "Project name for resource naming"
  type        = string
}

variable "environment" {
  description = "Environment (production, staging)"
  type        = string
  default     = "production"
}

variable "server_type" {
  description = "Hetzner server type"
  type        = string
  default     = "cx22"
}

variable "location" {
  description = "Hetzner datacenter location"
  type        = string
  default     = "nbg1"
}

variable "ssh_public_key" {
  description = "SSH public key for deploy user"
  type        = string
}
