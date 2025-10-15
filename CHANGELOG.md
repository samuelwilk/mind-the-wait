# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.5.0...v0.5.1) (2025-10-15)


### Documentation

* add comprehensive analytics implementation plan ([b05b99e](https://github.com/samuelwilk/mind-the-wait/commit/b05b99e64b284811824bd923dbc8ae6801e5c1d1))
* add least-privilege IAM policies for AWS access ([2681011](https://github.com/samuelwilk/mind-the-wait/commit/26810110a4831170fa839725dad571cc04b56fd9))

## [0.5.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.4.2...v0.5.0) (2025-10-15)


### Features

* add vehicle bunching detection infrastructure with comprehensive tests ([93d0503](https://github.com/samuelwilk/mind-the-wait/commit/93d0503eaf353040a33c275a4585214b0fe26dce))
* automate daily bunching detection with scheduler ([995013a](https://github.com/samuelwilk/mind-the-wait/commit/995013a17b49e2112f3c83241d07185e4b8c812a))


### Bug Fixes

* add missing MESSENGER_TRANSPORT_DSN to CI workflow ([3f7d3d7](https://github.com/samuelwilk/mind-the-wait/commit/3f7d3d7389a858bfbe668f9c7191e2bda8c5e282))
* remove hardcoded data from bunching by weather chart ([dafeeea](https://github.com/samuelwilk/mind-the-wait/commit/dafeeeae6e07fdcf6fd82b4bdebc57e29a59f720))


### Code Refactoring

* remove unused repository dependencies ([30d00a3](https://github.com/samuelwilk/mind-the-wait/commit/30d00a35d02beb544e3d581de7ce17233792f7e4))

## [0.4.2](https://github.com/samuelwilk/mind-the-wait/compare/v0.4.1...v0.4.2) (2025-10-14)


### Bug Fixes

* implement real data query for route detail heatmap ([faec5a0](https://github.com/samuelwilk/mind-the-wait/commit/faec5a02b98ed7b42a15d4c148b11868e26cbf81))

## [0.4.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.4.0...v0.4.1) (2025-10-14)


### Bug Fixes

* pass message object to InsightCacheWarmingSchedule ([202c79e](https://github.com/samuelwilk/mind-the-wait/commit/202c79eab5275579ee8d11eaa43a459b5641d1ec))
* update scheduler to consume from correct transports ([091448c](https://github.com/samuelwilk/mind-the-wait/commit/091448c69db68dfb70393399bc0f895f4586c8d2))

## [0.4.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.3.1...v0.4.0) (2025-10-14)


### Features

* add ArcGIS and GTFS-RT environment variables ([3917e0f](https://github.com/samuelwilk/mind-the-wait/commit/3917e0fa7efa347a8aad3f368804e8fbe3e0ecb4))


### Bug Fixes

* make gtfsStaticFallback parameter nullable ([d14bb42](https://github.com/samuelwilk/mind-the-wait/commit/d14bb42a564059d80c76eb7c989795dac87bce7e))

## [0.3.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.3.0...v0.3.1) (2025-10-14)


### Bug Fixes

* add asset-map:compile for production deployment ([e141f9a](https://github.com/samuelwilk/mind-the-wait/commit/e141f9a29548a29de7e97fcde12cbc8a795d0ce7))
* add MESSENGER_TRANSPORT_DSN to production environment ([bb084a4](https://github.com/samuelwilk/mind-the-wait/commit/bb084a4d55bed77d7f4e1b6dfc20bc76ec1fb110))
* build Tailwind CSS in production Docker image ([416b77c](https://github.com/samuelwilk/mind-the-wait/commit/416b77c08aba6f1213351360017be25028f300a0))

## [0.3.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.2.5...v0.3.0) (2025-10-14)


### Features

* add Redis readiness check and auto-migrations ([f418ff9](https://github.com/samuelwilk/mind-the-wait/commit/f418ff9374fe44e6575dc4bda89599811f3a39ef))

## [0.2.5](https://github.com/samuelwilk/mind-the-wait/compare/v0.2.4...v0.2.5) (2025-10-14)


### Bug Fixes

* set Caddy document root to /app/public for Symfony ([c8f2cb0](https://github.com/samuelwilk/mind-the-wait/commit/c8f2cb0f7e33ee6234c19f7b98d104a9f3ccd549))

## [0.2.4](https://github.com/samuelwilk/mind-the-wait/compare/v0.2.3...v0.2.4) (2025-10-14)


### Bug Fixes

* configure FrankenPHP to listen on port 8080 ([0fee8a7](https://github.com/samuelwilk/mind-the-wait/commit/0fee8a749aaae61cbaac2758f281b3c1b0fe9499))

## [0.2.3](https://github.com/samuelwilk/mind-the-wait/compare/v0.2.2...v0.2.3) (2025-10-14)


### Bug Fixes

* disable Mercure and Vulcain in production Caddyfile ([cc13231](https://github.com/samuelwilk/mind-the-wait/commit/cc13231620c43d39d25fdfafc35f3f06962272f5))

## [0.2.2](https://github.com/samuelwilk/mind-the-wait/compare/v0.2.1...v0.2.2) (2025-10-14)


### Bug Fixes

* always push :latest tag to ECR on all builds ([609db63](https://github.com/samuelwilk/mind-the-wait/commit/609db635ce63e17ec5eadca6ca9033be492da073))
* deploy to ECS when Release Please PRs are merged ([c3134f2](https://github.com/samuelwilk/mind-the-wait/commit/c3134f20bb3a18762fb3749f5033aa59dc2d24f0))
* update Caddyfile comment to bust Docker build cache ([e8c2ef4](https://github.com/samuelwilk/mind-the-wait/commit/e8c2ef4b62382029affc4c528decebd36d2b0ffa))

## [0.2.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.2.0...v0.2.1) (2025-10-14)


### Bug Fixes

* disable Caddyfile metrics server causing parse error ([7b87dba](https://github.com/samuelwilk/mind-the-wait/commit/7b87dba1f3da25cd50bee2d96398a5b9cbf78a95))

## [0.2.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.1.1...v0.2.0) (2025-10-14)


### Features

* enable HTTPS with automatic certificate validation ([8b0413d](https://github.com/samuelwilk/mind-the-wait/commit/8b0413d3f248a4a95ee841567dff4f42f7689791))

## [0.1.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.1.0...v0.1.1) (2025-10-14)


### Bug Fixes

* add Release Please manifest and move config to root ([75d4498](https://github.com/samuelwilk/mind-the-wait/commit/75d4498c42c3aa42e1fa75dbd8fe717371741ddf))
* update to non-deprecated Release Please action ([e156b40](https://github.com/samuelwilk/mind-the-wait/commit/e156b40bb59d54b2af8db08b909d20a7dc70eccc))


### Documentation

* add GitHub environment deployment rules guide ([de8886b](https://github.com/samuelwilk/mind-the-wait/commit/de8886b8caffe825f58afd1932c36dff8e62c486))

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
- **Route Detail Page Heatmap** - Implemented real data query for "Performance by Day & Time" chart
  - Queries `arrival_log` table grouped by day of week and hour bucket
  - Calculates on-time percentage (±3 minutes = on-time) for each time period
  - Returns null for cells with no data (displays as gray in chart)
  - Removed hardcoded placeholder data that generated random values
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
- **Development Docker Build** - Fixed php.ini path in Dockerfile
  - Changed from `COPY docker/php.ini` to `COPY docker/dev/php.ini`
  - Resolves "file not found" error during container build

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
