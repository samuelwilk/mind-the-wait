# DigitalOcean Droplet Module

terraform {
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
  }
}

resource "digitalocean_ssh_key" "deploy" {
  name       = "${var.project_name}-deploy-key"
  public_key = var.ssh_public_key
}

resource "digitalocean_firewall" "web" {
  name = "${var.project_name}-firewall"

  droplet_ids = [digitalocean_droplet.web.id]

  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "icmp"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "tcp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "udp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "icmp"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
}

resource "digitalocean_droplet" "web" {
  name     = "${var.project_name}-web"
  image    = "ubuntu-24-04-x64"
  size     = var.droplet_size
  region   = var.region
  ssh_keys = [digitalocean_ssh_key.deploy.fingerprint]

  tags = [
    var.project_name,
    var.environment,
    "managed-by-terraform"
  ]

  user_data = <<-EOF
    #cloud-config
    users:
      - name: deploy
        groups: [sudo, docker]
        shell: /bin/bash
        sudo: ALL=(ALL) NOPASSWD:ALL
        ssh_authorized_keys:
          - ${var.ssh_public_key}

    package_update: true
    package_upgrade: true

    packages:
      - docker.io
      - docker-compose-v2
      - fail2ban
      - git

    runcmd:
      # Create 2GB swap file (needed for GTFS loading)
      - fallocate -l 2G /swapfile
      - chmod 600 /swapfile
      - mkswap /swapfile
      - swapon /swapfile
      - echo '/swapfile none swap sw 0 0' >> /etc/fstab
      # Docker setup
      - systemctl enable docker
      - systemctl start docker
      - usermod -aG docker deploy
      - mkdir -p /home/deploy/mind-the-wait
      - chown deploy:deploy /home/deploy/mind-the-wait
  EOF
}
