# Hetzner Migration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Migrate mind-the-wait from AWS ECS to Hetzner VPS while preserving RDS database

**Architecture:** Hetzner VPS runs all containers via Docker Compose, connects to existing AWS RDS PostgreSQL over public internet. Caddy handles reverse proxy and auto-HTTPS. Cloudflare manages DNS.

**Tech Stack:** Terraform (Hetzner + Cloudflare providers), Docker Compose, Caddy, FrankenPHP, Redis, Mercure

---

## Phase 1: Terraform Infrastructure

### Task 1: Create Hetzner VPS Module

**Files:**
- Create: `terraform/modules/hetzner-vps/main.tf`
- Create: `terraform/modules/hetzner-vps/variables.tf`
- Create: `terraform/modules/hetzner-vps/outputs.tf`

**Step 1: Create main.tf**

```hcl
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
    direction = "in"
    protocol  = "tcp"
    port      = "22"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  rule {
    direction = "in"
    protocol  = "tcp"
    port      = "80"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  rule {
    direction = "in"
    protocol  = "tcp"
    port      = "443"
    source_ips = ["0.0.0.0/0", "::/0"]
  }

  rule {
    direction = "in"
    protocol  = "icmp"
    source_ips = ["0.0.0.0/0", "::/0"]
  }
}

resource "hcloud_server" "web" {
  name        = "${var.project_name}-web"
  image       = "ubuntu-24.04"
  server_type = var.server_type
  location    = var.location
  ssh_keys    = [hcloud_ssh_key.deploy.id]
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
```

**Step 2: Create variables.tf**

```hcl
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
```

**Step 3: Create outputs.tf**

```hcl
output "server_ip" {
  description = "Public IP address of the server"
  value       = hcloud_server.web.ipv4_address
}

output "server_id" {
  description = "Hetzner server ID"
  value       = hcloud_server.web.id
}

output "server_status" {
  description = "Server status"
  value       = hcloud_server.web.status
}
```

**Step 4: Commit**

```bash
git add terraform/modules/hetzner-vps/
git commit -m "feat(terraform): add Hetzner VPS module"
```

---

### Task 2: Create Cloudflare DNS Module

**Files:**
- Create: `terraform/modules/cloudflare-dns/main.tf`
- Create: `terraform/modules/cloudflare-dns/variables.tf`
- Create: `terraform/modules/cloudflare-dns/outputs.tf`

**Step 1: Create main.tf**

```hcl
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
```

**Step 2: Create variables.tf**

```hcl
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
  default     = false  # Disable for now - let Caddy handle SSL
}
```

**Step 3: Create outputs.tf**

```hcl
output "zone_id" {
  description = "Cloudflare zone ID"
  value       = data.cloudflare_zone.main.id
}

output "root_record_id" {
  description = "Root A record ID"
  value       = cloudflare_record.root.id
}
```

**Step 4: Commit**

```bash
git add terraform/modules/cloudflare-dns/
git commit -m "feat(terraform): add Cloudflare DNS module"
```

---

### Task 3: Create Production Environment Config

**Files:**
- Create: `terraform/environments/hetzner-prod/main.tf`
- Create: `terraform/environments/hetzner-prod/variables.tf`
- Create: `terraform/environments/hetzner-prod/outputs.tf`
- Create: `terraform/environments/hetzner-prod/terraform.tfvars.example`

**Step 1: Create main.tf**

```hcl
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
  cloudflare_proxy = false  # Let Caddy handle SSL
}
```

**Step 2: Create variables.tf**

```hcl
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
  default     = "cx22"  # 2 vCPU, 4GB RAM - €4.51/mo
}

variable "location" {
  description = "Hetzner datacenter"
  type        = string
  default     = "nbg1"  # Nuremberg
}

variable "ssh_public_key" {
  description = "SSH public key for deploy user"
  type        = string
}
```

**Step 3: Create outputs.tf**

