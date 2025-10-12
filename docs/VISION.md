# Mind the Wait: Product Vision

**Mission:** Expose transit system reliability through transparent, real-time service quality metrics that empower riders, advocates, and city officials to make informed decisions and drive accountability.

## Core Value Proposition

Mind the Wait is **not** a Google Maps competitor for arrival predictions. It's a **transit transparency and advocacy platform** that surfaces reliability metrics invisible in consumer navigation apps.

### What Makes Us Different

**Google Maps tells you:** "Bus 27 arrives in 6 minutes"

**Mind the Wait tells you:**
- Route 27 has **D-grade headway** (buses bunching frequently)
- This bus has been **late 73% of the time** this week
- Route 14 to the same destination has **A-grade reliability**
- Riders report this bus is **systematically later** than agency predictions
- Two buses are about to arrive together, then **20-minute gap**

## Target Audiences

### 1. Daily Riders
**Need:** Make better transit decisions
**Features:** Route comparisons, reliability warnings, bunching alerts

### 2. Transit Advocates
**Need:** Data-driven evidence for policy change
**Features:** Historical reliability reports, route scorecards, export capabilities

### 3. City Officials / Transit Agencies
**Need:** Identify underperforming routes and measure improvements
**Features:** Performance dashboards, trend analysis, comparative metrics

### 4. Researchers & Journalists
**Need:** Open access to transit performance data
**Features:** Public API, CSV exports, longitudinal data

### 5. App Developers
**Need:** Reliability data to enhance their transit apps
**Features:** REST API, webhooks, real-time feeds

## Strategic Focus

### Phase 1: Transit Monitoring & Transparency (Current)
✅ Real-time headway scoring (A-F grades)
✅ Arrival predictions with confidence levels
✅ Crowd-sourced feedback (ahead/on_time/late)
✅ Schedule delay calculation
✅ Position-based predictions

### Phase 2: Rider Utility Features (Next)
🎯 Public dashboard (route scorecards, system health)
🎯 "Should I wait or walk?" recommendations
🎯 Route reliability comparisons
🎯 Bunching alerts
🎯 Service quality warnings

### Phase 3: Advocacy & Analysis Tools
📊 Historical performance exports
📊 Route comparison reports
📊 Custom date range analysis
📊 Embed widgets for advocacy websites

### Phase 4: Predictive Intelligence
🔮 "Best time to catch this bus" predictions
🔮 "This route will likely bunch in 15 min" forecasts
🔮 Machine learning on historical reliability patterns

## Killer Features (That Google Doesn't Have)

### 1. "Should I Wait or Walk?" Decision Engine

**Problem:** Rider at stop with 2 route options - which is more reliable?

**Solution:**
```
🚶 Walk to Stop B (8 min)
  Route 14 → A-grade reliability (89% on-time)
  Next arrival: 12 min

⏱️  Wait at Stop A
  Route 27 → D-grade reliability (52% on-time)
  Next arrival: 3 min (⚠️ but historically 6 min late)

Recommendation: Walk to Stop B
Reason: Route 14 more reliable, you'll arrive 4 min sooner on average
```

**Inputs:**
- Current location
- Destination
- Nearby stops with route options
- Historical reliability scores
- Current predictions + historical delay patterns

**Output:**
- Clear recommendation with reasoning
- Expected arrival time accounting for reliability
- Confidence score

---

### 2. Route Reliability Comparison

**Problem:** Multiple routes go to same destination - which is most reliable?

**Solution:**
```
Route to University of Saskatchewan from Downtown

Route 27 (Silverspring)    Grade: D    52% on-time    Avg delay: 6.2 min
Route 14 (North Industrial) Grade: A    89% on-time    Avg delay: 1.1 min ⭐ BEST
Route 12 (River Heights)    Grade: C+   71% on-time    Avg delay: 3.4 min

🎯 Take Route 14 for most reliable service
📊 Based on 1,847 arrivals over last 30 days
```

**Features:**
- Side-by-side route comparison
- Historical on-time performance
- Average delay/early arrival
- Headway grade trends (past week/month)
- Peak vs off-peak reliability splits
- "Best time to ride" recommendations

**API Endpoint:**
```
GET /api/route-comparison?from_stop=3734&to_stop=5201
```

---

### 3. Bunching Alerts

**Problem:** Agency says "3 min and 12 min" but buses will arrive together, then huge gap.

