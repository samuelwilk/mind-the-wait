# iOS App Infrastructure Deployment Timeline

This document outlines when to apply various infrastructure changes during the iOS app development and release lifecycle.

## Overview

**Strategy:** Develop locally until iOS app is ready for release, then apply infrastructure changes incrementally as you approach production deployment.

**Key Principle:** Zero production changes during local development. Apply changes only when needed to support beta testing or public release.

---

## Phase 1: Local Development (Current - Week 6)

**Status:** ✅ Complete

**What's Applied:**
- CORS configuration (works in `dev` environment, allows all origins `*`)
- Rate limiting infrastructure installed (not enforced)
- Health check endpoint created (not used by ALB yet)
- iOS API v1 endpoints implemented and tested locally

**What You Can Do:**
- Develop iOS app against `https://localhost` or `https://mind-the-wait.local`
- Test all API endpoints without CORS restrictions
- Iterate quickly without worrying about production constraints

**No Production Deployment Required** ✅

---

## Phase 2: Internal Beta Testing (Week 6-7)

**Timeline:** 2-3 weeks before TestFlight launch

**Goal:** Verify iOS app works against staging/production backend

### Infrastructure Changes to Apply

#### 1. Deploy Latest Code to Production ECS ⚙️

```bash
# From your local machine or CI/CD
git push origin main

# Verify GitHub Actions deployment or manually deploy to ECS
# (Your existing CI/CD workflow should handle this)
```

**What This Deploys:**
- New API v1 endpoints (`/api/v1/routes`, `/api/v1/stops`, etc.)
- Health check endpoint (`/api/healthz`)
- CORS configuration (production uses `when@prod` - restrictive)
- Rate limiting infrastructure (installed but not enforced)