```hcl
output "server_ip" {
  description = "VPS public IP address"
  value       = module.vps.server_ip
}

output "ssh_command" {
  description = "SSH command to connect"
  value       = "ssh deploy@${module.vps.server_ip}"
}

output "deploy_instructions" {
  description = "Next steps after provisioning"
  value       = <<-EOF

    Server provisioned successfully!

    1. Wait 2-3 minutes for cloud-init to complete
    2. SSH into server: ssh deploy@${module.vps.server_ip}
    3. Clone the repo: git clone https://github.com/your-org/mind-the-wait.git
    4. Deploy: cd mind-the-wait && docker compose -f docker/compose.prod.yaml up -d

    DNS will propagate within a few minutes.
  EOF
}
```

**Step 4: Create terraform.tfvars.example**

```hcl
# Copy to terraform.tfvars and fill in values
# DO NOT commit terraform.tfvars to git

hetzner_token        = "your-hetzner-api-token"
cloudflare_api_token = "your-cloudflare-api-token"
ssh_public_key       = "ssh-ed25519 AAAA... you@example.com"
domain_name          = "mind-the-wait.ca"
server_type          = "cx22"
location             = "nbg1"
```

**Step 5: Commit**

```bash
git add terraform/environments/hetzner-prod/
git commit -m "feat(terraform): add Hetzner production environment"
```

---

## Phase 2: Docker Production Files

### Task 4: Create Production Docker Compose

**Files:**
- Create: `docker/compose.prod.yaml`

**Step 1: Create compose.prod.yaml**

```yaml
# Production Docker Compose for Hetzner VPS

services:
  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"  # HTTP/3
    volumes:
      - ./Caddyfile.prod:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - php
      - mercure
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "wget", "-qO-", "http://localhost:80/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  php:
    build:
      context: ..
      dockerfile: Dockerfile
      target: frankenphp_prod
    restart: unless-stopped
    expose:
      - "8080"
    env_file:
      - .env.prod
    environment:
      MERCURE_URL: http://mercure/.well-known/mercure
      MERCURE_PUBLIC_URL: https://mind-the-wait.ca/.well-known/mercure
    depends_on:
      - redis
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/api/healthz"]
      interval: 30s
      timeout: 10s
      retries: 3

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    volumes:
      - redis_data:/data
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3

  pyparser:
    build:
      context: ./pyparser
    restart: unless-stopped
    env_file:
      - .env.prod
    environment:
      REDIS_URL: redis://redis:6379
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - app-network

  scheduler-high-freq:
    build:
      context: ..
      dockerfile: Dockerfile
      target: frankenphp_prod
    restart: unless-stopped
    command: php bin/console messenger:consume scheduler_score_tick scheduler_mercure_route_broadcast scheduler_arrival_logging -vv
    env_file:
      - .env.prod
    environment:
      MERCURE_URL: http://mercure/.well-known/mercure
      MERCURE_PUBLIC_URL: https://mind-the-wait.ca/.well-known/mercure
    depends_on:
      redis:
        condition: service_healthy
      mercure:
        condition: service_started
    networks:
      - app-network

  scheduler-low-freq:
    build:
      context: ..
      dockerfile: Dockerfile
      target: frankenphp_prod
    restart: unless-stopped
    command: php bin/console messenger:consume scheduler_weather_collection scheduler_performance_aggregation scheduler_insight_cache_warming scheduler_bunching_detection -vv
    env_file:
      - .env.prod
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - app-network

  mercure:
    image: dunglas/mercure:v0.16
    restart: unless-stopped
    expose:
      - "80"
    environment:
      SERVER_NAME: ':80'
      MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET}
      MERCURE_EXTRA_DIRECTIVES: |
        anonymous
        cors_origins https://mind-the-wait.ca
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "wget", "-qO-", "http://localhost/.well-known/mercure"]
      interval: 30s
      timeout: 5s
      retries: 3

volumes:
  caddy_data:
  caddy_config:
  redis_data:

networks:
  app-network:
    driver: bridge
```

**Step 2: Commit**

```bash
git add docker/compose.prod.yaml
git commit -m "feat(docker): add production Docker Compose"
```

---

### Task 5: Create Production Caddyfile

**Files:**
- Create: `docker/Caddyfile.prod`

**Step 1: Create Caddyfile.prod**

