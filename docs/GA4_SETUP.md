# Google Analytics 4 Setup Guide

## Overview

This document describes how to configure Google Analytics 4 (GA4) tracking for Mind the Wait in production.

## Security Model

- **Development:** GA4 is disabled (no tracking)
- **Production:** GA4 loads only when `GA4_MEASUREMENT_ID` environment variable is set
- **Secret Management:** Measurement ID is stored as GitHub secret, never committed to git

## GitHub Secret Setup

### Step 1: Add Secret to GitHub Repository

1. Go to your GitHub repository: `https://github.com/YOUR-USERNAME/YOUR-REPO`
2. Click **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Fill in:
   - **Name:** `GA4_MEASUREMENT_ID`
   - **Secret:** `G-XXXXXXXXXX` (your actual measurement ID)
5. Click **Add secret**

### Step 2: Update GitHub Actions Workflow (if needed)

If you're using GitHub Actions for deployment, ensure the secret is passed to your deployment:

```yaml
# .github/workflows/deploy.yml
env:
  GA4_MEASUREMENT_ID: ${{ secrets.GA4_MEASUREMENT_ID }}
```

### Step 3: Update ECS Task Definition (for AWS ECS deployment)

The GA4 measurement ID should be injected as an environment variable in your ECS task:

**Option A: Via Terraform**
```hcl
# terraform/ecs.tf
resource "aws_ecs_task_definition" "app" {
  # ... existing config ...

  container_definitions = jsonencode([{
    name  = "app"
    # ... existing config ...
    environment = [
      # ... existing environment variables ...
      {
        name  = "GA4_MEASUREMENT_ID"
        value = var.ga4_measurement_id
      }
    ]
  }])
}

# terraform/variables.tf
variable "ga4_measurement_id" {
  description = "Google Analytics 4 Measurement ID"
  type        = string
  sensitive   = true
}
```

**Option B: Via AWS Systems Manager Parameter Store**
```bash
# Store secret in Parameter Store
aws ssm put-parameter \
  --name "/mindthewait/prod/ga4_measurement_id" \
  --value "G-XXXXXXXXXX" \
  --type "SecureString" \
  --description "Google Analytics 4 Measurement ID"

# Then reference in ECS task definition
"secrets": [
  {
    "name": "GA4_MEASUREMENT_ID",
    "valueFrom": "/mindthewait/prod/ga4_measurement_id"
  }
]
```

## How It Works

### 1. Environment Detection

The base template checks if GA4 should be loaded:

```twig
{% if app.environment == 'prod' and app.request.server.get('GA4_MEASUREMENT_ID') %}
    <!-- GA4 loads here -->
{% endif %}
```

### 2. Privacy-Friendly Configuration

GA4 is configured with maximum privacy:

- **IP Anonymization:** `anonymize_ip: true`
- **No Google Signals:** `allow_google_signals: false`
- **No Ad Personalization:** `allow_ad_personalization_signals: false`
- **Consent Mode:** Users must explicitly accept cookies

### 3. Cookie Consent Flow

1. User visits site → Banner appears (if no previous choice)
2. User clicks "Accept" → Consent stored in localStorage
3. GA4 consent updated: `analytics_storage: 'granted'`
4. Google Analytics starts tracking

If user clicks "Decline":
- Consent remains denied
- No analytics cookies are set
- All features still work normally

## Custom Events Tracked

We track the following custom events (only with user consent):

| Event Name | Triggered When | Parameters |
|------------|---------------|------------|
| `view_route` | User views a route detail page | `route_id`, `route_number`, `route_name`, `route_grade`, `avg_performance` |
| `view_weather_impact` | User views weather impact analysis | `page_title` |

## Verifying GA4 is Working

### In Development (Should NOT Track)

1. Run app locally with `APP_ENV=dev`
2. Open browser DevTools → Network tab
3. Search for `google-analytics` or `gtag`
4. **Should see: NO requests** (GA4 disabled in dev)

### In Production (Should Track)

1. Visit production site
2. Open browser DevTools → Console
3. Type: `dataLayer`
4. **Should see:** Array with GA4 events
5. Check GA4 Realtime report (https://analytics.google.com/)
   - Navigate to **Reports** → **Realtime**
   - Should see active users within 30 seconds

## Testing Locally (Optional)

To test GA4 integration locally without polluting production analytics:

1. Create a separate GA4 property for testing
2. Add measurement ID to `.env.local`:
   ```bash
   GA4_MEASUREMENT_ID=G-TESTXXXXXXX
   APP_ENV=prod  # Temporarily set to prod
   ```
3. Visit `http://localhost:8080`
4. Accept cookies
5. Check test GA4 property for events

**Remember to remove test config from `.env.local` before committing!**

## Privacy Compliance

### GDPR (European Union)

✅ **Compliant** - Cookie banner requires explicit consent before analytics

### CCPA (California)

✅ **Compliant** - Users can decline tracking; privacy policy discloses data collection

### PIPEDA (Canada)

✅ **Compliant** - Consent obtained; data minimization applied

## Troubleshooting

### GA4 Not Loading in Production

**Check 1:** Environment variable is set
```bash
# In ECS task, run:
env | grep GA4_MEASUREMENT_ID
# Should output: GA4_MEASUREMENT_ID=G-XXXXXXXXXX
```

**Check 2:** APP_ENV is 'prod'
```bash
env | grep APP_ENV
# Should output: APP_ENV=prod
```

**Check 3:** User has accepted cookies
```javascript
// In browser console:
localStorage.getItem('analytics-consent')
// Should output: "granted"
```

### Events Not Showing in GA4

**Check 1:** User consented to analytics
- Open DevTools → Application → Local Storage
- Check `analytics-consent` key is set to `"granted"`

**Check 2:** Events are being sent
- Open DevTools → Network tab → Filter by "collect"
- Should see POST requests to `www.google-analytics.com/g/collect`

**Check 3:** GA4 Realtime report
- Events may take 1-2 minutes to appear
- Use **Realtime** report, not standard reports (those update daily)

## Analytics Dashboard Access

**URL:** https://analytics.google.com/

**Account:** Your Google account used to create the GA4 property

**Property Name:** mind-the-wait

**Measurement ID:** G-XXXXXXXXXX (set as GitHub secret)

## Key Reports to Monitor

After Reddit launch, monitor these reports:

1. **Realtime** - See live traffic during announcement
2. **Acquisition** → **Traffic acquisition** - See reddit.com as referrer
3. **Engagement** → **Pages and screens** - Most viewed routes
4. **Tech** → **Tech details** - Mobile vs desktop breakdown
5. **User attributes** → **Demographics** - Geographic distribution

## Cost

- **GA4:** Free (up to 10M events/month)
- **Infrastructure:** No additional AWS costs
- **Total:** $0/month

## Security Best Practices

✅ Measurement ID stored as GitHub secret
✅ Not committed to git
✅ Only loaded in production
✅ IP anonymization enabled
✅ No cross-device tracking
✅ No ad personalization

---

**Last Updated:** October 29, 2025
**Owner:** @samuelwilk
**Status:** Production Ready
