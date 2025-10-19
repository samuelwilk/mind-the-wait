# Implemented Features

This directory contains **documentation for features that have been fully implemented and deployed**. These documents serve as historical records of implementation decisions and patterns.

## ✅ Features in this Directory

### [BUNCHING_WEATHER_NORMALIZATION.md](BUNCHING_WEATHER_NORMALIZATION.md)
**Status:** ✅ IMPLEMENTED (October 2025)

Weather-normalized bunching incident analysis. Changed from raw incident counts to incidents-per-hour rate metric to account for exposure time.

**Why Important:** Raw counts were misleading - "Clear weather has 3,085 incidents" vs "Cloudy has 1,650" didn't account for the fact that clear weather is more common. Normalized rates revealed cloudy weather actually has 2× higher bunching rate.

**Implementation:**
- Added `BunchingIncidentRepository::countByWeatherConditionNormalized()`
- Updated `WeatherAnalysisService` to use normalized rates
- Enhanced chart tooltips to show exposure hours
- Added unit tests in `BunchingIncidentRepositoryTest`

**Impact:** More accurate weather impact analysis for transit operators.

---

## Using These Documents

### Purpose

Implemented feature documents serve three main purposes:

1. **Historical Record** - Captures implementation decisions and rationale
2. **Reference Material** - Shows patterns and best practices for similar features
3. **Onboarding** - Helps new developers understand why code is structured a certain way

### When to Add Documents Here

Move a document from `/docs/planning/` to `/docs/implemented/` when:

1. ✅ Feature is fully implemented and tested
2. ✅ Code is deployed to production
3. ✅ All acceptance criteria from planning doc are met

### Document Structure

Each implemented document should have:

1. **Status header** with:
   - ✅ IMPLEMENTED badge
   - Implementation date
   - Files modified
   - Brief summary

2. **Original problem statement** - Why was this needed?

3. **Solution approach** - What was implemented?

4. **Implementation details** - Code examples, SQL queries, etc.

5. **Testing strategy** - How was it verified?

6. **Impact metrics** (if applicable) - Performance improvements, user adoption, etc.

---

## See Also

- [Planning Documents](/docs/planning/README.md) - Features in development or planned
- [API Documentation](/docs/api/endpoints.md) - Active API endpoints
- [Architecture Documentation](/docs/architecture/overview.md) - System design

**Last Updated:** 2025-10-19