```caddyfile
# Production Caddyfile for mind-the-wait.ca

{
    email admin@mind-the-wait.ca
}

mind-the-wait.ca, www.mind-the-wait.ca {
    # Redirect www to non-www
    @www host www.mind-the-wait.ca
    redir @www https://mind-the-wait.ca{uri} permanent

    # Health check endpoint
    respond /health 200

    # Mercure hub
    handle /.well-known/mercure* {
        reverse_proxy mercure:80
    }

    # PHP application
    handle {
        reverse_proxy php:8080
    }

    # Security headers
    header {
        X-Frame-Options "SAMEORIGIN"
        X-Content-Type-Options "nosniff"
        X-XSS-Protection "1; mode=block"
        Referrer-Policy "strict-origin-when-cross-origin"
        -Server
    }

    # Logging
    log {
        output stdout
        format json
    }
}
```

**Step 2: Commit**

```bash
git add docker/Caddyfile.prod
git commit -m "feat(docker): add production Caddyfile"
```

---

### Task 6: Create Production Environment Template

**Files:**
- Create: `docker/.env.prod.example`

**Step 1: Create .env.prod.example**

```bash
# Production Environment Variables
# Copy to .env.prod and fill in values (created by CI/CD from GitHub secrets)

# Symfony
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=change-me-64-char-random-string

# Database (AWS RDS)
DATABASE_URL="postgresql://mindthewait_admin:PASSWORD@mind-the-wait-prod.xxx.ca-central-1.rds.amazonaws.com:5432/mindthewait?serverVersion=16&charset=utf8"

# Redis (local container)
REDIS_URL=redis://redis:6379
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages

# Mercure
MERCURE_JWT_SECRET=change-me-random-string

# OpenAI (for insights)
OPENAI_API_KEY=sk-proj-xxx

# GTFS-RT URLs (Saskatoon Transit)
VEH_URL=https://saskprdtmgtfs.sasktrpcloud.com/TMGTFSRealTimeWebService/Vehicle/VehiclePositions.pb
TRIP_URL=https://saskprdtmgtfs.sasktrpcloud.com/TMGTFSRealTimeWebService/TripUpdate/TripUpdates.pb
ALERT_URL=https://saskprdtmgtfs.sasktrpcloud.com/TMGTFSRealTimeWebService/Alert/Alerts.pb

# GTFS Static (ArcGIS)
MTW_ARCGIS_ROUTE=https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Routes/FeatureServer/0/query
MTW_ARCGIS_STOP=https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Stops/FeatureServer/0/query
MTW_ARCGIS_TRIP=https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Trips/FeatureServer/0/query
MTW_ARCGIS_STOP_TIME=https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Stop_Times/FeatureServer/0/query

# Google Analytics (optional)
GA4_MEASUREMENT_ID=G-xxx
```

**Step 2: Commit**

```bash
git add docker/.env.prod.example
git commit -m "docs(docker): add production env template"
```

---

## Phase 3: CI/CD Pipeline

### Task 7: Create Hetzner Deploy Workflow

**Files:**
- Create: `.github/workflows/deploy-hetzner.yml`

**Step 1: Create deploy-hetzner.yml**

