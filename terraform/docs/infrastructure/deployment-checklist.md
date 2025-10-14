# Production Deployment Checklist

Your infrastructure is deployed, but needs final configuration.

## Current Status  

✅ AWS Infrastructure - Deployed  
✅ Docker Images - Built  
✅ Release v0.1.1 - Created  
❌ DNS - Using Porkbun nameservers (NOT Route 53)  
❌ SSL - Can't validate without Route 53  
❌ Deployment - Blocked by GitHub environment rules  

## The Problem

```bash
curl -I mind-the-wait.ca
Server: openresty          ← This is Porkbun's parking page!
X-Service: pixie-default   ← Not your application
```

DNS still points to Porkbun, not Route 53. Must fix 3 things in order:

---

## Step 1: Fix GitHub Environment (5 minutes) ⚠️ DO THIS FIRST

Your deployments are being skipped! Fix:

1. Go to: https://github.com/samuelwilk/mind-the-wait/settings/environments
2. Click **"production"** environment
3. Find **"Deployment branches and tags"** section  
4. Change from "Selected branches" to **"Selected branches and tags"**
5. Click **"Add deployment branch or tag rule"**
6. Enter: `refs/tags/v*`
7. Click **"Save protection rules"**

**Why**: Releases deploy from tags (v0.1.1), not branches. This setting blocks tag-based deployments.

---

## Step 2: Switch DNS to Route 53 (10-30 min wait)

### A. Get Route 53 Nameservers

**AWS Console Method**:
1. Go to: https://console.aws.amazon.com/route53/
2. Click **"Hosted zones"**
3. Click **"mind-the-wait.ca"**
4. Copy the 4 nameservers (look like: `ns-123.awsdns-45.com`)

### B. Update Porkbun

1. Go to: https://porkbun.com/account/domain
2. Find **"mind-the-wait.ca"** → Click **"Details"**
3. Find **"Authoritative Nameservers"** section
4. Replace Porkbun nameservers with the 4 Route 53 nameservers
5. Click **"Save"** or **"Update"**

### C. Wait for DNS Propagation (10-30 minutes)

Check every 5-10 minutes:
```bash
dig +short NS mind-the-wait.ca
```

**Before** (current):
```
curitiba.ns.porkbun.com.
salvador.ns.porkbun.com.
...
```

**After** (ready):
```
ns-123.awsdns-45.com.
ns-456.awsdns-78.org.
...
```

**Flush your local DNS cache** (macOS):
```bash
sudo dscacheutil -flushcache
sudo killall -HUP mDNSResponder
```

---

## Step 3: Enable HTTPS in Terraform (10-15 min)

**ONLY after DNS propagates to Route 53!**

### A. Edit terraform/modules/dns/main.tf

Find and **uncomment** certificate validation (around line 30):
```hcl
# Certificate validation
resource "aws_acm_certificate_validation" "this" {
  certificate_arn         = aws_acm_certificate.this.arn
  validation_record_fqdns = [for record in aws_route53_record.cert_validation : record.fqdn]
}
```

### B. Edit terraform/modules/alb/main.tf  

1. **Uncomment HTTPS listener** (around line 50):
```hcl
# HTTPS Listener
resource "aws_lb_listener" "https" {
  load_balancer_arn = aws_lb.this.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = var.certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.php.arn
  }
}
```

2. **Update HTTP listener to redirect** (around line 35):
```hcl
# HTTP Listener - Redirect to HTTPS
resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.this.arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type = "redirect"
    redirect {
      port        = "443"
      protocol    = "HTTPS"
      status_code = "HTTP_301"
    }
  }
}
```

### C. Apply Changes

```bash
cd terraform
terraform plan    # Review changes
terraform apply   # Apply HTTPS config
```

This takes 5-15 minutes to validate certificate and configure HTTPS.

---

## Step 4: Deploy Application

After fixing Step 1 (GitHub environment), deploy:

**Option A: Re-run Failed Deployment**
1. https://github.com/samuelwilk/mind-the-wait/actions
2. Find failed "Deploy to Production" for v0.1.1
3. Click **"Re-run failed jobs"**

**Option B: Make Empty Commit** (triggers new release)
```bash
git commit --allow-empty -m "chore: trigger deployment"
git push
# Merge the Release Please PR
```

**Monitor**: https://github.com/samuelwilk/mind-the-wait/actions

---

## Verification

After all steps complete:

```bash
# Check DNS (should show AWS IPs)
dig +short mind-the-wait.ca

# Check HTTP (should redirect to HTTPS)
curl -I http://mind-the-wait.ca
# Expected: Location: https://mind-the-wait.ca

# Check HTTPS (should work!)
curl -I https://mind-the-wait.ca
# Expected: HTTP/2 200
# Expected: x-powered-by: PHP/8.4

# Check application
open https://mind-the-wait.ca
```

---

## Troubleshooting

**Q: DNS not propagating?**  
A: Wait up to 48 hours, flush local DNS cache

**Q: Certificate validation hanging?**  
A: DNS must fully propagate to Route 53 first

**Q: 503 Service Unavailable?**  
A: ECS tasks may be starting, wait 2-3 minutes

**Q: Deployment still skipped?**  
A: Double-check GitHub environment protection settings

---

## Timeline

1. **Fix GitHub environment** → 5 minutes
2. **Update Porkbun DNS** → 2 minutes  
3. **Wait for DNS propagation** → 10-30 minutes
4. **Enable HTTPS in Terraform** → 15 minutes
5. **Deploy application** → 10 minutes

**Total**: 40-65 minutes (mostly waiting for DNS)

---

## What Happens Next

Once complete:
- ✅ https://mind-the-wait.ca → Your live application!
- ✅ SSL certificate valid
- ✅ Auto-redirect HTTP → HTTPS  
- ✅ Zero-downtime deployments on every release
- ✅ Production monitoring via CloudWatch

Your app is 95% deployed - just needs DNS switched! 🚀
