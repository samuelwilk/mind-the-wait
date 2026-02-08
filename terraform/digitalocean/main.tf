# DigitalOcean Production Environment

terraform {
  required_version = ">= 1.6"

  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 4.0"
    }
  }
}

provider "digitalocean" {
  token = var.digitalocean_token
}

provider "cloudflare" {
  api_token = var.cloudflare_api_token
}

module "droplet" {
  source = "../modules/digitalocean-droplet"

  project_name   = "mind-the-wait"
  environment    = "production"
  droplet_size   = var.droplet_size
  region         = var.region
  ssh_public_key = var.ssh_public_key
}

module "dns" {
  source = "../modules/cloudflare-dns"

  domain_name      = var.domain_name
  server_ip        = module.droplet.server_ip
  cloudflare_proxy = false # Let Caddy handle SSL
}