```yaml
name: Deploy to Hetzner

on:
  push:
    branches: [main]
  release:
    types: [published]
  workflow_dispatch:

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: intl, pdo_pgsql, redis

      - name: Install dependencies
        run: composer install --no-progress --prefer-dist

      - name: Run tests
        run: vendor/bin/phpunit

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.event_name == 'release' || github.event_name == 'workflow_dispatch'
    steps:
      - name: Deploy to Hetzner
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.HETZNER_SERVER_IP }}
          username: deploy
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            set -e

            cd /home/deploy/mind-the-wait

            # Pull latest code
            git fetch origin main
            git reset --hard origin/main

            # Create .env.prod from secrets
            cat > docker/.env.prod << 'ENVEOF'
            APP_ENV=prod
            APP_DEBUG=0
            APP_SECRET=${{ secrets.APP_SECRET }}
            DATABASE_URL=${{ secrets.DATABASE_URL }}
            REDIS_URL=redis://redis:6379
            MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
            MERCURE_JWT_SECRET=${{ secrets.MERCURE_JWT_SECRET }}
            OPENAI_API_KEY=${{ secrets.OPENAI_API_KEY }}
            VEH_URL=${{ secrets.GTFS_RT_VEHICLES_URL }}
            TRIP_URL=${{ secrets.GTFS_RT_TRIPS_URL }}
            ALERT_URL=${{ secrets.GTFS_RT_ALERTS_URL }}
            MTW_ARCGIS_ROUTE=${{ secrets.ARCGIS_ROUTES_URL }}
            MTW_ARCGIS_STOP=${{ secrets.ARCGIS_STOPS_URL }}
            MTW_ARCGIS_TRIP=${{ secrets.ARCGIS_TRIPS_URL }}
            MTW_ARCGIS_STOP_TIME=${{ secrets.ARCGIS_STOP_TIMES_URL }}
            GA4_MEASUREMENT_ID=${{ secrets.GA4_MEASUREMENT_ID }}
            ENVEOF

            # Build and deploy
            docker compose -f docker/compose.prod.yaml build --pull
            docker compose -f docker/compose.prod.yaml up -d --remove-orphans

            # Wait for services to be healthy
            sleep 10

            # Run migrations
            docker compose -f docker/compose.prod.yaml exec -T php bin/console doctrine:migrations:migrate --no-interaction

            # Warm cache
            docker compose -f docker/compose.prod.yaml exec -T php bin/console cache:warmup

            # Health check
            curl -sf http://localhost/api/healthz || exit 1

            echo "Deployment successful!"
```

**Step 2: Commit**

```bash
git add .github/workflows/deploy-hetzner.yml
git commit -m "feat(ci): add Hetzner deployment workflow"
```

---

## Phase 4: RDS Configuration (Terraform)

### Task 8: Update RDS for Public Access

**Files:**
- Modify: `terraform/modules/rds/main.tf`
- Modify: `terraform/modules/networking/main.tf`

**Step 1: Update RDS module to allow public access**

In `terraform/modules/rds/main.tf`, change:

```hcl
# Change this line:
publicly_accessible    = false

# To:
publicly_accessible    = var.publicly_accessible
```

Add to `terraform/modules/rds/variables.tf`:

```hcl
variable "publicly_accessible" {
  description = "Whether the RDS instance should be publicly accessible"
  type        = bool
  default     = false
}
```

**Step 2: Add security group rule for Hetzner IP**

In `terraform/modules/networking/main.tf`, add to the RDS security group:

```hcl
# Allow access from Hetzner VPS (add after existing rules)
resource "aws_security_group_rule" "rds_from_hetzner" {
  count             = var.hetzner_vps_ip != "" ? 1 : 0
  type              = "ingress"
  from_port         = 5432
  to_port           = 5432
  protocol          = "tcp"
  cidr_blocks       = ["${var.hetzner_vps_ip}/32"]
  security_group_id = aws_security_group.rds.id
  description       = "PostgreSQL from Hetzner VPS"
}
```

Add variable:

```hcl
variable "hetzner_vps_ip" {
  description = "Hetzner VPS IP address for RDS access"
  type        = string
  default     = ""
}
```

**Step 3: Commit**

```bash
git add terraform/modules/rds/ terraform/modules/networking/
git commit -m "feat(terraform): allow public RDS access from Hetzner"
```

---

## Phase 5: User Action Items

### Task 9: USER ACTION - Hetzner Setup

**⚠️ STOP HERE - User action required**

The following must be done manually:

1. **Create Hetzner Cloud account** at https://console.hetzner.cloud

2. **Generate API token:**
   - Go to Security → API Tokens
   - Create new token with Read & Write permissions
   - Save token securely

3. **Generate SSH key pair** (if not already done):
   ```bash
   ssh-keygen -t ed25519 -C "deploy@mind-the-wait" -f ~/.ssh/mind-the-wait-deploy
   ```

4. **Create terraform.tfvars:**
   ```bash
   cd terraform/environments/hetzner-prod
   cp terraform.tfvars.example terraform.tfvars
   # Edit with your values
   ```

5. **Provision the VPS:**
   ```bash
   terraform init
   terraform plan
   terraform apply
   ```

6. **Note the server IP** from terraform output

---

