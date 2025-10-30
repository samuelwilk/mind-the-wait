# Deployment Checklist - Reddit Launch

Last Updated: October 29, 2025

## ✅ Completed

### GA4 Analytics Implementation
- [x] GA4 tracking script with consent mode
- [x] Cookie consent banner (GDPR/CCPA compliant)
- [x] Privacy policy page (`/privacy`)
- [x] Custom event tracking (route views, weather impact)
- [x] Environment variable setup (GA4_MEASUREMENT_ID)
- [x] Documentation (docs/GA4_SETUP.md)
- [x] Code pushed to main branch

### Stop-Level Reliability Improvements (Part 1/2)
- [x] Updated StopReliabilityDto with stopSequence and direction fields
- [x] Updated repository query to filter by direction and sort by route order
- [x] Code pushed to main branch

---

## 🚧 In Progress

### Stop-Level Reliability Improvements (Part 2/2)

**Remaining Tasks:**
1. Update RoutePerformanceService:
   - Build two separate charts (one per direction)
   - Add `getHeadsignForDirection()` helper method
   - Add `shortenStopName()` helper method
   - Generate direction labels (e.g., "City Centre ↓", "North Industrial ↑")

2. Update RouteDetailDto:
   - Replace single `stopReliabilityChart` with two properties:
     - `stopReliabilityChartDirection0` (outbound)
     - `stopReliabilityChartDirection1` (inbound)

3. Update route_detail.html.twig:
   - Display two charts side-by-side (grid layout)
   - Add direction arrows (↓ and ↑) in titles
   - Update descriptions to explain route order

4. Update RoutePerformanceChartPreset:
   - Add direction parameter to `stopReliability()`
   - Include direction in chart title

5. Test:
   - Verify charts show correct data for each direction
   - Verify stops are sorted by sequence
   - Verify direction labels are accurate
   - Test mobile responsiveness

**Estimated Time:** 30-40 minutes

---

## 📋 Deployment Requirements

### 1. Add GitHub Secret

**Action Required:**
1. Go to: https://github.com/samuelwilk/mind-the-wait/settings/secrets/actions
2. Click "New repository secret"
3. Name: `GA4_MEASUREMENT_ID`
4. Value: `G-LRK992JPE0`
5. Click "Add secret"

### 2. Update Terraform/ECS Configuration ✅ COMPLETED

**Status:** GA4_MEASUREMENT_ID has been added to Terraform configuration.

**Changes made:**
- ✅ Added `ga4_measurement_id` variable to `terraform/environments/prod/variables.tf`
- ✅ Added GA4_MEASUREMENT_ID to PHP service environment variables
- ✅ Added GA4_MEASUREMENT_ID to scheduler-high-freq service environment variables
- ✅ Added GA4_MEASUREMENT_ID to scheduler-low-freq service environment variables
- ✅ Added value to `terraform/environments/prod/terraform.tfvars`

**Files modified:**
- `terraform/environments/prod/main.tf` (lines 157, 255, 309)
- `terraform/environments/prod/variables.tf` (lines 142-146)
- `terraform/environments/prod/terraform.tfvars` (line 62)

### 3. Deploy to Production

```bash
# Option 1: If using GitHub Actions
# Push triggers automatic deployment via CI/CD

# Option 2: Manual deploy
terraform plan
terraform apply

# Verify deployment
aws ecs list-tasks --cluster mindthewait-prod
aws ecs describe-tasks --cluster mindthewait-prod --tasks <task-arn>
```

### 4. Verify GA4 is Working

**Immediate Verification:**
1. Visit production site: https://mindthewait.ca
2. Open DevTools → Console
3. Type: `dataLayer`
4. Should see array with GA4 initialization

**Check Environment Variable:**
```bash
# SSH into ECS container or use ECS Exec
aws ecs execute-command \
  --cluster mindthewait-prod \
  --task <task-id> \
  --container app \
  --interactive \
  --command "env | grep GA4"

# Should output: GA4_MEASUREMENT_ID=G-LRK992JPE0
```

**GA4 Dashboard (within 30 seconds):**
1. Go to: https://analytics.google.com/
2. Navigate to: Reports → Realtime
3. Should see active users

**Test Cookie Consent Flow:**
1. Visit site in incognito mode
2. Cookie banner should appear at bottom
3. Click "Accept" → banner disappears, localStorage has `analytics-consent=granted`
4. Click "Decline" → banner disappears, localStorage has `analytics-consent=denied`
5. Refresh page → banner should NOT reappear

---

## 🎯 Reddit Launch Checklist

### Pre-Launch (Do Before Posting)
- [ ] GA4 secret added to GitHub
- [ ] Terraform/ECS updated with GA4_MEASUREMENT_ID
- [ ] Production deployment complete
- [ ] GA4 verified working in production
- [ ] Cookie banner tested in production
- [ ] Privacy policy accessible at /privacy
- [ ] Finish stop-level reliability improvements (optional but recommended)

### Launch
- [ ] Post to r/saskatoon
- [ ] Monitor GA4 Realtime dashboard
- [ ] Watch for errors in CloudWatch logs
- [ ] Monitor server performance (CPU, memory)

### Post-Launch (First 24 Hours)
- [ ] Review GA4 traffic sources (verify reddit.com/r/saskatoon appears)
- [ ] Check most viewed pages/routes
- [ ] Review device breakdown (mobile vs desktop)
- [ ] Check for any error spikes
- [ ] Review feedback submissions (if any)

---

## 📊 Expected GA4 Metrics

**Immediately After Posting:**
- Active users: 10-50 (first hour)
- Traffic source: reddit.com
- Device: 60-70% mobile (Reddit users)
- Top pages: /dashboard, /routes, specific route pages

**First Week:**
- Unique visitors: 200-1000 (local subreddit)
- Avg session duration: 2-5 minutes
- Bounce rate: 40-60%
- Top custom events: view_route, view_weather_impact

---

## 🔧 Troubleshooting

### GA4 Not Loading
```bash
# Check 1: Env var is set
docker exec <container> env | grep GA4_MEASUREMENT_ID

# Check 2: APP_ENV is prod
docker exec <container> env | grep APP_ENV

# Check 3: View rendered HTML source
curl https://mindthewait.ca | grep "googletagmanager"
# Should see: <script async src="https://www.googletagmanager.com/gtag/js?id=G-LRK992JPE0">
```

### Cookie Banner Not Showing
- Clear browser localStorage
- Visit in incognito mode
- Check browser console for JS errors

### Events Not Tracking
- Open DevTools → Network → Filter "collect"
- Should see POST to www.google-analytics.com/g/collect
- Verify user has accepted cookies (localStorage check)

---

## 📝 Notes

- GA4 is **free** (up to 10M events/month)
- Cookie consent required by law (GDPR/CCPA/PIPEDA)
- Data retention: 14 months default (can configure up to 26 months)
- Realtime reports update within 1-2 minutes
- Standard reports update once daily (morning)

---

**Status:** Ready for deployment pending GA4 secret configuration
**Owner:** @samuelwilk
**Next Action:** Add GA4_MEASUREMENT_ID to GitHub secrets and Terraform
