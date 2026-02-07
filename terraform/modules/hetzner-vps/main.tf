# Hetzner VPS Module

terraform {
  required_providers {
    hcloud = {
      source  = "hetznercloud/hcloud"
      version = "~> 1.45"
    }
  }
}

resource "hcloud_ssh_key" "deploy" {
  name       = "${var.project_name}-deploy-key"
  public_key = var.ssh_public_key
}

resource "hcloud_firewall" "web" {
  name = "${var.project_name}-firewall"

  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "22"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "80"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "443"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  rule {
    direction  = "in"
    protocol   = "icmp"
    source_ips = ["0.0.0.0/0", "::/0"]
  }
}

resource "hcloud_server" "web" {
  name         = "${var.project_name}-web"
  image        = "ubuntu-24.04"
  server_type  = var.server_type
  location     = var.location
  ssh_keys     = [hcloud_ssh_key.deploy.id]
  firewall_ids = [hcloud_firewall.web.id]

  labels = {
    project     = var.project_name
    environment = var.environment
    managed_by  = "terraform"
  }

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
      - systemctl enable docker
      - systemctl start docker
      - usermod -aG docker deploy
      - mkdir -p /home/deploy/mind-the-wait
      - chown deploy:deploy /home/deploy/mind-the-wait
  EOF
}