### Task 10: USER ACTION - GitHub Secrets

**⚠️ STOP HERE - User action required**

Add these secrets to GitHub repository settings:

| Secret Name | Value |
|------------|-------|
| `HETZNER_SERVER_IP` | VPS IP from terraform output |
| `SSH_PRIVATE_KEY` | Contents of `~/.ssh/mind-the-wait-deploy` |
| `APP_SECRET` | 64-char random string |
| `DATABASE_URL` | `postgresql://mindthewait_admin:PASSWORD@mind-the-wait-prod.xxx.rds.amazonaws.com:5432/mindthewait` |
| `MERCURE_JWT_SECRET` | Random string |
| `OPENAI_API_KEY` | Your OpenAI key |
| `GTFS_RT_VEHICLES_URL` | `https://saskprdtmgtfs.sasktrpcloud.com/.../VehiclePositions.pb` |
| `GTFS_RT_TRIPS_URL` | `https://saskprdtmgtfs.sasktrpcloud.com/.../TripUpdates.pb` |
| `GTFS_RT_ALERTS_URL` | `https://saskprdtmgtfs.sasktrpcloud.com/.../Alerts.pb` |
| `ARCGIS_ROUTES_URL` | ArcGIS routes endpoint |
| `ARCGIS_STOPS_URL` | ArcGIS stops endpoint |
| `ARCGIS_TRIPS_URL` | ArcGIS trips endpoint |
| `ARCGIS_STOP_TIMES_URL` | ArcGIS stop_times endpoint |
| `GA4_MEASUREMENT_ID` | Google Analytics ID (optional) |

---

### Task 11: USER ACTION - Update RDS Security

**⚠️ STOP HERE - User action required**

After getting the Hetzner VPS IP:

1. **Update terraform.tfvars in AWS prod:**
   ```hcl
   # Add to terraform/environments/prod/terraform.tfvars
   hetzner_vps_ip      = "YOUR_HETZNER_IP"
   rds_publicly_accessible = true
   ```

2. **Apply AWS changes:**
   ```bash
   cd terraform/environments/prod
   terraform plan
   terraform apply
   ```

---

## Phase 6: Deploy & Cutover

### Task 12: Initial Deployment

**After user completes Tasks 9-11:**

1. **SSH into Hetzner VPS:**
   ```bash
   ssh deploy@YOUR_HETZNER_IP
   ```

2. **Clone repository:**
   ```bash
   git clone https://github.com/YOUR_ORG/mind-the-wait.git
   cd mind-the-wait
   ```

3. **Trigger GitHub Actions deployment** or deploy manually:
   ```bash
   # Manual deployment (first time)
   docker compose -f docker/compose.prod.yaml up -d --build
   docker compose -f docker/compose.prod.yaml exec php bin/console doctrine:migrations:migrate --no-interaction
   docker compose -f docker/compose.prod.yaml exec php bin/console cache:warmup
   ```

4. **Reload GTFS data to fix route mismatch:**
   ```bash
   docker compose -f docker/compose.prod.yaml exec php bin/console app:gtfs:load --mode=arcgis
   ```

5. **Verify site works:**
   ```bash
   curl -I https://YOUR_HETZNER_IP  # Should work after DNS propagates
   ```

---

### Task 13: DNS Cutover

1. **Verify everything works** on Hetzner IP directly

2. **Update Cloudflare DNS** (automatic via Terraform, or manual):
   - Point `mind-the-wait.ca` A record to Hetzner IP
   - Remove old AWS ALB references

3. **Wait for DNS propagation** (usually < 5 minutes with Cloudflare)

4. **Verify production site:** https://mind-the-wait.ca

---

### Task 14: Tear Down AWS ECS

**Only after confirming Hetzner is working:**

```bash
cd terraform/environments/prod

# Remove ECS-related resources (keep RDS!)
# Option 1: Targeted destroy
terraform destroy -target=module.ecs_service -target=module.ecs_cluster -target=module.alb -target=module.elasticache -target=module.ecr

# Option 2: Or manually delete via AWS Console
```

**Keep these AWS resources:**
- RDS PostgreSQL database
- S3 terraform state bucket
- VPC (if RDS needs it)
