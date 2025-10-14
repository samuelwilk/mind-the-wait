# Docker Setup

This directory contains all Docker-related configuration for the mind-the-wait project.

## Directory Structure

```
docker/
├── README.md              ← You are here
├── compose.yaml           ← Local development Docker Compose config
├── dev/                   ← Local development configs (PHP-FPM + Nginx)
│   ├── Dockerfile         ← PHP-FPM container for local dev
│   ├── nginx.conf         ← Nginx reverse proxy config
│   ├── php.ini            ← PHP configuration for dev
│   └── certs/             ← Local SSL certificates for HTTPS
└── pyparser/              ← Python GTFS-RT parser (used in local + prod)
    ├── Dockerfile
    ├── parser.py
    ├── healthcheck.py
    └── requirements.txt

Root directory (../)
├── Dockerfile             ← Production (FrankenPHP for AWS ECS)
└── frankenphp/            ← Production configs
    ├── Caddyfile          ← Webserver config
    ├── docker-entrypoint.sh
    └── conf.d/            ← PHP configuration
        ├── 10-app.ini
        ├── 20-app.dev.ini
        └── 20-app.prod.ini
```

## Local Development vs Production

### Local Development (PHP-FPM + Nginx)

**Used for**: Day-to-day development on your machine

**Stack**:
- PHP 8.3-FPM (docker/dev/Dockerfile)
- Nginx 1.27 (reverse proxy)
- PostgreSQL 16
- Redis 7
- Python parser (docker/pyparser/)

**Commands**:
```bash
# Start containers
make docker-up

# Build and start
make docker-build

# Stop containers
make docker-down

# Access PHP container
make docker-php
```

**Access**: `https://localhost` (port 8080 HTTP, 443 HTTPS)

### Production (FrankenPHP on AWS ECS)

**Used for**: AWS ECS Fargate deployment

**Stack**:
- FrankenPHP 1.x with PHP 8.4 (root Dockerfile)
- Caddy webserver (configured via frankenphp/Caddyfile)
- RDS PostgreSQL 16
- ElastiCache Redis 7
- Python parser (docker/pyparser/)

**Build**:
```bash
# Build production image
docker build -t mind-the-wait:prod --target frankenphp_prod .

# Build pyparser
docker build -t mind-the-wait-pyparser:prod -f docker/pyparser/Dockerfile docker/pyparser/
```

**Deploy**: Automated via GitHub Actions on release

## Why Two Different Setups?

### Local Dev (PHP-FPM + Nginx)
- **Faster iteration**: Code changes reflect immediately
- **Better debugging**: Xdebug integration, detailed error output
- **Familiar tools**: Standard PHP-FPM + Nginx stack
- **Volume mounting**: Edit code on host, runs in container
- **Resource efficient**: Lightweight for laptops

### Production (FrankenPHP)
- **High performance**: Built-in HTTP/2, HTTP/3 support
- **Better concurrency**: Worker mode for Symfony
- **Simplified stack**: Single binary (no separate webserver needed)
- **Modern features**: Native gRPC, WebSocket support
- **Security**: Built-in security features, auto HTTPS

## Services

### PHP Container (`php`)
Main application container running Symfony.

**Local**: PHP 8.3-FPM
```bash
docker compose -f docker/compose.yaml exec php bash
docker compose -f docker/compose.yaml exec php bin/console cache:clear
```

**Production**: FrankenPHP (runs in AWS ECS, not locally)

### Nginx Container (`nginx`)
**Local only** - Reverse proxy for PHP-FPM.

Handles:
- SSL termination (local self-signed certs)
- Static file serving
- Request routing to PHP-FPM

**Config**: `docker/dev/nginx.conf`

### Database Container (`database`)
PostgreSQL 16 for application data.

**Local**: docker/compose.yaml service
**Production**: AWS RDS PostgreSQL 16

### Redis Container (`redis`)
Cache and message queue.

**Local**: docker/compose.yaml service
**Production**: AWS ElastiCache Redis 7

### Pyparser Container (`pyparser`)
Python script that polls GTFS-RT protobuf feeds and writes to Redis.

**Dockerfile**: `docker/pyparser/Dockerfile`
**Used in**: Both local and production

