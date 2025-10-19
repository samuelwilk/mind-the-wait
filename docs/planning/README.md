# Planning Documents

This directory contains **planning documents for features that have NOT been fully implemented yet**. These are detailed design specs and implementation plans for future work.

## 📋 Status Legend

- **📋 PLANNING** - Feature has not been started
- **📋 PARTIALLY IMPLEMENTED** - Some work completed, but feature incomplete
- **✅ IMPLEMENTED** - Feature fully implemented (moved to `/docs/implemented/`)

## Documents in this Directory

### High Priority

#### [LIVE_ROUTE_VISUALIZATION.md](LIVE_ROUTE_VISUALIZATION.md)
**Status:** 📋 PLANNING (Not started)

Beautiful 3D visualization of favorite transit route with live vehicle positions and countdown timers. Mid-century modern aesthetic meets futuristic design.

**Why Important:** Marquee feature that makes transit data delightful and instantly understandable. Widget provides at-a-glance countdown without opening app.

**Key Features:**
- 🚌 Live vehicle positions in isometric 3D view
- ⏱️ Countdown timers to next arrival
- 📱 Home screen widget (small/medium/large)
- 🎨 Mid-century modern design language
- 🔄 Tilt/rotate interaction with touch gestures

**Dependencies:** Requires iOS app base implementation (IOS_IMPLEMENTATION_PLAN.md)

**Estimated Effort:** 3-4 weeks

---

#### [IOS_IMPLEMENTATION_PLAN.md](IOS_IMPLEMENTATION_PLAN.md)
**Status:** 📋 PARTIALLY IMPLEMENTED (Backend complete, iOS app not started)

Mobile app implementation plan for native iOS application. Backend APIs and infrastructure ready, iOS development pending.

**What's Done:**
- ✅ Mobile API endpoints (`/api/v1/routes`, `/api/v1/stops`, etc.)
- ✅ CORS configuration for mobile origins
- ✅ Rate limiting setup
- ✅ Health check endpoint for ALB
- ✅ CloudFront caching documentation

**What's Pending:**
- ⏸️ iOS app development (SwiftUI + MapKit)
- ⏸️ TestFlight distribution
- ⏸️ App Store submission

**Estimated Effort:** 6 weeks for iOS app

---

#### [DIRECTIONAL_PERFORMANCE.md](DIRECTIONAL_PERFORMANCE.md)
**Status:** 📋 PLANNING (Not started)

Track and display route performance separately by direction (outbound vs inbound). Reveals hidden insights about directional asymmetry in transit operations.

**Why Important:** Current metrics aggregate both directions, hiding patterns like "Route 16 outbound is 85% on-time, inbound is 15% on-time" (combined shows 50%).

**Estimated Effort:** 2 weeks (1 week backend, 1 week frontend)

---

#### [CODE_QUALITY_REFACTORING.md](CODE_QUALITY_REFACTORING.md)
**Status:** 📋 PARTIALLY IMPLEMENTED (Phase 4 complete, ongoing)

Refactoring plan to improve code quality: repository pattern enforcement, DTOs instead of arrays, Enums for type safety, value objects.

**What's Done:**
- ✅ Phase 4: Temperature Threshold refactoring

**What's Pending:**
- ⏸️ Move all queries to repositories (remove from services)
- ⏸️ Replace array returns with DTOs
- ⏸️ Enum-ify weather conditions
- ⏸️ ChartBuilder pattern enforcement

**Estimated Effort:** 3-4 weeks (incremental)

---

### Medium Priority

#### [ENHANCED_ANALYTICS_FEATURES.md](ENHANCED_ANALYTICS_FEATURES.md)
**Status:** 📋 PLANNING (Not started)

7 advanced analytics features to transform basic headway monitoring into comprehensive transit performance diagnostics:

1. **Stop-Level Reliability Map** - Heatmap showing which stops have highest delays
2. **Delay Propagation Visualization** - How delays cascade through route
3. **Schedule Realism Index** - Identify unrealistic scheduled times
4. **Temporal Delay Curve** - Peak hour delay patterns
5. **Reliability Context Panel** - Contextual comparison to system average
6. **Live Route Health Gauge** - Real-time performance indicator
7. **Data Integrity/Coverage Diagnostics** - Data quality dashboard

**Estimated Effort:** 3 weeks total (~2-3 days per feature)

---

#### [ANALYTICS_PLAN.md](ANALYTICS_PLAN.md)
**Status:** 📋 PLANNING (Not started)

Visitor tracking and analytics implementation. Three options evaluated:

1. **ALB Logs + Athena** - Query AWS logs
2. **Custom Tracking** - PostgreSQL-based privacy-friendly tracking
3. **Google Analytics 4** - Third-party analytics

**Recommended:** Start with Option 2 (Custom Tracking) - zero cost, GDPR-compliant, full control.

**Estimated Effort:** ~4 hours (backend + frontend + testing)

---

## Using These Documents

### For Developers

1. **Read the status header** at the top of each document
2. **Check "Implementation Status"** to understand what's done vs pending
3. **Review dependencies** - some features depend on others
4. **Follow coding standards** - All new features must use DTOs, Enums, Repository pattern

### For Planning

1. **Priority is indicated** in each status header
2. **Effort estimates** are included for sprint planning
3. **Update status headers** when work begins or completes
4. **Move to `/docs/implemented/`** when feature is complete

### Adding New Planning Documents

When creating a new planning document:

1. Add it to `docs/planning/`
2. Include status header at top:
   ```markdown
   > **📋 STATUS: PLANNING** | This document describes a feature that has NOT been implemented yet.
   >
   > **Implementation Status:** Not started
   > **Priority:** [High/Medium/Low]
   > **Estimated Effort:** [time estimate]
   > **Last Updated:** YYYY-MM-DD
   ```
3. Update this README with a summary entry
4. Link from main `/docs/README.md` if relevant

---

## Moving Documents to Implemented

When a feature is fully implemented:

1. Add **✅ STATUS: IMPLEMENTED** header to document
2. Add implementation date and modified files list
3. Move from `docs/planning/` to `docs/implemented/`
4. Update this README to remove the entry
5. Add reference to main `/docs/README.md` if helpful

**Example implemented status header:**
```markdown
> **✅ STATUS: IMPLEMENTED** | This feature has been fully implemented and deployed.
>
> **Implementation Date:** October 2025
> **Files Modified:**
> - src/Repository/FooRepository.php
> - src/Service/BarService.php
```

---

## Questions?

- Check the main [Documentation Index](/docs/README.md)
- See [Implemented Features](/docs/implemented/README.md)
- Review [Getting Started Guide](/docs/GETTING_STARTED.md)

**Last Updated:** 2025-10-19
