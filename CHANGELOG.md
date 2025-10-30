# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.10.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.9.0...v0.10.0) (2025-10-30)


### Features

* add feed health monitoring with outage banner ([8975ddc](https://github.com/samuelwilk/mind-the-wait/commit/8975ddc64f4d52f96d58e11829cb108357940db7))
* implement Reliability Context Panel (Feature 5) ([c03568b](https://github.com/samuelwilk/mind-the-wait/commit/c03568bfacb9f7460d2f6000a195129553b2cfcb))
* implement Schedule Realism Index (Feature 3) ([23b1499](https://github.com/samuelwilk/mind-the-wait/commit/23b1499d551cf105cfcaaf91f8402dec78c119b9))
* implement Stop-Level Reliability Map (Feature 1) ([a0822eb](https://github.com/samuelwilk/mind-the-wait/commit/a0822eb1de1a39cc580fa569921843f639b5dd68))


### Bug Fixes

* implement Bayesian adjustment for small sample size bias ([5197fdf](https://github.com/samuelwilk/mind-the-wait/commit/5197fdf8754ff72b599a64134030d4ef525fdb2d))
* improve stop-level reliability chart mobile responsiveness and DTO type safety ([3267716](https://github.com/samuelwilk/mind-the-wait/commit/3267716524b3d4b768b01005d2bad5d92148bc60))


### Documentation

* update code quality audit to reflect completed refactors ([4dfe15f](https://github.com/samuelwilk/mind-the-wait/commit/4dfe15f1c439b273d2272629099e668125b184c8))


### Code Refactoring

* complete high-priority code quality improvements ([9a31327](https://github.com/samuelwilk/mind-the-wait/commit/9a31327f0ca9554a830e7220908a1f411affa7f2))
* implement medium-priority refactors (WeatherService + InsightGenerator) ([5685f5f](https://github.com/samuelwilk/mind-the-wait/commit/5685f5f95066a1daf33e8c26880bad1cf8fdd57c))
* move PerformanceAggregator calculations to repository layer ([8bdcce4](https://github.com/samuelwilk/mind-the-wait/commit/8bdcce44c121b8656737967e647c4137f0fc88b6))

## [0.9.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.8.2...v0.9.0) (2025-10-22)


### Features

* comprehensive API improvements ([ca584b0](https://github.com/samuelwilk/mind-the-wait/commit/ca584b00c66d2ec26fccf7877ab652531cac44c3))
* migrate all ECS services to Fargate Spot for 70% cost savings ([970a928](https://github.com/samuelwilk/mind-the-wait/commit/970a9285eb919105d110a33469f1a6d5eed68911))


### Bug Fixes

* enable DAMA bundle and refactor tests with Foundry factories ([84052d6](https://github.com/samuelwilk/mind-the-wait/commit/84052d6cdf815bbea48c048f7dda227ef29ba875))
* order route stops by sequence for proper visualization ([a371f3a](https://github.com/samuelwilk/mind-the-wait/commit/a371f3a6bd5093025aff2e056f068e792b618738))
* update RouteSearchComponent for multi-city support ([23e31a5](https://github.com/samuelwilk/mind-the-wait/commit/23e31a54154819488fa0507920ea46a855f7c245))


### Code Refactoring

* use Foundry factories for test fixtures ([8600deb](https://github.com/samuelwilk/mind-the-wait/commit/8600deb7aec4a0279bd94a4426ff271da4f4b00c))

## [0.8.2](https://github.com/samuelwilk/mind-the-wait/compare/v0.8.1...v0.8.2) (2025-10-19)


### Bug Fixes

* heatmap charts ([d1b7189](https://github.com/samuelwilk/mind-the-wait/commit/d1b718931b5a180ad617be6204f1a208845576e9))

## [0.8.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.8.0...v0.8.1) (2025-10-19)


### Bug Fixes

* update deployment workflow for split scheduler services ([387a418](https://github.com/samuelwilk/mind-the-wait/commit/387a4188b46b3a37b40bdc3a1113e7a58909ce66))

## [0.8.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.7.2...v0.8.0) (2025-10-19)


### Features

* add comprehensive TLS/HTTPS setup for local development and iOS simulator ([a2ef060](https://github.com/samuelwilk/mind-the-wait/commit/a2ef060c742aab3d31fd67041d3ed7de0fc1748c))
* add iOS API infrastructure configuration ([354899f](https://github.com/samuelwilk/mind-the-wait/commit/354899fc4eb80f7eeb5056ae44426398165a53a8))
* add mobile-optimized v1 API endpoints for iOS app ([455a019](https://github.com/samuelwilk/mind-the-wait/commit/455a01928bf8414aa6ea670c066e1238eb2ea666))
* split scheduler into high-frequency and low-frequency services ([0e77558](https://github.com/samuelwilk/mind-the-wait/commit/0e7755811f8d2f553c00410afc40272bfa403919))


### Bug Fixes

* correct production domain in CORS config to mind-the-wait.ca ([772479f](https://github.com/samuelwilk/mind-the-wait/commit/772479f4b7cb044d24a3fe03784feecb8ad7f66f))


### Documentation

* add comprehensive AWS cost optimization strategy to reduce bill by 78-80% ([9a3ab6d](https://github.com/samuelwilk/mind-the-wait/commit/9a3ab6d49b8d22ba59732ef5a135a67ae06b4f38))
* add iOS infrastructure deployment timeline ([191b414](https://github.com/samuelwilk/mind-the-wait/commit/191b414e6fa04b9e89bed87af7a51f39c2d83ce1))
* add Live Route Visualization iOS feature planning document ([3ecb9f2](https://github.com/samuelwilk/mind-the-wait/commit/3ecb9f200a40bacfa2b1d9dacd05023cad09b56b))
* document scheduler split fix for weather collection reliability ([8f51bf4](https://github.com/samuelwilk/mind-the-wait/commit/8f51bf40974ee0e25236cc852f588304dc82e836))
* reorganize documentation into planning/ and implemented/ directories ([d2853a9](https://github.com/samuelwilk/mind-the-wait/commit/d2853a9574936f99d38541ac5cf528b341fb797f))
* revise AWS cost optimization plan to preserve scheduler architecture ([8474d93](https://github.com/samuelwilk/mind-the-wait/commit/8474d93ac5a9e8bf7ff55a9b482e73ec879fe8a6))
* update ENHANCED_ANALYTICS_FEATURES with modern coding standards ([a660941](https://github.com/samuelwilk/mind-the-wait/commit/a660941a0edbed56b330573f9c00e7ccc0273db5))


### Code Refactoring

* complete Phase 4 code quality improvements and fix chart regressions ([ef90fa8](https://github.com/samuelwilk/mind-the-wait/commit/ef90fa81da1b1fb15594801a7c9b2265a18d1f90))

## [0.7.2](https://github.com/samuelwilk/mind-the-wait/compare/v0.7.1...v0.7.2) (2025-10-16)


### Bug Fixes

* improve mobile responsive layout to prevent text cutoff and cramped display on route pages ([4b46ed5](https://github.com/samuelwilk/mind-the-wait/commit/4b46ed52917daa02d7e9e7d7c7051ce8a09f236a))


### Code Refactoring

* remove recommendations from AI-generated insights ([fe97eae](https://github.com/samuelwilk/mind-the-wait/commit/fe97eae6f9e7b0575cb0e6c04b49b518aa817a8c))

## [0.7.1](https://github.com/samuelwilk/mind-the-wait/compare/v0.7.0...v0.7.1) (2025-10-16)


### Bug Fixes

* add missing PerformanceAggregationMessage messenger routing ([75a60c5](https://github.com/samuelwilk/mind-the-wait/commit/75a60c50460b5aae2b55aad75a022242115a5abd))
* resolve weather collection duplicate key errors and routing issues ([7dd6e94](https://github.com/samuelwilk/mind-the-wait/commit/7dd6e94b9522ff300ccc4b39dd09eac606f9fe8c))
* use timestamp comparison instead of object identity in test helper ([fc929f1](https://github.com/samuelwilk/mind-the-wait/commit/fc929f1277ad715616bebcf36754cd09478611e9))

## [0.7.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.6.0...v0.7.0) (2025-10-15)


### Features

* automate arrival prediction logging for performance analysis ([edd159c](https://github.com/samuelwilk/mind-the-wait/commit/edd159c7966d251a697d8ecc4af08b7e2e3c5039))

## [0.6.0](https://github.com/samuelwilk/mind-the-wait/compare/v0.5.1...v0.6.0) (2025-10-15)


### Features

* add temporary debug endpoint for database verification ([b49637e](https://github.com/samuelwilk/mind-the-wait/commit/b49637e39c923cb565efd5388712514f56ba0be9))
* add temporary debug endpoint with security tests ([558afe2](https://github.com/samuelwilk/mind-the-wait/commit/558afe257f74da780552840e64ccf31f011b2bb1))

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
