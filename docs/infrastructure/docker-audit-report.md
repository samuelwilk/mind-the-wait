# Docker Production Readiness Audit

**Date**: 2025-01-13
**Status**: ⚠️ CRITICAL ISSUES FOUND
**Severity**: HIGH - Production deployment will fail

## Executive Summary

The production Dockerfile (root `/Dockerfile`) has critical missing dependencies and references non-existent files. The application will fail to start in AWS ECS.

## Critical Issues

### 1. ❌ CRITICAL: Missing Database Extension (pdo_pgsql)

**Location**: `/Dockerfile` line 26-32

**Problem**: Production image missing PostgreSQL extension
```dockerfile
RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
	;
```

**Missing**: `pdo_pgsql` extension

**Impact**: Application cannot connect to RDS PostgreSQL database
- All database queries will fail
- Migrations cannot run
- Application will crash on startup

**Fix Required**:
```dockerfile
RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		pdo_pgsql \  # ADD THIS
	;
```

### 2. ❌ CRITICAL: Missing Redis Extension

**Location**: `/Dockerfile` line 26-32

**Problem**: Production image missing Redis extension

**Impact**:
- Cannot connect to ElastiCache Redis
- Realtime data (vehicle positions, trips) unavailable
- Caching layer non-functional
- Symfony Messenger queue non-functional

**Fix Required**:
```dockerfile
RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		pdo_pgsql \
		redis \  # ADD THIS
	;
```

### 3. ❌ CRITICAL: Missing FrankenPHP Configuration Files

**Location**: `/Dockerfile` lines 45-47

**Problem**: References non-existent files
```dockerfile
# COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/  # COMMENTED OUT - FILE MISSING
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint  # FILE MISSING
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile  # FILE MISSING
```

**Impact**:
- Docker build will fail
- Missing entrypoint script (critical for startup)
- Missing Caddyfile (webserver configuration)

**Status**: `.ini` files commented out (optional), but entrypoint and Caddyfile are REQUIRED

### 4. ⚠️ MAJOR: No Production PHP Configuration

**Location**: Production stage has no PHP tuning

**Missing optimizations**:
- `opcache.enable=1`
- `opcache.memory_consumption=256`
- `opcache.max_accelerated_files=20000`
- `realpath_cache_size=4096K`
- `memory_limit` (currently unlimited, dangerous)
- Error logging configuration

**Impact**: Poor performance, potential memory exhaustion

### 5. ⚠️ MAJOR: Running as Root User

**Location**: Entire Dockerfile

**Problem**: No `USER` directive, runs as root

**Security Risk**: Container runs with root privileges
- Violates principle of least privilege
- Security vulnerability if container is compromised

**Fix Required**:
```dockerfile
# Before ENTRYPOINT
RUN addgroup --gid 1000 app && adduser --uid 1000 --gid 1000 --disabled-password app
RUN chown -R app:app /app
USER app
```

### 6. ⚠️ MODERATE: Pyparser Dockerfile Location Mismatch

**Problem**:
- AWS ECS config builds from `./pyparser` context
- But `pyparser/Dockerfile` doesn't exist
- `docker/pyparser.Dockerfile` exists but won't be found

**Impact**: Pyparser container build will fail in AWS

**Fix**: Create `pyparser/Dockerfile` or update ECS task definition

## Configuration Comparison

| Feature | Local Dev (docker/) | Production (root) | Status |
|---------|---------------------|-------------------|--------|
| PostgreSQL Extension | ✅ pdo_pgsql | ❌ MISSING | CRITICAL |
| Redis Extension | ✅ redis | ❌ MISSING | CRITICAL |
| FrankenPHP Config | N/A (uses nginx) | ❌ Files missing | CRITICAL |
| PHP Tuning | ✅ docker/php.ini | ❌ No config | MAJOR |
| Security (non-root) | ✅ www-data | ❌ Runs as root | MAJOR |
| Healthcheck | ✅ | ✅ | OK |

## Recommendations

### Immediate Actions (Required for Deployment)

1. **Fix root Dockerfile**:
   - Add `pdo_pgsql` extension
   - Add `redis` extension
   - Create missing frankenphp/ files
   - Add production PHP configuration
   - Switch to non-root user

2. **Fix pyparser Dockerfile location**:
   - Move `docker/pyparser.Dockerfile` to `pyparser/Dockerfile`
   - OR update Terraform ECS task definitions

3. **Test production build locally**:
   ```bash
   docker build -t test-prod --target frankenphp_prod .
   docker run test-prod php -m | grep -E "pdo_pgsql|redis"
   ```

### Production Hardening (Recommended)

4. **Add production PHP configuration**:
   - Create `frankenphp/conf.d/20-app.prod.ini`
   - Tune OPcache, memory limits, error logging

5. **Add security headers**:
   - Configure Caddyfile with security headers
   - HSTS, CSP, X-Frame-Options

6. **Monitoring**:
   - Add CloudWatch logging
   - Add application performance monitoring

7. **Secrets management**:
   - Verify no secrets in images (✅ currently OK)
   - Use AWS Secrets Manager for runtime secrets

## Next Steps

1. ✅ Fix critical issues in Dockerfile
2. ✅ Create missing frankenphp configuration files
3. ✅ Move pyparser Dockerfile to correct location
4. ✅ Test builds locally
5. ✅ Commit fixes
6. ✅ Re-run GitHub Actions CI/CD
7. ✅ Deploy to AWS ECS

## Files to Create

### Required Files
- `frankenphp/docker-entrypoint.sh` - Container startup script
- `frankenphp/Caddyfile` - Webserver configuration
- `pyparser/Dockerfile` - Python parser container

### Optional But Recommended
- `frankenphp/conf.d/10-app.ini` - Base PHP configuration
- `frankenphp/conf.d/20-app.prod.ini` - Production PHP tuning
- `frankenphp/conf.d/20-app.dev.ini` - Development PHP configuration

## Timeline Estimate

- Fixing critical issues: 30-45 minutes
- Testing: 15 minutes
- Total: ~1 hour to production-ready state

## Risk Assessment

**Current Risk Level**: 🔴 HIGH

**With Fixes Applied**: 🟢 LOW

The application WILL NOT RUN in production without these fixes. However, all issues are straightforward to resolve.
