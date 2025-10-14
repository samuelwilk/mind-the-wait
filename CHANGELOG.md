# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 (2025-10-14)


### Features

* 6-color vehicle status system with dad jokes and comprehensive docs ([bc29539](https://github.com/samuelwilk/mind-the-wait/commit/bc295399d4f0f5f9f4098c794054503fe8380a41))
* add AWS infrastructure and CI/CD pipeline ([21fe5dd](https://github.com/samuelwilk/mind-the-wait/commit/21fe5dd077544d7c68d99c0a4a293fc3d598be0d))
* build and push Docker images on every push to main ([2d01c61](https://github.com/samuelwilk/mind-the-wait/commit/2d01c61bf5d6bfc3d553fd667848a09f6377a3a3))
* comprehensive weather tests and Symfony Scheduler integration ([60183c0](https://github.com/samuelwilk/mind-the-wait/commit/60183c059ae0e9d7a5de44bb5f9539b6a77e40c4))
* GTFS static: bulk upsert stop_times with unique, metadata-safe repos, loader hardening ([a6245b5](https://github.com/samuelwilk/mind-the-wait/commit/a6245b5487d474c5abd7a6bc70e62e66496a2275))
* historical data collection with arrival logging and daily performance aggregation ([cfaa5f1](https://github.com/samuelwilk/mind-the-wait/commit/cfaa5f10c24e309021615ab0d1a4904953f5506a))
* implement position-based headway calculation with realtime fallback ([fcb42ad](https://github.com/samuelwilk/mind-the-wait/commit/fcb42add58c17627ec77e68e9c463a4e2b4a5042))
* initial working stack (Symfony + Redis + SSE + pyparser) ([caa7be7](https://github.com/samuelwilk/mind-the-wait/commit/caa7be760f3c240ced706aca91ae36a78def9232))
* interactive dashboard with Tailwind CSS and live components ([0381478](https://github.com/samuelwilk/mind-the-wait/commit/03814783cc68a2842c69171e840994a85cdd0d88))
* Phase 3 complete - route pages with ECharts visualizations and live data polling ([e29b156](https://github.com/samuelwilk/mind-the-wait/commit/e29b156cf74e2494321a0485b1d35ef1a4ec7053))
* Phase 4 complete - AI-powered insights and weather impact analysis ([c3f1f56](https://github.com/samuelwilk/mind-the-wait/commit/c3f1f563b225ac18dac4940e5fee51836d4ec20a))
* realtime arrival predictions with 3-tier fallback and countdown timers ([1a8806b](https://github.com/samuelwilk/mind-the-wait/commit/1a8806b7d32cb672060b78f64e90a53fc1cb8f2f))
* route colors with WCAG contrast, reusable components, and test fixes ([53f9db1](https://github.com/samuelwilk/mind-the-wait/commit/53f9db12721d1509743fb0f04631532369d8361f))
* weather integration with automatic performance correlation ([9bb0237](https://github.com/samuelwilk/mind-the-wait/commit/9bb02371274c8ed013eca2d1056c271a85b12215))


### Bug Fixes

* add REDIS_URL default for CI/CD environments ([efe2b3a](https://github.com/samuelwilk/mind-the-wait/commit/efe2b3afeca811c70528a4d58c34ffd3136a7833))
* ALB Listener Warning ([4dbb1a3](https://github.com/samuelwilk/mind-the-wait/commit/4dbb1a3b7995e42ee742d630d2c10ab4916b28bd))
* complete dad joke detection in HeuristicTrafficReasonProvider tests ([8e3d63b](https://github.com/samuelwilk/mind-the-wait/commit/8e3d63ba75142ec0fd1663780674cb4df4f333df))
* correct test database naming in GitHub Actions ([bc85188](https://github.com/samuelwilk/mind-the-wait/commit/bc851889528790dc8d19a82b99e1c288793d1a64))
* explicitly specify production Docker target in workflows ([18c7b4b](https://github.com/samuelwilk/mind-the-wait/commit/18c7b4b4040ef33cc5b829df19f1bad8a3100c9c))
* only update :latest tag on releases, not on every push ([d3823fd](https://github.com/samuelwilk/mind-the-wait/commit/d3823fd2dd53d77f08c86c12e4616636f8c53b46))
* production-ready Docker configuration and CI/CD fixes ([4f1612e](https://github.com/samuelwilk/mind-the-wait/commit/4f1612e11a8c023531752b37e6b042c45b628f51))
* remove deprecated command test methods ([ec59aea](https://github.com/samuelwilk/mind-the-wait/commit/ec59aea8b79272f53552460e846bde9c1a78812f))
* resolve duplicate workflows and missing PHP extensions ([8311ba0](https://github.com/samuelwilk/mind-the-wait/commit/8311ba0c85912a06f91cdf8f6cd5b90f9f299e18))
* resolve Terraform errors for certificate and PostgreSQL version ([89bd0b5](https://github.com/samuelwilk/mind-the-wait/commit/89bd0b56d7a67d5cbd08874df2aa78946b293ca1))
* temporarily skip ACM certificate validation ([8038d57](https://github.com/samuelwilk/mind-the-wait/commit/8038d573c40a40333857e5ad6e4c171097d3fa7e))

## [Unreleased]

### Added
- **Overview Dashboard** - Complete Phase 2 implementation with real-time metrics
  - System-wide performance grade (A-F) with on-time percentage
  - Live weather banner with transit impact assessment
  - Top performing routes and routes needing attention
  - 30-day historical performance tracking (top/worst performers)
  - Responsive design with mobile support
- **Weather Integration** - Hourly automated weather collection with transit impact
  - Open-Meteo API integration for Saskatoon conditions
  - Transit impact assessment (None/Minor/Moderate/Severe)
  - Historical weather correlation with performance data
  - Weather banner auto-updates with latest observation
- **Schedule Adherence Calculation** - Single-vehicle route grading
  - Position-based delay calculation using GPS and schedule
  - Grades single-vehicle routes A-F based on punctuality
  - Confidence levels: HIGH (multi-vehicle), MEDIUM (schedule adherence), LOW (default)
- **Enhanced Setup** - One-command project setup via `make setup`
  - Automated Docker build and startup
  - Database creation and migrations
  - GTFS static data loading
  - Initial weather collection
  - Test database setup

### Changed
- **Scheduler Architecture** - Migrated from bash loops to Symfony Scheduler
  - Score calculation now uses `ScoreTickSchedule` (every 30 seconds)
  - Weather collection uses `WeatherCollectionSchedule` (hourly)
  - Performance aggregation uses `PerformanceAggregationSchedule` (daily at 1 AM)
  - Unified message consumer approach via `messenger:consume`
  - Better logging, error handling, and retry mechanisms
- **Timezone Configuration** - Set to America/Regina (CST/CDT)
  - PHP timezone: `America/Regina` (was UTC)
  - All timestamps now display in Saskatoon local time
  - Scheduled tasks run at expected local times (e.g., 1:00 AM local for aggregation)
- **Weather Repository** - Fixed `findLatest()` to exclude future forecasts
  - Only returns observations with `observed_at <= NOW()`
  - Prevents displaying forecast data as current conditions
- **Weather Banner Timestamp** - Changed from relative to absolute time display
  - Today: Shows time only (e.g., "11:00 PM")
  - Yesterday: Shows "Yesterday at 3:00 PM"
  - Older: Shows full date + time (e.g., "Oct 12, 11:00 PM")
  - Better clarity for hourly weather updates

### Fixed
- **Weather Collection Not Running** - Scheduler container now consumes scheduled messages
  - Was only running `app:score:tick` in a loop
  - Now consumes all scheduler transports (score, weather, performance)
  - Weather automatically collects every hour
- **Live Weather Banner** - Removed conditional rendering that prevented display
  - Banner now always renders, handles null data internally
  - Shows "Weather data unavailable" when no data exists
- **Intermittent Empty Dashboard Lists** - Improved filtering logic
  - Top performers now includes routes with active vehicles and valid grades
  - Needs attention targets grades D/F and single-vehicle C routes
  - Added secondary sorting by vehicle count for confidence ranking
- **Score Calculation for Single Vehicles** - Implemented schedule adherence grading
  - Previously assigned default "C" grade to single-vehicle routes
  - Now calculates delay based on vehicle position vs schedule
  - Grades: A (on-time), B (1-3 min late), C (3-5 min), D (5-10 min), F (>10 min)

### Improved
- **Documentation** - Comprehensive updates
  - Updated quick-start guide with one-command setup
  - Added "What's Running?" section explaining all services
  - Improved troubleshooting guidance
  - Added dashboard verification steps

## [0.3.0] - 2025-10-10

### Added
- Historical data collection with arrival logging
- Daily performance aggregation (route-level on-time percentages)
- Automatic performance correlation with weather conditions
- Symfony Scheduler integration for automated tasks

## [0.2.0] - 2025-10-08

### Added
- Realtime arrival predictions with 3-tier fallback system
- Countdown timers with HIGH/MEDIUM/LOW confidence levels
- Position-based arrival estimation using GPS interpolation

## [0.1.0] - 2025-10-06

### Added
- Initial release
- 6-color vehicle status system with severity labels
- Crowd feedback system (ahead/on_time/late voting)
- Dad jokes easter egg (10% chance)
- GTFS static and realtime feed processing
- Headway calculation with position-based interpolation
- Redis caching for realtime data
- Docker-based development environment

[Unreleased]: https://github.com/samuelwilk/mind-the-wait/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/samuelwilk/mind-the-wait/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/samuelwilk/mind-the-wait/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/samuelwilk/mind-the-wait/releases/tag/v0.1.0
