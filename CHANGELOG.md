# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