**Solution:**
```
⚠️ BUNCHING ALERT: Route 27 Northbound

2 buses arriving together:
  • Bus 606: 3 min (on schedule)
  • Bus 707: 4 min (14 min ahead of schedule)

Then 24-minute gap until next bus

💡 Suggestion: Take first bus, next one won't be far behind schedule
```

**Detection Logic:**
- Multiple buses on same route within 2 minutes of each other
- Gap after bunched buses > 1.5× scheduled headway
- Alert riders via API/push notifications

**Use Cases:**
- Rider decides to wait for less crowded second bus
- Rider knows not to panic if they miss bunched buses
- Agency sees systemic bunching patterns by route/time

---

### 4. Service Quality Warnings

**Problem:** Bus says "on time" but this route is chronically unreliable at this hour.

**Solution:**
```
🚌 Route 43 to City Centre
Arrival: 6:51 PM (predicted)

⚠️ RELIABILITY WARNING
This route is late 78% of the time on Friday evenings
Average delay: 8.3 minutes
Based on 156 arrivals

Plan for arrival around 7:00 PM instead
```

**Triggers:**
- Route has <60% on-time performance for this time/day combination
- Average delay >5 minutes
- Pattern consistent over 30+ observations

**Breakdown by:**
- Time of day (morning rush, midday, evening, night)
- Day of week
- Weather conditions (if available)
- Special events (if calendar integration)

---

### 5. Crowd Wisdom Override

**Problem:** Agency TripUpdate says "on time" but GPS shows bus stuck in traffic.

**Solution:**
```
🚌 Bus 606 to University
Agency says: 6:51 PM arrival (on time)

👥 Crowd Feedback (last 20 riders):
   • Late: 17 votes (85%)
   • On time: 2 votes (10%)
   • Early: 1 vote (5%)

⚠️ Crowd consensus: Likely 5-10 min late
Consider alternative routes
```

**Features:**
- Real-time crowd feedback aggregation
- Flag predictions with >70% disagreement
- Show historical crowd accuracy
- "Was the crowd right?" post-arrival validation

**API Response:**
```json
{
  "prediction": {
    "agency_arrival": 1759897380,
    "crowd_consensus": "late",
    "crowd_confidence": 0.85,
    "suggested_arrival": 1759897680,
    "crowd_correction_sec": 300
  }
}
```

---

### 6. "Best Time to Catch This Bus"

**Problem:** Rider has flexible schedule - when is this route most reliable?

**Solution:**
```
📊 Route 27 Reliability by Hour (Weekdays)

🟢 7:00-9:00 AM   Grade: A-   92% on-time   Best window
🟡 9:00-12:00 PM  Grade: B    81% on-time
🟡 12:00-3:00 PM  Grade: B-   76% on-time
🟠 3:00-6:00 PM   Grade: D+   58% on-time   Avoid if possible
🟡 6:00-9:00 PM   Grade: C    68% on-time

💡 Recommendation: Travel before 9 AM or after 6 PM for best reliability
```

**Data Requirements:**
- Historical reliability by hour of day
- Minimum 100 observations per time bucket
- Trend analysis (improving/worsening)

---

### 7. Real-Time System Health Dashboard

**Problem:** No visibility into overall transit system performance.

**Solution:** Public web dashboard showing:

**System Overview:**
```
Saskatoon Transit - Live Performance
Last updated: 2 minutes ago

System Grade: C+ (71% on-time)
Active Vehicles: 32/54 routes operating
Service Alerts: 2 active

Top Performers (A-grade):
  ✅ Route 14: North Industrial (94% on-time)
  ✅ Route 12: River Heights (89% on-time)
  ✅ Route 50: Lakeview (87% on-time)

Needs Attention (D-F grade):
  ⚠️ Route 43: Evergreen (52% on-time, bunching)
  ⚠️ Route 27: Silverspring (58% on-time, delays)
  ⚠️ Route 6: Wilson Cres (61% on-time, irregular headway)
```

**Route Scorecards:**
```
Route 27: Silverspring / University

Current Status: D-grade (58% on-time today)
Trend: ↓ Declining (was C+ last week)

Last 24 Hours:
  • 18 scheduled trips
  • 7 on-time (38%)
  • 9 late (50%, avg 6.2 min late)
  • 2 early (11%)

Bunching Incidents: 3
Longest Delay: 14 minutes
Crowd Feedback: 78% voted "late"

Historical Performance:
  • This week: D- (54%)
  • This month: C- (67%)
  • 3-month avg: C+ (72%)
```