Polls every 12 seconds:
- Vehicle positions
- Trip updates
- Service alerts

### Scheduler Container (`scheduler`)
Symfony Messenger consumer running scheduled tasks:
- `scheduler_score_tick` - Headway scoring every 30s
- `scheduler_weather_collection` - Weather data every 15 min
- `scheduler_performance_aggregation` - Daily route stats

**Local**: docker/compose.yaml service
**Production**: AWS ECS task

### Worker Container (`worker`)
**Optional** - Symfony Messenger async worker

**Enable**:
```bash
docker compose -f docker/compose.yaml --profile queue up -d worker
```

## Common Tasks

### Start Development Environment
```bash
make docker-build  # First time
make docker-up     # Subsequent times
```

### Run Database Migrations
```bash
make database-migrations-execute
```

### Load GTFS Data
```bash
make gtfs-load
```

### Run Tests
```bash
make test-phpunit
```

### View Logs
```bash
# All services
docker compose -f docker/compose.yaml logs -f

# Specific service
docker compose -f docker/compose.yaml logs -f scheduler
docker compose -f docker/compose.yaml logs -f pyparser
```

### Rebuild After Dependency Changes
```bash
make docker-build
make composer-install
```

## Environment Variables

Configure in `.env.local` (not committed to git):

```bash
# Database (local dev)
DATABASE_URL=postgresql://app:app@database:5432/app

# Redis
REDIS_URL=redis://redis:6379

# Application
APP_ENV=dev
APP_SECRET=changeme

# OpenAI (for AI insights)
OPENAI_API_KEY=sk-proj-...

# GTFS Data
MTW_GTFS_STATIC_URL=https://apps2.saskatoon.ca/transit/google_transit.zip
```

## Troubleshooting

### Port Already in Use
If ports 8080 or 443 are in use:
```bash
# Check what's using the port
lsof -i :8080
lsof -i :443

# Stop conflicting service or change ports in docker/compose.yaml
```

### Database Connection Refused
```bash
# Check if database is running
docker compose -f docker/compose.yaml ps database

# Restart database
docker compose -f docker/compose.yaml restart database

# Check logs
docker compose -f docker/compose.yaml logs database
```

### Pyparser Not Updating Redis
```bash
# Check pyparser logs
docker compose -f docker/compose.yaml logs pyparser

# Restart pyparser
docker compose -f docker/compose.yaml restart pyparser

# Check Redis connection
docker compose -f docker/compose.yaml exec redis redis-cli ping
```

### Scheduler Not Running Tasks
```bash
# Check scheduler logs
docker compose -f docker/compose.yaml logs -f scheduler

# Restart scheduler
docker compose -f docker/compose.yaml restart scheduler
```

### Clear Everything and Start Fresh
```bash
# Stop and remove all containers + volumes
make docker-prune

# Rebuild from scratch
make docker-build
make database
```

## Production Deployment

Production deployment is fully automated via GitHub Actions:

1. **Push to main**: Builds images, pushes to ECR with commit SHA
2. **Create release**: Builds images with `:latest` tag, deploys to ECS

**Manual deployment**:
```bash
# Build production images locally
docker build -t mind-the-wait:prod --target frankenphp_prod .
docker build -t mind-the-wait-pyparser:prod -f docker/pyparser/Dockerfile docker/pyparser/

# Tag for ECR
docker tag mind-the-wait:prod 123456789.dkr.ecr.ca-central-1.amazonaws.com/mind-the-wait/php:latest
docker tag mind-the-wait-pyparser:prod 123456789.dkr.ecr.ca-central-1.amazonaws.com/mind-the-wait/pyparser:latest

# Push to ECR (after aws ecr get-login-password)
docker push 123456789.dkr.ecr.ca-central-1.amazonaws.com/mind-the-wait/php:latest
docker push 123456789.dkr.ecr.ca-central-1.amazonaws.com/mind-the-wait/pyparser:latest
```

See `docs/infrastructure/` for complete deployment documentation.

## Further Reading

- **Dockerfile**: See root `Dockerfile` for production build stages
- **Compose**: See `docker/compose.yaml` for full local dev configuration
- **Infrastructure**: See `docs/infrastructure/` for AWS deployment guides
- **Makefile**: See root `Makefile` for all available commands
