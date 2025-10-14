# Recent Changes Summary

**Date**: October 14, 2025
**Changes**: Route detail heatmap implementation, Dockerfile fixes, production deployment improvements

**Previous Changes**: [October 12 changes](#october-12-2025---scheduler-refactor-and-setup-improvements)

---

## 🎯 October 14, 2025 - Latest Changes

### 1. Route Detail Heatmap Implementation

**Problem**: The "Performance by Day & Time" chart on route detail pages was showing randomly generated placeholder data instead of real arrival log data.

**Solution**: Replaced hardcoded fake data with SQL query that pulls real data from the `arrival_log` table.

**Changes Made**:

#### Updated Files
- `src/Service/Dashboard/RoutePerformanceService.php:286-349` - Implemented real data query

**Implementation Details**:
```php
// Queries arrival_log table grouped by day of week and hour bucket
SELECT
    EXTRACT(DOW FROM predicted_at) as day_of_week,
    CASE
        WHEN EXTRACT(HOUR FROM predicted_at) < 6  THEN 0
        WHEN EXTRACT(HOUR FROM predicted_at) < 9  THEN 1
        // ... (7 hour buckets total)
    END as hour_bucket,
    COUNT(*) as total,
    SUM(CASE WHEN delay_sec BETWEEN -180 AND 180 THEN 1 ELSE 0 END) as on_time
FROM arrival_log
WHERE route_id = :route_id
    AND predicted_at >= :start_date
    AND predicted_at < :end_date
GROUP BY day_of_week, hour_bucket
```

**Benefits**:
- ✅ No more fake/random data in production
- ✅ Accurate performance visualization by time of day and day of week
- ✅ Gracefully handles empty data (shows gray cells with null values)
- ✅ Will auto-populate as arrival_log data accumulates
- ✅ On-time threshold: ±3 minutes (consistent with system-wide standard)

---

### 2. Development Docker Build Fix

**Problem**: Docker build was failing with `"/docker/php.ini": not found` error.

**Solution**: Fixed incorrect file path in Dockerfile.

**Changes Made**:

#### Updated Files
- `docker/dev/Dockerfile:20` - Changed `COPY docker/php.ini` to `COPY docker/dev/php.ini`

**Impact**:
- ✅ `make docker-build` and `make docker-up` now work without errors
- ✅ Containers build successfully on fresh clones
- ✅ No disruption to existing running containers

---

### 3. Production Timezone Fix

**Issue**: Dashboard showed "last updated at 9:11:11 PM" on mobile, which was UTC time instead of local Saskatchewan time.

**Solution**: Already fixed in previous deployment (October 12) - timezone set to `America/Regina`.

**Status**:
- ✅ Fixed in `frankenphp/conf.d/10-app.ini:4`
- ✅ Deployed to production in v0.4.0
- ✅ All timestamps now show Saskatoon local time (CST/CDT)

---

## 🔄 Deployment Notes

### What's New in This Deployment

1. **Route Detail Pages**:
   - Heatmap now shows real data (or empty gray cells if no data yet)
   - No more placeholder values of 65 ± 10

2. **Development Setup**:
   - Dockerfile fixed for clean builds
   - No breaking changes

3. **Production**:
   - Timezone already deployed (working correctly)
   - Weather collection running hourly
   - All schedulers operational

### Data Collection Timeline

The heatmap will populate as arrival logging accumulates:
- **Day 1**: Empty (gray cells with null values)
- **After 1 day**: Sparse data for times with predictions
- **After 1 week**: Good coverage during peak hours
- **After 30 days**: Full heatmap with statistical significance

---

## ✅ Verification Checklist

After this deployment:

- [ ] Route detail page loads without errors
- [ ] Heatmap shows either real data or gray cells (not random numbers)
- [ ] Dashboard timestamps show Saskatoon local time
- [ ] Weather banner displays current conditions (if available)

---

## 📊 October 14, 2025 Metrics

**Files Changed**: 3
- `src/Service/Dashboard/RoutePerformanceService.php`
- `docker/dev/Dockerfile`
- `CHANGELOG.md`

**Lines Changed**: ~70
**Type**: Bug fix + Feature implementation

---

## 🎯 October 12, 2025 - Scheduler Refactor and Setup Improvements

## 🎯 Major Improvements

### 1. Scheduler Architecture Refactor

**Problem**: The scheduler container was using a bash loop (`while true; do php bin/console app:score:tick; sleep 30; done`), which was a code smell and inconsistent with Symfony best practices.

**Solution**: Migrated to Symfony Scheduler with message-based architecture.

**Changes Made**:

#### New Files
- `src/Scheduler/ScoreTickSchedule.php` - Runs every 30 seconds
- `src/MessageHandler/ScoreTickMessageHandler.php` - Handles score calculation messages

#### Updated Files
- `compose.yaml:97` - Now runs `messenger:consume` instead of bash loop
  ```yaml
  command: php bin/console messenger:consume scheduler_score_tick scheduler_weather_collection scheduler_performance_aggregation -vv
  ```

**Benefits**:
- ✅ Consistent with other scheduled tasks (weather, performance)
- ✅ Proper error handling and retry logic via Messenger
- ✅ Better logging through Symfony's messenger infrastructure
- ✅ Can be monitored with `debug:scheduler`
- ✅ Follows Symfony best practices

#### Verification
```bash
docker compose exec php bin/console debug:scheduler
```

Shows all three schedules:
- `score_tick` - Every 30 seconds
- `weather_collection` - Hourly at :00
- `performance_aggregation` - Daily at 1:00 AM

---

### 2. Timezone Configuration

**Problem**: System was using UTC for all operations, causing confusion with local time displays. Weather observations appeared "Just now" when they were actually from hours ago.

**Solution**: Configured PHP timezone to America/Regina (Saskatoon's timezone).

**Changes Made**:

#### Updated Files
- `docker/php.ini:4` - Changed `date.timezone=UTC` to `date.timezone=America/Regina`

**Impact**:
- All timestamps now display in Saskatoon local time (CST/CDT)
- Scheduled tasks run at expected local times
- Weather observations show accurate local times
- Dashboard displays times users expect to see

#### Verification
```bash
docker compose exec php php -r "echo date_default_timezone_get();"
# Output: America/Regina

docker compose exec php date
# Output: Sun Oct 12 10:35:25 CST 2025
```

---

### 3. Weather Collection Fixes

**Problem 1**: Weather was not updating automatically (hadn't updated in 11 hours)
**Root Cause**: Scheduler wasn't consuming the `scheduler_weather_collection` transport

**Solution**: Added weather collection transport to messenger:consume command

**Problem 2**: Weather banner showed "Just now" for hours-old data
**Root Cause**: Relative time display ("Just now", "5 min ago") was misleading for hourly updates

**Solution**: Changed to absolute timestamps
- Today: Shows time only (e.g., "11:00 PM")
- Yesterday: "Yesterday at 3:00 PM"
- Older: Full date + time (e.g., "Oct 12, 11:00 PM")

**Problem 3**: Banner showed future forecast data as "current"
**Root Cause**: `findLatest()` returned highest timestamp, including forecasts

**Solution**: Added filter in `WeatherObservationRepository::findLatest()`
```php
->where('w.observedAt <= :now')
->setParameter('now', new \DateTimeImmutable())
```

#### Updated Files
- `src/Twig/Components/LiveWeatherBanner.php:142-165` - Absolute timestamp display
- `src/Repository/WeatherObservationRepository.php:26-38` - Filter future observations
- `templates/dashboard/index.html.twig:14-16` - Always render banner (handles null internally)

---

### 4. Enhanced Setup Process

**Problem**: Setup was manual and required multiple commands. No single command to get the application running.

**Solution**: Created comprehensive `make setup` target that does everything.

**Changes Made**:

#### Updated Files
- `Makefile:102-143` - Complete setup automation

**New Targets**:
```makefile
setup:           # Complete application setup (7 steps)
gtfs-load:       # Load GTFS static data
weather-collect: # Collect current weather
```

**What `make setup` Does**:
1. ✅ Build and start Docker containers
2. ✅ Install Composer dependencies
3. ✅ Set up development database
4. ✅ Set up test database
5. ✅ Load GTFS static data (routes, stops, trips)
6. ✅ Collect initial weather data
7. ✅ Run initial score calculation

**Time**: 3-7 minutes on first run

#### Usage
```bash
git clone https://github.com/samuelwilk/mind-the-wait.git
cd mind-the-wait
make setup
```

---

### 5. Dashboard Improvements

**Problem**: Top performers and needs attention lists were intermittently empty on page refresh.

**Solution**: Improved filtering and sorting logic.

#### Updated Files
- `src/Service/Dashboard/OverviewService.php:227-322`

**Changes**:
- Top performers: Filter by active vehicles AND valid grades, secondary sort by vehicle count
- Needs attention: Target grades D/F and single-vehicle C routes specifically
- More stable across page refreshes

---

## 📚 Documentation Updates

### New Files Created

1. **CHANGELOG.md** - Comprehensive version history
   - Unreleased changes
   - Version 0.3.0, 0.2.0, 0.1.0
   - Detailed added/changed/fixed sections

2. **docs/GETTING_STARTED.md** - Complete beginner's guide
   - What is Mind the Wait?
   - Quick start with `make setup`
   - Understanding the system
   - Core concepts (headway, grading, confidence)
   - Common tasks and troubleshooting
   - Customization guide

3. **docs/RECENT_CHANGES.md** - This document

### Updated Files

1. **README.md** - Project root
   - Added badges (MIT, Symfony 7.3, PHP 8.3)
   - Feature highlights with emojis
   - One-command setup instructions
   - Architecture diagram
   - Common tasks section
   - Troubleshooting quick reference

2. **docs/README.md** - Documentation index
   - Added link to Getting Started Guide
   - Added link to Changelog

3. **docs/development/quick-start.md** - Quick start guide
   - Simplified to focus on `make setup`
   - Added "What's Running?" section
   - Updated verification steps
   - Added dashboard verification

---

## 🔄 Migration Guide

### For Existing Installations

If you have an existing installation, update with:

```bash
# 1. Pull latest changes
git pull

# 2. Rebuild containers (for timezone change)
docker compose build php scheduler

# 3. Restart scheduler with new configuration
docker compose up -d scheduler

# 4. Verify schedules are running
docker compose exec php bin/console debug:scheduler

# 5. Check logs
docker compose logs -f scheduler
```

### Breaking Changes

**None** - All changes are backward compatible. Existing installations will continue to work, but won't benefit from:
- Improved scheduler architecture
- Correct timezone display
- Automated weather collection

---

## ✅ Verification Checklist

After updating, verify:

- [ ] All three scheduler tasks appear in `debug:scheduler`
- [ ] Scheduler logs show score tick messages every ~30 seconds
- [ ] Weather collection scheduled for next hour (:00)
- [ ] Timezone shows `America/Regina` in PHP
- [ ] Dashboard displays current weather with local timestamp
- [ ] Top performers/needs attention lists populated
- [ ] `make setup` completes successfully on fresh clone

### Quick Verification

```bash
# Check scheduler is consuming all transports
docker compose logs scheduler | grep "Consuming messages"
# Should show: scheduler_score_tick, scheduler_weather_collection, scheduler_performance_aggregation

# Check score ticks are running
docker compose logs scheduler | tail -20 | grep ScoreTickMessage
# Should show recent messages

# Check timezone
docker compose exec php php -r "echo date_default_timezone_get();"
# Should show: America/Regina

# Check scheduled tasks
docker compose exec php bin/console debug:scheduler
# Should show all three schedules with next run times in -0600 (CST)
```

---

## 📊 Metrics

**Files Changed**: 12
**Files Created**: 4
**Lines Changed**: ~800
**Documentation Pages**: 3 new, 3 updated

**Impact**:
- Setup time: Reduced from ~15 minutes (manual) to 3-7 minutes (automated)
- Code quality: Removed bash loop code smell
- Maintainability: Improved consistency with Symfony best practices
- User experience: Accurate timestamps, reliable weather updates
- Developer experience: Comprehensive documentation, one-command setup

---

## 🎉 Next Steps

Recommended follow-up improvements:

1. **Add Weather Alerts** - Surface severe weather warnings in dashboard
2. **Performance Benchmarks** - Add route comparison tool
3. **Arrival Prediction Accuracy** - Track and display prediction accuracy
4. **Mobile App** - Create companion mobile application
5. **Multi-Agency Support** - Extend to support multiple transit agencies
6. **Real-time Notifications** - Push notifications for service disruptions

---

## 📞 Support

Questions about these changes?
- Check [docs/GETTING_STARTED.md](GETTING_STARTED.md) for comprehensive guide
- See [CHANGELOG.md](../CHANGELOG.md) for version history
- Open an issue on [GitHub](https://github.com/samuelwilk/mind-the-wait/issues)