**Expected Behavior:**
- iOS app can call `https://mind-the-wait.ca/api/v1/*` endpoints
- CORS headers return: `access-control-allow-origin: https://mind-the-wait.ca`
- iOS app using native URLSession should work fine (native apps don't need CORS)
- WebView-based apps (Capacitor/Ionic) get allowed via `capacitor://localhost`

**Testing:**
```bash
# Verify production API works
curl https://mind-the-wait.ca/api/v1/routes | jq '.routes | length'

# Verify health check
curl https://mind-the-wait.ca/api/healthz | jq

# Verify CORS headers (for web testing)
curl -I https://mind-the-wait.ca/api/v1/routes \
  -H "Origin: https://mind-the-wait.ca"
```

**Deployment Duration:** ~5 minutes (ECS rolling update)

---

#### 2. Update ALB Health Check (Optional at this stage) 🏥

**When:** Before launching TestFlight, or if you notice ECS task flapping

**What:** Update ALB target group to use new health check endpoint

**Terraform/CloudFormation Example:**
```hcl
resource "aws_lb_target_group" "php" {
  # ... existing config ...

  health_check {
    enabled             = true
    healthy_threshold   = 2
    unhealthy_threshold = 3
    timeout             = 5
    interval            = 30
    path                = "/api/healthz"  # CHANGED from "/" or "/health"
    matcher             = "200"
  }
}
```

**Why Update?**
- More reliable health checks (dedicated endpoint)
- Faster response time (lightweight JSON response)
- Better monitoring (can track health check failures separately)

**Risk:** Low (health check is backwards compatible)

**Rollback:** Change back to previous health check path if issues occur

---

## Phase 3: Public Beta (TestFlight) (Week 7-8)

**Timeline:** 1-2 weeks before App Store submission

**Goal:** Scale infrastructure to handle 100+ beta testers

### Infrastructure Changes to Apply

#### 3. Monitor and Tune (No Changes Yet) 📊

**What to Monitor:**
- ALB request count (CloudWatch: `RequestCount` metric)
- API response times (CloudWatch: `TargetResponseTime`)
- ECS CPU/Memory usage
- Redis memory usage
- RDS connections

**CloudWatch Dashboard Example:**
```bash
# View ALB metrics
aws cloudwatch get-metric-statistics \
  --namespace AWS/ApplicationELB \
  --metric-name TargetResponseTime \
  --dimensions Name=LoadBalancer,Value=app/mind-the-wait-alb/... \
  --start-time 2025-10-01T00:00:00Z \
  --end-time 2025-10-02T00:00:00Z \
  --period 300 \
  --statistics Average
```

**Expected Traffic (100 beta users):**
- ~1,000 API requests/hour (10 requests/user/hour average)
- ~0.5 MB/s bandwidth
- Negligible cost impact (<$1/month)

**Action Items:**
- Set up CloudWatch billing alerts at $100/month
- Monitor for unusual traffic patterns
- Collect beta tester feedback on API performance

**No Infrastructure Changes Needed** ✅

---

## Phase 4: App Store Submission (Week 9-10)

**Timeline:** Just before App Store submission

**Goal:** Prepare for public launch at scale

### Infrastructure Changes to Apply

#### 4. Deploy CloudFront Cache Configuration (Optional) ☁️

**When:** 1 week before App Store submission

**Why:** Reduce API latency globally, lower AWS costs

**What:** Apply CloudFront cache behaviors from `docs/infrastructure/ios-api-infrastructure.md`

**Terraform Example:**
```hcl
# Add to terraform/cloudfront.tf

resource "aws_cloudfront_distribution" "main" {
  # ... existing config ...

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

**Deployment Steps:**
1. Add cache policies to Terraform
2. Run `terraform plan` to review changes
3. Run `terraform apply` to deploy
4. Test API through CloudFront: `https://mind-the-wait.ca/api/v1/routes`
5. Monitor cache hit ratio in CloudWatch

**Expected Impact:**
- Cache hit ratio: 70-80% for `/api/v1/routes` and `/api/v1/stops`
- Reduced ALB traffic by 60-70%
- Cost savings: ~$1-2/month
- Faster API response times globally

**Risk:** Low (CloudFront is transparent to clients)

**Rollback:** Remove cache behaviors if issues occur

**Decision:** **OPTIONAL** - Only apply if you expect >500 active users in first month

---

#### 5. Enable Rate Limiting (Optional) 🚦

**When:** If you see abuse or unusual traffic patterns

**Why:** Prevent API abuse, protect backend resources

**How:** Add rate limiter to specific endpoints

**Example:**
```php
// src/Controller/Api/RouteApiController.php

use Symfony\Component\RateLimiter\RateLimiterFactory;

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

**Configuration:** Already set up in `config/packages/rate_limiter.yaml`
- 120 requests per 60 seconds per IP
- Sliding window policy

**Decision:** **WAIT** - Only enable if you detect abuse

---

## Phase 5: Post-Launch (Month 2+)

**Timeline:** After App Store launch

**Goal:** Optimize based on real usage data

### Infrastructure Changes to Consider

#### 6. Fine-Tune Cache TTLs 🎯

**Based on CloudWatch metrics, adjust cache durations:**

```hcl
# If route list changes frequently
min_ttl = 180  # 3 min instead of 5 min

# If stops never change
min_ttl = 86400  # 24 hours instead of 1 hour
```

**Monitor:**
- Cache invalidation frequency
- Stale data reports from users
- API response time improvements

---

#### 7. Scale Infrastructure (If Needed) 📈

**Triggers:**
- >5,000 active users
- RDS CPU >80% sustained
- Redis memory >70% full
- ALB response time >500ms p95

**Scaling Options:**
1. **ECS Tasks:** Increase desired count from 2 to 4 (cost: +$15/month)
2. **RDS:** Upgrade instance class (e.g., db.t4g.small → db.t4g.medium) (cost: +$20/month)
3. **Redis:** Upgrade instance (cache.t4g.micro → cache.t4g.small) (cost: +$10/month)

**Decision:** Monitor first, scale only if metrics show need

---

## Summary Table

| Phase | Timeline | Infrastructure Change | Required? | Cost Impact | Risk |
|-------|----------|----------------------|-----------|-------------|------|
| **Local Dev** | Current | Code deployment (no infra) | ✅ Done | $0 | None |
| **Internal Beta** | Week 6-7 | Update ALB health check | Optional | $0 | Low |
| **Public Beta** | Week 7-8 | Monitor only | N/A | $0 | None |
| **App Store Prep** | Week 9-10 | Deploy CloudFront caching | Optional | +$3/mo | Low |
| **App Store Prep** | Week 9-10 | Enable rate limiting | If needed | $0 | Low |
| **Post-Launch** | Month 2+ | Fine-tune cache TTLs | If needed | $0 | Low |
| **Post-Launch** | Month 2+ | Scale infrastructure | If needed | +$45/mo | Low |

---

## Decision Tree

```
┌─────────────────────────────────────┐
│ iOS App Development Stage           │
└───────────┬─────────────────────────┘
            │
            ├─ Local Development (Weeks 1-6)
            │  └─> No infrastructure changes needed ✅
            │
            ├─ Internal Beta (Week 6-7)
            │  ├─> Deploy code to production ECS ✅ (Required)
            │  └─> Update ALB health check ⚠️ (Optional)
            │
            ├─ Public Beta (Week 7-8)
            │  └─> Monitor metrics only 📊 (No changes)
            │
            ├─ App Store Submission (Week 9-10)
            │  ├─> Deploy CloudFront caching ⚠️ (Optional, if >500 users expected)
            │  └─> Enable rate limiting ⚠️ (Only if abuse detected)
            │
            └─ Post-Launch (Month 2+)
               ├─> Fine-tune cache TTLs 🎯 (As needed)
               └─> Scale infrastructure 📈 (If metrics show need)
```

---

## Recommended Minimal Approach

If you want to keep infrastructure changes minimal:

**Phase 1-3 (Weeks 1-8):**
- ✅ Deploy code to production ECS
- ✅ That's it!

**Phase 4 (Week 9-10):**
- ⚠️ Only if you expect >500 users: Deploy CloudFront caching

**Phase 5 (Post-Launch):**
- 🎯 Monitor and optimize as needed

**Total Cost Impact:** $0-3/month depending on CloudFront decision

---

## Emergency Rollback Plan

If anything breaks after infrastructure changes:

### Rollback CloudFront
```bash
# Disable cache behaviors
terraform apply -target=aws_cloudfront_distribution.main

# Or manually in AWS Console:
# CloudFront > Distributions > Behaviors > Delete custom behaviors
```

### Rollback ALB Health Check
```bash
# Revert to previous health check path
terraform apply -target=aws_lb_target_group.php
```

### Disable Rate Limiting
```bash
# Remove rate limiter from controller methods
# Deploy code update
git revert <commit-hash>
git push
```

**Recovery Time:** <5 minutes for all scenarios

---

## Questions to Ask Yourself

Before each infrastructure change:

1. **Do I need this right now?** If no, wait.
2. **What problem does this solve?** If none, don't apply.
3. **What's the rollback plan?** If unclear, don't apply.
4. **What's the cost?** If >$10/month, validate with metrics first.
5. **Can I test in staging first?** If yes, do that.

**Default Answer:** When in doubt, **wait and monitor**.

---

## Next Steps

1. ✅ Continue iOS app development locally
2. ✅ Test against `https://localhost` or `https://mind-the-wait.local`
3. ⏸️ Wait until Week 6-7 for infrastructure deployment
4. 📋 Bookmark this document for reference during deployment phases

**You're all set for local development!** No infrastructure changes needed until you're ready for beta testing.

---

**Document Version:** 1.0
**Last Updated:** 2025-10-19
**Author:** Claude (Anthropic)
**Status:** Ready for Use