**Map View:**
- Color-coded routes by grade (green=A, red=F)
- Live bus positions
- Click route for detailed scorecard
- Bunching incidents highlighted

---

## Public Dashboard Specification

### Pages

#### 1. Home: System Overview
- System-wide grade (weighted average)
- Active vehicles count
- Service alerts
- Top 3 best/worst routes
- Real-time map

#### 2. Route List
- All routes with current grades
- Sort by: grade, route number, on-time %
- Filter by: service type, grade, area
- Search by route number or name

#### 3. Route Detail Page
```
URL: /route/14536 (Route 27)

Sections:
  • Current Performance (today's stats)
  • Real-time Map (buses on this route)
  • Reliability Trends (charts: 24hr, 7day, 30day)
  • Schedule Adherence (histogram: early/on-time/late)
  • Bunching Analysis (frequency, severity)
  • Crowd Feedback (votes over time)
  • Stop-by-Stop Performance (which stops have worst delays)
  • Compare Routes (link to comparison tool)
```

#### 4. Stop Detail Page
```
URL: /stop/3734 (Primrose / Lenore)

Sections:
  • Live Arrivals (next 5 buses with reliability warnings)
  • Routes Serving This Stop (with grades)
  • Historical Performance (reliability by route/time)
  • Nearby Stops (alternatives within 500m)
  • Should I Wait? (decision recommendation)
```

#### 5. Route Comparison Tool
```
URL: /compare?routes=14536,14526,14525

Side-by-side comparison:
  • Grades
  • On-time percentages
  • Average delays
  • Bunching frequency
  • Crowd ratings
  • Recommended times
```

#### 6. Historical Reports
```
URL: /reports

Pre-built reports:
  • Monthly System Performance
  • Route Performance Report Card
  • On-Time Performance by Time of Day
  • Bunching Incidents Log
  • Crowd Feedback vs Agency Accuracy

Export formats: CSV, JSON, PDF
```

#### 7. API Documentation
```
URL: /api-docs

Interactive API explorer:
  • Live endpoint testing
  • Code examples (curl, JS, Python)
  • Rate limits
  • Authentication (if needed)
  • Webhooks documentation
```

### Design Principles

**1. Data First**
- No unnecessary decoration
- Clear typography
- Generous whitespace
- Accessible color contrast

**2. Mobile-First**
- Responsive at all breakpoints
- Touch-friendly controls
- Fast load times (<2s)
- Progressive enhancement

**3. Real-Time Updates**
- Auto-refresh key metrics (30s)
- WebSocket for live map
- Loading states
- Last updated timestamps

**4. Accessibility**
- WCAG 2.1 AA compliant
- Keyboard navigation
- Screen reader support
- High contrast mode

**5. Shareable**
- Embed widgets for advocacy sites
- Direct links to route scorecards
- Social media preview cards
- Printable reports

### Technical Architecture

**Frontend:**
- Framework: React or Vue.js
- Charting: Chart.js or D3.js
- Maps: Leaflet or Mapbox
- State: Redux or Pinia
- Real-time: WebSocket or SSE

**Backend:**
- API: Existing Symfony endpoints
- Caching: Redis (5-30s TTL)
- Historical: PostgreSQL queries with indexing
- Real-time: Server-Sent Events for live updates

**Deployment:**
- Static hosting: Vercel/Netlify/Cloudflare Pages
- Or: Serve via Nginx from this repo
- CDN for assets
- SSL required

---

## Use Case Scenarios

### Scenario 1: Daily Commuter
**Sarah rides Route 27 to university every day at 8 AM.**

**Without Mind the Wait:**
- Checks Google Maps: "Bus in 5 min"
- Waits 12 minutes (bus was late, no warning)
- Frustrated, no idea if this is normal

**With Mind the Wait:**
- Dashboard shows: "Route 27: D-grade, 68% late on weekday mornings"
- Sees: "Route 14 alternative: A-grade, 91% on-time"
- Decision: Walk 3 minutes to Route 14 stop
- Result: Reliable arrival, saves 5 min average

---

### Scenario 2: Transit Advocate
**Local advocacy group wants more funding for Route 43.**

**Without Mind the Wait:**
- Anecdotal complaints
- No hard data for city council presentation
- Agency dismisses as "isolated incidents"

**With Mind the Wait:**
- Downloads 6-month performance report
- Route 43: F-grade, 48% on-time, 27 bunching incidents
- Comparison: Route 14 same area, A-grade
- Presents data visualization to council
- Result: Dedicated study, potential service improvements

