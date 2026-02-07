# Hetzner Production Environment

terraform {
  required_version = ">= 1.6"

  required_providers {
    hcloud = {
      source  = "hetznercloud/hcloud"
      version = "~> 1.45"
    }
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 4.0"
    }
  }

  # Optional: Use S3 backend for state (reuse existing)
  # backend "s3" {
  #   bucket         = "mind-the-wait-terraform-state"
  #   key            = "hetzner-prod/terraform.tfstate"
  #   region         = "ca-central-1"
  #   encrypt        = true
  #   dynamodb_table = "mind-the-wait-terraform-locks"
  # }
}

provider "hcloud" {
  token = var.hetzner_token
}

provider "cloudflare" {
  api_token = var.cloudflare_api_token
}

module "vps" {
  source = "../../modules/hetzner-vps"

  project_name   = "mind-the-wait"
  environment    = "production"
  server_type    = var.server_type
  location       = var.location
  ssh_public_key = var.ssh_public_key
}

module "dns" {
  source = "../../modules/cloudflare-dns"

  domain_name      = var.domain_name
  server_ip        = module.vps.server_ip
  cloudflare_proxy = false # Let Caddy handle SSL
}
