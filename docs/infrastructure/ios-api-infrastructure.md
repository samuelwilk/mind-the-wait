# iOS API Infrastructure Configuration

This document describes the infrastructure configuration required to support the mobile iOS app API endpoints.

## Overview

The iOS app consumes JSON APIs from the existing Symfony backend with minimal infrastructure changes. The strategy is to reuse existing infrastructure (ECS, RDS, Redis, ALB) and add edge caching via CloudFront.

**Incremental Cost:** ~$3/month (CloudFront + slight ALB traffic increase)

---

## 1. CORS Configuration ✅

**Status:** Implemented

**Package:** `nelmio/cors-bundle`

**Configuration:** `config/packages/nelmio_cors.yaml`

### What Was Configured

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['*'] # Change to specific origins in production
        allow_methods: ['GET', 'POST', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        max_age: 3600
    paths:
        # Mobile API endpoints (v1)
        '^/api/v1/':
            allow_origin: ['*']
            allow_methods: ['GET', 'OPTIONS']
            max_age: 3600

        # Web API endpoints (unversioned)
        '^/api/':
            allow_origin: ['*']
            allow_methods: ['GET', 'POST', 'OPTIONS']
            max_age: 3600
```

### Testing CORS

```bash
# Test preflight OPTIONS request
curl -X OPTIONS https://mindthewait.ca/api/v1/routes \
  -H "Origin: https://example.com" \
  -H "Access-Control-Request-Method: GET" \
  -v

# Verify headers in response:
# - Access-Control-Allow-Origin: *
# - Access-Control-Allow-Methods: GET, OPTIONS
# - Access-Control-Max-Age: 3600
```

### Production Hardening

For production, restrict `allow_origin` to specific domains or the iOS app bundle ID:

```yaml
allow_origin: ['https://mindthewait.ca', 'capacitor://localhost']
```

---

## 2. Rate Limiting ✅

**Status:** Configured (optional enforcement)

**Configuration:** `config/packages/rate_limiter.yaml`

### What Was Configured

```yaml
framework:
    rate_limiter:
        mobile_api:
            policy: 'sliding_window'
            limit: 120  # 120 requests per 60 seconds per IP
            interval: '60 seconds'
```

### Applying Rate Limiting to Controllers

To enforce rate limiting on specific endpoints, inject `RateLimiterFactory` into controller methods:

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/v1/routes', name: 'api_v1_routes_list', methods: ['GET'])]
public function listRoutes(
    Request $request,
    RateLimiterFactory $mobileApiLimiter
): JsonResponse {
    $limiter = $mobileApiLimiter->create($request->getClientIp());

    if (!$limiter->consume(1)->isAccepted()) {
        return $this->json(['error' => 'Too many requests'], 429);
    }

    // ... existing code
}
```

### Monitoring Rate Limits

```bash
# Check Redis for rate limit keys
docker compose -f docker/compose.yaml exec redis redis-cli KEYS "rate_limiter:*"

# View rate limit for specific IP
docker compose -f docker/compose.yaml exec redis redis-cli GET "rate_limiter:mobile_api:192.168.1.1"
```

### When to Enable

Rate limiting is **optional** for initial launch. Enable it if:
- API abuse is detected (excessive requests from single IPs)
- CloudWatch shows unusual traffic patterns
- AWS costs exceed budget

**Recommendation:** Start without enforcement, monitor for 2-4 weeks, then enable if needed.

---

## 3. Health Check Endpoint ✅

**Status:** Implemented

**Endpoint:** `GET /api/healthz`

**Controller:** `src/Controller/HealthController.php`

### Response Format

```json
{
  "status": "ok",
  "timestamp": 1759897200
}
```

### ALB Health Check Configuration

Update ALB target group health check settings:

```yaml
# terraform/alb.tf (or CloudFormation equivalent)
resource "aws_lb_target_group" "php" {
  health_check {
    enabled             = true
    healthy_threshold   = 2
    unhealthy_threshold = 3
    timeout             = 5
    interval            = 30
    path                = "/api/healthz"
    matcher             = "200"
  }
}
```

### Testing Health Check

```bash
# Local development
curl -sk https://localhost/api/healthz | jq

# Production
curl https://mindthewait.ca/api/healthz | jq
```

---

## 4. CloudFront Cache Configuration

**Status:** Documentation only (requires IaC deployment)

CloudFront edge caching reduces API latency globally and lowers AWS costs by caching responses at edge locations.

### Cache Behaviors

Add these cache behaviors to your CloudFront distribution (Terraform/CloudFormation):

#### Routes List (High Cache)

```yaml
CacheBehaviors:
  - PathPattern: /api/v1/routes
    TargetOriginId: ALB
    ViewerProtocolPolicy: redirect-to-https
    AllowedMethods: [GET, HEAD, OPTIONS]
    CachedMethods: [GET, HEAD, OPTIONS]
    CachePolicyId: CachingOptimized
    OriginRequestPolicyId: AllViewerExceptHostHeader
    Compress: true
    MinTTL: 300       # 5 minutes
    DefaultTTL: 300   # 5 minutes
    MaxTTL: 600       # 10 minutes
```

**Why:** Route list changes infrequently (only when new routes added or metrics update). 5-minute cache dramatically reduces ALB hits.

#### Route Detail (Medium Cache)

```yaml
  - PathPattern: /api/v1/routes/*
    TargetOriginId: ALB
    ViewerProtocolPolicy: redirect-to-https
    AllowedMethods: [GET, HEAD, OPTIONS]
    CachedMethods: [GET, HEAD, OPTIONS]
    CachePolicyId: CachingOptimized
    Compress: true
    MinTTL: 600       # 10 minutes
    DefaultTTL: 600   # 10 minutes
    MaxTTL: 1200      # 20 minutes
```

**Why:** Route detail pages include 30-day statistics that update hourly. 10-minute cache is safe.

#### Stops List (Very High Cache)

```yaml
  - PathPattern: /api/v1/stops
    TargetOriginId: ALB
    ViewerProtocolPolicy: redirect-to-https
    AllowedMethods: [GET, HEAD, OPTIONS]
    CachedMethods: [GET, HEAD, OPTIONS]
    CachePolicyId: CachingOptimized
    Compress: true
    MinTTL: 3600      # 1 hour
    DefaultTTL: 3600  # 1 hour
    MaxTTL: 86400     # 24 hours
```

**Why:** Stop data (lat/lon, names) rarely changes. GTFS static updates are infrequent (weekly/monthly).

#### Stop Predictions (No Cache)

```yaml
  - PathPattern: /api/v1/stops/*/predictions
    TargetOriginId: ALB
    ViewerProtocolPolicy: redirect-to-https
    AllowedMethods: [GET, HEAD, OPTIONS]
    CachePolicyId: CachingDisabled
    OriginRequestPolicyId: AllViewerExceptHostHeader
    Compress: true
```

**Why:** Realtime predictions must always be fresh. No caching.

#### Realtime Vehicle Data (No Cache)

```yaml
  - PathPattern: /api/realtime
    TargetOriginId: ALB
    ViewerProtocolPolicy: redirect-to-https
    AllowedMethods: [GET, HEAD, OPTIONS]
    CachePolicyId: CachingDisabled
    OriginRequestPolicyId: AllViewerExceptHostHeader
    Compress: true
```

**Why:** Vehicle positions update every 15-30 seconds. No caching.

### Cache Policy Details

**CachingOptimized** (AWS Managed Policy):
- Caches based on query strings
- Respects `Cache-Control` headers from origin
- Compresses responses (gzip/brotli)

**CachingDisabled** (AWS Managed Policy):
- Never caches responses
- Always forwards requests to origin
- Useful for realtime data

### Custom Cache Policy (Recommended)

For finer control, create a custom cache policy:

```yaml
CachePolicy:
  Name: MindTheWaitAPICachePolicy
  MinTTL: 0
  MaxTTL: 86400
  DefaultTTL: 300
  ParametersInCacheKeyAndForwardedToOrigin:
    QueryStringsConfig:
      QueryStringBehavior: whitelist
      QueryStrings:
        - route_id  # Allow caching with route_id parameter
        - limit     # Allow caching with limit parameter
    HeadersConfig:
      HeaderBehavior: none
    CookiesConfig:
      CookieBehavior: none
    EnableAcceptEncodingGzip: true
    EnableAcceptEncodingBrotli: true
```

### Terraform Example

```hcl
# terraform/cloudfront.tf

resource "aws_cloudfront_distribution" "main" {
  enabled = true
  aliases = ["mindthewait.ca"]

  origin {
    domain_name = aws_lb.main.dns_name
    origin_id   = "ALB"

    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols   = ["TLSv1.2"]
    }
  }

  # Default behavior (no caching for web pages)
  default_cache_behavior {
    target_origin_id       = "ALB"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods         = ["GET", "HEAD"]
    cache_policy_id        = data.aws_cloudfront_cache_policy.caching_disabled.id
    compress               = true
  }

  # Route list (5 min cache)
  ordered_cache_behavior {
    path_pattern           = "/api/v1/routes"
    target_origin_id       = "ALB"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD", "OPTIONS"]
    cached_methods         = ["GET", "HEAD", "OPTIONS"]
    cache_policy_id        = aws_cloudfront_cache_policy.api_cache.id
    compress               = true
  }

  # Stops list (1 hour cache)
  ordered_cache_behavior {
    path_pattern           = "/api/v1/stops"
    target_origin_id       = "ALB"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD", "OPTIONS"]
    cached_methods         = ["GET", "HEAD", "OPTIONS"]
    cache_policy_id        = aws_cloudfront_cache_policy.stops_cache.id
    compress               = true
  }

  # Realtime data (no cache)
  ordered_cache_behavior {
    path_pattern           = "/api/realtime"
    target_origin_id       = "ALB"
    viewer_protocol_policy = "redirect-to-https"
    allowed_methods        = ["GET", "HEAD", "OPTIONS"]
    cache_policy_id        = data.aws_cloudfront_cache_policy.caching_disabled.id
    compress               = true
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    acm_certificate_arn      = aws_acm_certificate.main.arn
    ssl_support_method       = "sni-only"
    minimum_protocol_version = "TLSv1.2_2021"
  }
}

resource "aws_cloudfront_cache_policy" "api_cache" {
  name        = "mind-the-wait-api-cache"
  min_ttl     = 300
  max_ttl     = 600
  default_ttl = 300

  parameters_in_cache_key_and_forwarded_to_origin {
    query_strings_config {
      query_string_behavior = "whitelist"
      query_strings {
        items = ["route_id", "limit"]
      }
    }

    headers_config {
      header_behavior = "none"
    }

    cookies_config {
      cookie_behavior = "none"
    }

    enable_accept_encoding_gzip   = true
    enable_accept_encoding_brotli = true
  }
}

resource "aws_cloudfront_cache_policy" "stops_cache" {
  name        = "mind-the-wait-stops-cache"
  min_ttl     = 3600
  max_ttl     = 86400
  default_ttl = 3600

  parameters_in_cache_key_and_forwarded_to_origin {
    query_strings_config {
      query_string_behavior = "whitelist"
      query_strings {
        items = ["route_id"]
      }
    }

    headers_config {
      header_behavior = "none"
    }

    cookies_config {
      cookie_behavior = "none"
    }

    enable_accept_encoding_gzip   = true
    enable_accept_encoding_brotli = true
  }
}

data "aws_cloudfront_cache_policy" "caching_disabled" {
  name = "Managed-CachingDisabled"
}
```

### Monitoring CloudFront

After deployment, monitor CloudFront metrics in CloudWatch:

```bash
# Cache hit ratio (target: >70% for cached endpoints)
aws cloudwatch get-metric-statistics \
  --namespace AWS/CloudFront \
  --metric-name CacheHitRate \
  --dimensions Name=DistributionId,Value=E1234567890ABC \
  --start-time 2025-01-01T00:00:00Z \
  --end-time 2025-01-02T00:00:00Z \
  --period 3600 \
  --statistics Average

# Request count (all requests hitting CloudFront)
aws cloudwatch get-metric-statistics \
  --namespace AWS/CloudFront \
  --metric-name Requests \
  --dimensions Name=DistributionId,Value=E1234567890ABC \
  --start-time 2025-01-01T00:00:00Z \
  --end-time 2025-01-02T00:00:00Z \
  --period 3600 \
  --statistics Sum
```

### Cache Invalidation

When deploying API changes, invalidate CloudFront cache:

```bash
# Invalidate all API endpoints
aws cloudfront create-invalidation \
  --distribution-id E1234567890ABC \
  --paths "/api/v1/*" "/api/realtime"

# Invalidate specific endpoint
aws cloudfront create-invalidation \
  --distribution-id E1234567890ABC \
  --paths "/api/v1/routes"
```

**Cost:** First 1,000 invalidations per month are free, then $0.005 per path.

**Recommendation:** Use versioned API paths (`/api/v2/routes`) instead of invalidations when making breaking changes.

---

## 5. No New Infrastructure Required ✅

### What Stays Unchanged

- **ECS Tasks:** php, scheduler, pyparser containers run unchanged
- **RDS PostgreSQL:** No schema changes required
- **ElastiCache Redis:** Same keys, same usage pattern
- **ALB:** No configuration changes (CORS handled by Symfony)
- **No Lambda functions needed**
- **No API Gateway needed** (ALB + CloudFront is sufficient)

### Cost Breakdown

| Service | Current (Web) | iOS Addition | Monthly Cost |
|---------|---------------|--------------|--------------|
| ECS Tasks | $15 | None (reuse) | $0 |
| RDS PostgreSQL | $25 | None | $0 |
| ElastiCache Redis | $15 | None | $0 |
| ALB | $18 | +10% traffic | ~$2 |
| CloudFront | $1 | JSON APIs cached | ~$1 |
| OpenAI API | $0.05 | Shared cache | $0 |
| **Total Incremental** | — | — | **~$3/month** |

---

## 6. Security Checklist

Before launching iOS app to production:

- [ ] Update CORS `allow_origin` to restrict to specific domains or app bundle ID
- [ ] Enable rate limiting on high-traffic endpoints
- [ ] Set up CloudWatch alarms for ALB 5xx errors (threshold: 10/min)
- [ ] Set up CloudWatch alarms for RDS CPU (threshold: 80%)
- [ ] Enable AWS WAF rules on CloudFront (optional, +$5/month)
- [ ] Configure CloudFront geo-restrictions if limiting to Canada only
- [ ] Set up billing alerts at $100/month
- [ ] Review CloudFront access logs for unusual patterns
- [ ] Enable CloudFront request/response logging (optional, costs extra)

---

## 7. Deployment Checklist

### Pre-Deployment

- [x] Install and configure `nelmio/cors-bundle`
- [x] Create health check endpoint (`/api/healthz`)
- [x] Configure rate limiting (optional enforcement)
- [ ] Update CloudFront distribution with cache behaviors
- [ ] Test CORS headers with iOS simulator
- [ ] Test all API endpoints with `curl` and Postman
- [ ] Run `make test-phpunit` to ensure no regressions
- [ ] Update ALB health check to use `/api/healthz`

### Deployment

- [ ] Deploy updated Symfony code to ECS
- [ ] Wait for ECS tasks to stabilize (health checks passing)
- [ ] Update CloudFront distribution (Terraform apply)
- [ ] Invalidate CloudFront cache for `/api/v1/*`
- [ ] Test API endpoints through CloudFront distribution
- [ ] Monitor CloudWatch logs for errors

### Post-Deployment

- [ ] Monitor CloudWatch metrics for 24 hours
- [ ] Check CloudFront cache hit ratio (target: >70%)
- [ ] Monitor AWS costs daily for first week
- [ ] Test iOS app with production API
- [ ] Collect user feedback on API performance

---

## 8. Troubleshooting

### CORS Errors in iOS App

**Symptom:** iOS app shows "CORS policy blocked" errors

**Solution:**
```bash
# Test CORS headers
curl -X OPTIONS https://mindthewait.ca/api/v1/routes \
  -H "Origin: https://example.com" \
  -H "Access-Control-Request-Method: GET" \
  -v

# Verify response includes:
# Access-Control-Allow-Origin: *
# Access-Control-Allow-Methods: GET, OPTIONS
```

If headers are missing, check:
1. `nelmio/cors-bundle` is installed
2. `config/packages/nelmio_cors.yaml` exists
3. Symfony cache cleared: `make cc`

### CloudFront Not Caching

**Symptom:** CloudWatch shows 0% cache hit rate

**Possible causes:**
1. Query strings not in cache key (e.g., `?timestamp=123`)
2. `Cache-Control: no-cache` header from origin
3. Cookies being set (breaks caching)

**Solution:**
- Use custom cache policy with query string whitelist
- Verify origin `Cache-Control` headers: `curl -I https://mindthewait.ca/api/v1/routes`
- Disable cookie forwarding in CloudFront

### Health Check Failing

**Symptom:** ALB marks ECS tasks as unhealthy

**Solution:**
```bash
# Test health endpoint locally
docker compose -f docker/compose.yaml exec php bin/console router:match /api/healthz

# Check nginx logs
docker compose -f docker/compose.yaml logs nginx | grep healthz

# Verify response
curl -sk https://localhost/api/healthz | jq
```

### Rate Limiting Too Aggressive

**Symptom:** Legitimate iOS users getting 429 errors

**Solution:**
- Increase limit in `config/packages/rate_limiter.yaml` (e.g., 240/min)
- Whitelist CloudFront IP ranges (rate limit by `X-Forwarded-For` header)
- Disable rate limiting temporarily and monitor for abuse

---

## 9. Future Enhancements

### Phase 2: API Versioning

When breaking changes are needed:

```php
#[Route('/api/v2/routes', name: 'api_v2_routes_list', methods: ['GET'])]
public function listRoutesV2(): JsonResponse {
    // New schema
}
```

Deploy both v1 and v2 simultaneously, deprecate v1 after 6 months.

### Phase 3: Multi-Region CloudFront

For international users, add CloudFront distributions in:
- US East (existing)
- EU West (Frankfurt)
- Asia Pacific (Singapore)

**Cost:** ~$2/month per region

### Phase 4: GraphQL API (Optional)

If mobile API grows to 20+ endpoints with complex relationships, consider GraphQL:

```bash
composer require api-platform/core
```

**Tradeoff:** Increased complexity vs reduced over-fetching

---

## Summary

Infrastructure changes for iOS app are **minimal and additive**:

1. ✅ CORS configured (allow cross-origin requests)
2. ✅ Rate limiting configured (optional enforcement)
3. ✅ Health check endpoint created
4. 📝 CloudFront cache behaviors documented (requires IaC deployment)

**Total new infrastructure:** $0 (CloudFront caching is ~$3/month incremental)

**Next steps:**
1. Test CORS headers with iOS simulator
2. Deploy CloudFront configuration updates (Terraform/CloudFormation)
3. Monitor CloudWatch metrics for 1 week
4. Adjust cache TTLs based on observed traffic patterns

**Deployment:** No downtime required. All changes are backwards-compatible with existing web app.

---

**Document Version:** 1.0
**Last Updated:** 2025-10-19
**Author:** Claude (Anthropic)
**Status:** Implementation Complete (pending CloudFront deployment)