---

### Scenario 3: City Transportation Planner
**Planner needs to identify routes needing intervention.**

**Without Mind the Wait:**
- Waits for quarterly agency reports
- Aggregate system-level stats only
- No granular visibility

**With Mind the Wait:**
- Dashboard shows real-time route grades
- Identifies Route 27: declining from C+ to D- over 3 weeks
- Drills into stop-by-stop analysis: bottleneck at Evergreen/8th
- Cross-references with traffic data
- Result: Traffic signal timing adjustment, grade improves to C+

---

### Scenario 4: Journalist
**Reporter writing story on transit reliability post-pandemic.**

**Without Mind the Wait:**
- FOI requests to transit agency (6-week delay)
- Limited data, pre-formatted reports
- No ability to analyze patterns

**With Mind the Wait:**
- Public API access, downloads historical data
- Analyzes system-wide on-time performance: 72% → 63% (2019 vs 2025)
- Identifies specific routes with biggest declines
- Visualizes bunching incidents by route
- Result: Data-driven story with interactive charts

---

## Roadmap

### Q1 2025: Foundation ✅
- [x] GTFS static/realtime ingestion
- [x] Headway scoring system
- [x] Arrival predictions (3-tier)
- [x] Crowd feedback system
- [x] Schedule delay calculation

### Q2 2025: Public Dashboard 🎯
- [ ] Route scorecards page
- [ ] System overview dashboard
- [ ] Real-time map view
- [ ] Stop detail pages
- [ ] Historical charts (24hr, 7day, 30day)
- [ ] Mobile-responsive design

### Q3 2025: Rider Utility
- [ ] "Should I wait or walk?" engine
- [ ] Route comparison tool
- [ ] Bunching alerts
- [ ] Service quality warnings
- [ ] Best time to ride recommendations

### Q4 2025: Analysis & Advocacy
- [ ] Historical report exports (CSV, PDF)
- [ ] Custom date range queries
- [ ] Embeddable widgets
- [ ] Enhanced API (webhooks, GraphQL)
- [ ] Multi-city support framework

### 2026: Predictive Intelligence
- [ ] Machine learning on reliability patterns
- [ ] Predictive bunching alerts
- [ ] Weather impact analysis
- [ ] Event-based service predictions
- [ ] Personalized reliability scores

---

## Success Metrics

### Rider Adoption
- Unique visitors to dashboard
- API requests per day
- Mobile vs desktop usage
- Avg time on site
- Return visitor rate

### Data Impact
- Media citations
- City council presentations using our data
- Transit agency references
- Academic research papers
- Advocacy campaigns launched

### System Improvements
- Routes with grade improvements after attention
- Correlation between public visibility and service changes
- Agency adoption of reliability metrics

### Community Engagement
- Crowd feedback submissions
- Social media shares of route scorecards
- Developer apps using our API

---

## Competitive Landscape

### Existing Transit Apps
- **Google Maps**: Predictions, routing (no reliability metrics)
- **Transit App**: UI/UX leader (limited reliability scoring)
- **Citymapper**: Multi-modal routing (some delay history)
- **Moovit**: Crowdsourcing (limited analytics)

### Our Niche
**Open-source transit transparency and advocacy platform**

- Only tool focused on *system reliability* vs *individual predictions*
- Open data for researchers and advocates
- Public accountability dashboard
- City-agnostic (deployable anywhere with GTFS)

### Why This Matters
Transit advocates and researchers have **no good tools** for:
- Longitudinal reliability analysis
- Route-level performance tracking
- System-wide health monitoring
- Evidence-based advocacy

This is the gap we fill.

---

## Contributing

We welcome contributions from:
- **Transit agencies** - Integrate your data, improve rider experience
- **City governments** - Deploy for your city
- **Researchers** - Use our data, improve our algorithms
- **Developers** - Build features, fix bugs, enhance API
- **Riders** - Submit feedback, report issues, suggest features

See [CONTRIBUTING.md](../CONTRIBUTING.md) for guidelines.

---

## License & Governance

**License:** MIT (open source, free to use/modify/deploy)

**Governance:** Community-driven, transparent roadmap

**Goal:** Become the de facto open-source transit reliability platform

---

## Questions & Discussion

- **Slack/Discord:** [Coming soon]
- **GitHub Discussions:** Use Issues tab
- **Email:** [Your contact]
- **Mastodon/Twitter:** [Your handle]

---

*Last updated: 2025-10-11*
*Version: 1.0*
