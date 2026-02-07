# Cloudflare DNS Module

terraform {
  required_providers {
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 4.0"
    }
  }
}

data "cloudflare_zone" "main" {
  name = var.domain_name
}

resource "cloudflare_record" "root" {
  zone_id = data.cloudflare_zone.main.id
  name    = "@"
  type    = "A"
  content = var.server_ip
  proxied = var.cloudflare_proxy
  ttl     = var.cloudflare_proxy ? 1 : 300
}

resource "cloudflare_record" "www" {
  zone_id = data.cloudflare_zone.main.id
  name    = "www"
  type    = "A"
  content = var.server_ip
  proxied = var.cloudflare_proxy
  ttl     = var.cloudflare_proxy ? 1 : 300
}
