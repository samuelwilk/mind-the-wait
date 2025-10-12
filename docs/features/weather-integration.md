# Weather Integration: Transit Performance Context

## Why Weather Matters for Transit

Saskatoon winters are brutal (-30°C+, heavy snow), which dramatically impacts transit reliability:

### Impact Patterns

**Snow/Ice:**
- ❄️ **Slower speeds** → Longer trip times → Cascading delays
- 🚌 **Bus bunching** → Vehicles catch up to each other due to uneven slowdowns
- 🛑 **Missed stops** → Drivers skip stops to make up time
- 🚧 **Route detours** → Road closures force alternate routes

**Extreme Cold (<-25°C):**
- 🥶 **Cold starts** → Buses take longer to warm up, first trips delayed
- 🔋 **Battery issues** → Electrical systems struggle, breakdowns increase
- 🚪 **Door freezing** → Delays at stops while doors thaw
- 👥 **Higher ridership** → More people avoid walking, crowding delays

**Rain/Slush:**
- 🌧️ **Reduced visibility** → Slower driving
- 💦 **Slippery roads** → Cautious driving, longer following distances
- 🚗 **More traffic** → Cars slow down, buses get stuck in congestion

**Clear Weather:**
- ✅ **Normal operations** → Baseline reliability
- 🎯 **Predictable performance** → Use for route comparisons

---

## Value Propositions

### For Riders
> "Route 27 is usually D-grade, but drops to F during snow. Take Route 14 instead - it stays B-grade even in winter."

> "System-wide on-time performance: 78% clear weather, 52% during snowfall. Plan extra 10 min today."

### For City Officials
> "Bunching incidents increase 3x during snow. Deploy supervisors to high-impact routes."

> "Route 43 handles winter better than Route 27 (snow vs clear: -12% vs -28% on-time). Why? Investigate route design."

### For Advocates
> "City claims '90% on-time,' but that's only on clear days. During winter (40% of year), it's 62%. Misleading metric."

---

## Weather Data Sources

### 1. Environment Canada (Official, Free)

**API:** Weather Data Web Service
- **Endpoint:** `https://dd.weather.gc.ca/citypage_weather/xml/SK/s0000797_e.xml` (Saskatoon)
- **Update Frequency:** Hourly
- **Cost:** Free (government open data)
- **Data:**
  - Current temperature (°C)
  - Weather condition (clear, cloudy, snow, rain, etc.)
  - Visibility (km)
  - Wind speed (km/h)
  - Precipitation (mm)

**Pros:**
- Official government data
- No rate limits
- No API key required
- Historical data available

**Cons:**
- XML format (harder to parse than JSON)
- Limited to hourly updates
- No forecasts in this endpoint (need separate API)

**Example Request:**
```bash
curl https://dd.weather.gc.ca/citypage_weather/xml/SK/s0000797_e.xml
```

**Example Response (simplified):**
```xml
<siteData>
  <currentConditions>
    <temperature units="C">-15.2</temperature>
    <condition>Snow</condition>
    <visibility units="km">2.5</visibility>
    <wind>
      <speed units="km/h">25</speed>
    </wind>
  </currentConditions>
</siteData>
```

---

### 2. Open-Meteo (Free, Modern API)

**Website:** https://open-meteo.com
- **Endpoint:** `https://api.open-meteo.com/v1/forecast`
- **Cost:** Free (up to 10,000 requests/day)
- **Data:**
  - Current weather
  - Hourly forecast (7 days)
  - Historical weather (back to 1940!)
  - Snow depth, precipitation, wind, temperature

**Pros:**
- JSON API (easy to parse)
- Historical data API for backfilling
- No API key required
- Good documentation

**Cons:**
- Not official government source
- Rate limits on free tier

**Example Request:**
```bash
curl "https://api.open-meteo.com/v1/forecast?latitude=52.1324&longitude=-106.6607&current=temperature_2m,precipitation,snowfall,weather_code&timezone=America/Regina"
```

**Example Response:**
```json
{
  "current": {
    "time": "2025-10-11T18:00",
    "temperature_2m": -15.2,
    "precipitation": 0.5,
    "snowfall": 0.8,
    "weather_code": 73  // Snow
  }
}
```

**Historical Data:**
```bash
curl "https://archive-api.open-meteo.com/v1/archive?latitude=52.1324&longitude=-106.6607&start_date=2025-01-01&end_date=2025-10-11&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,snowfall_sum"
```

---

### 3. OpenWeatherMap (Popular, Paid)

**Website:** https://openweathermap.org
- **Cost:** Free tier (60 calls/min), paid for historical
- **Data:** Current, forecast, historical

**Pros:**
- Very popular, well-documented
- JSON API

**Cons:**
- Historical data requires paid plan ($40-150/month)
- Rate limits on free tier

**Recommendation:** Use **Open-Meteo** for historical backfill, **Environment Canada** for current conditions.

---

## Database Schema

### New Table: `weather_observation`

```sql
CREATE TABLE weather_observation (
    id SERIAL PRIMARY KEY,
    observed_at TIMESTAMP NOT NULL,

    -- Temperature
    temperature_celsius DECIMAL(4,1) NOT NULL,
    feels_like_celsius DECIMAL(4,1),

    -- Precipitation
    precipitation_mm DECIMAL(5,1),
    snowfall_cm DECIMAL(4,1),
    snow_depth_cm INTEGER,

    -- Conditions
    weather_code INTEGER,              -- Open-Meteo code (71=snow, 0=clear)
    weather_condition VARCHAR(50),     -- Human-readable: 'snow', 'clear', 'rain'
    visibility_km DECIMAL(5,2),
    wind_speed_kmh DECIMAL(5,1),

    -- Severity classification
    transit_impact VARCHAR(20),        -- 'none', 'minor', 'moderate', 'severe'

    -- Source
    data_source VARCHAR(50) NOT NULL,  -- 'environment_canada', 'open_meteo'

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(observed_at)
);

CREATE INDEX idx_weather_observed_at ON weather_observation(observed_at DESC);
CREATE INDEX idx_weather_impact ON weather_observation(transit_impact, observed_at DESC);
```

**Transit Impact Classification (Year-Round):**
```php
function classifyTransitImpact(array $weather): string
{
    $temp = $weather['temperature_celsius'];
    $snowfall = $weather['snowfall_cm'] ?? 0;
    $precipitation = $weather['precipitation_mm'] ?? 0;
    $visibility = $weather['visibility_km'] ?? 10;
    $windSpeed = $weather['wind_speed_kmh'] ?? 0;
    $condition = $weather['weather_condition'];

    // SEVERE: Dangerous conditions, major delays expected
    // Winter: Extreme cold, blizzard conditions
    if ($temp < -35 || $snowfall > 15 || $visibility < 0.5) {
        return 'severe';
    }

    // Summer: Severe thunderstorms, flooding, extreme heat
    if ($condition === 'thunderstorm' ||
        $precipitation > 25 ||  // Heavy rain (flooding risk)
        $temp > 35 ||           // Extreme heat (mechanical failures)
        $windSpeed > 70) {      // High winds (safety hazards)
        return 'severe';
    }

    // MODERATE: Noticeable impact, some delays
    // Winter: Heavy snow, very cold
    if ($temp < -25 || $snowfall > 5 || ($condition === 'snow' && $snowfall > 2)) {
        return 'moderate';
    }

    // Year-round: Heavy rain, poor visibility, strong winds
    if ($precipitation > 10 ||           // Heavy rain
        $visibility < 2 ||               // Poor visibility
        $windSpeed > 50 ||               // Strong winds
        $condition === 'showers' ||      // Heavy rain showers
        ($condition === 'rain' && $precipitation > 5)) {
        return 'moderate';
    }

    // MINOR: Slight impact, minimal delays
    // Winter: Cold, light snow
    if ($temp < -15 || ($condition === 'snow' && $snowfall <= 2)) {
        return 'minor';
    }

    // Summer: Light rain, moderate heat
    if ($condition === 'rain' ||
        $precipitation > 2 ||
        ($temp > 28 && $temp <= 35) ||  // Hot but not extreme
        $windSpeed > 30) {               // Moderate winds
        return 'minor';
    }

    // NONE: Clear conditions, no significant impact
    return 'none';
}
```

---

## Foreign Key to Performance Tables

Add weather context to daily performance:

```sql
-- Modify route_performance_daily
ALTER TABLE route_performance_daily
ADD COLUMN weather_observation_id INTEGER REFERENCES weather_observation(id);

-- Modify bunching_incident
ALTER TABLE bunching_incident
ADD COLUMN weather_observation_id INTEGER REFERENCES weather_observation(id);
```

This allows queries like:
```sql
-- Compare on-time % in clear vs snow conditions
SELECT
  w.weather_condition,
  AVG(r.on_time_percentage) as avg_on_time,
  COUNT(*) as days
FROM route_performance_daily r
JOIN weather_observation w ON r.date = DATE(w.observed_at)
WHERE r.route_id = 42 AND w.observed_at BETWEEN r.date AND r.date + INTERVAL '1 day'
GROUP BY w.weather_condition;

-- Result:
-- clear: 78.2% on-time (120 days)
-- snow:  52.1% on-time (45 days)
-- rain:  68.5% on-time (30 days)
```

---

## Weather Collection Command

**Console Command:** `app:collect:weather`

Runs every hour via cron.

```php
<?php
// src/Command/CollectWeatherCommand.php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:collect:weather',
    description: 'Fetch and store current weather conditions for Saskatoon'
)]
class CollectWeatherCommand extends Command
{
    private const SASKATOON_LAT = 52.1324;
    private const SASKATOON_LON = -106.6607;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = HttpClient::create();

        // Fetch from Open-Meteo
        $response = $client->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => self::SASKATOON_LAT,
                'longitude' => self::SASKATOON_LON,
                'current' => 'temperature_2m,precipitation,snowfall,snow_depth,weather_code,visibility,wind_speed_10m',
                'timezone' => 'America/Regina',
            ],
        ]);

        $data = $response->toArray();
        $current = $data['current'];

        // Map weather code to human-readable condition
        $condition = $this->mapWeatherCode($current['weather_code']);
        $impact = $this->classifyTransitImpact($current['temperature_2m'], $condition, $current);

        // Insert into database
        $this->connection->executeStatement(
            'INSERT INTO weather_observation (
                observed_at, temperature_celsius, precipitation_mm, snowfall_cm,
                snow_depth_cm, weather_code, weather_condition, visibility_km,
                wind_speed_kmh, transit_impact, data_source
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (observed_at) DO UPDATE SET
                temperature_celsius = EXCLUDED.temperature_celsius,
                precipitation_mm = EXCLUDED.precipitation_mm,
                snowfall_cm = EXCLUDED.snowfall_cm,
                weather_condition = EXCLUDED.weather_condition,
                transit_impact = EXCLUDED.transit_impact',
            [
                $current['time'],
                $current['temperature_2m'],
                $current['precipitation'] ?? 0,
                $current['snowfall'] ?? 0,
                $current['snow_depth'] ?? 0,
                $current['weather_code'],
                $condition,
                $current['visibility'] ?? null,
                $current['wind_speed_10m'] ?? null,
                $impact,
                'open_meteo',
            ]
        );

        $output->writeln(sprintf(
            'Weather collected: %s, %s°C, %s (impact: %s)',
            $current['time'],
            $current['temperature_2m'],
            $condition,
            $impact
        ));

        return Command::SUCCESS;
    }

    private function mapWeatherCode(int $code): string
    {
        // Open-Meteo weather codes: https://open-meteo.com/en/docs
        return match (true) {
            $code === 0 => 'clear',
            $code >= 1 && $code <= 3 => 'cloudy',
            $code >= 51 && $code <= 67 => 'rain',
            $code >= 71 && $code <= 77 => 'snow',
            $code >= 80 && $code <= 86 => 'showers',
            $code >= 95 && $code <= 99 => 'thunderstorm',
            default => 'unknown',
        };
    }

    private function classifyTransitImpact(float $temp, string $condition, array $current): string
    {
        $snowfall = $current['snowfall'] ?? 0;
        $visibility = $current['visibility'] ?? 10000; // meters

        // Severe
        if ($temp < -30 || $snowfall > 10 || $visibility < 1000) {
            return 'severe';
        }

        // Moderate
        if ($temp < -20 || $snowfall > 2 || $visibility < 5000 || $condition === 'snow') {
            return 'moderate';
        }

        // Minor
        if ($temp < -10 || $condition === 'rain') {
            return 'minor';
        }

        return 'none';
    }
}
```

**Cron Schedule:**

```yaml
# In docker-compose.yaml scheduler service
scheduler:
  command: bash -lc '
    while true; do
      php bin/console app:score:tick || true;
      sleep 30;
    done;

    # Collect weather every hour
    0 * * * * php bin/console app:collect:weather;

    # Collect performance at midnight
    0 0 * * * php bin/console app:collect:daily-performance;
  '
```

---

## Backfilling Historical Weather

To analyze past patterns, backfill weather data:

```php
// src/Command/BackfillWeatherCommand.php

#[AsCommand(name: 'app:backfill:weather')]
class BackfillWeatherCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = HttpClient::create();

        // Fetch last 90 days of weather
        $endDate = new \DateTime('today');
        $startDate = (clone $endDate)->modify('-90 days');

        $response = $client->request('GET', 'https://archive-api.open-meteo.com/v1/archive', [
            'query' => [
                'latitude' => 52.1324,
                'longitude' => -106.6607,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'hourly' => 'temperature_2m,precipitation,snowfall,snow_depth,weather_code',
                'timezone' => 'America/Regina',
            ],
        ]);

        $data = $response->toArray();

        // Insert each hourly observation
        foreach ($data['hourly']['time'] as $index => $time) {
            $this->connection->executeStatement(
                'INSERT INTO weather_observation (...) VALUES (...) ON CONFLICT DO NOTHING',
                [/* data */]
            );
        }

        return Command::SUCCESS;
    }
}
```

Run once: `docker compose exec php bin/console app:backfill:weather`

---

## Dashboard Features

### 1. Weather Badge on Route Cards

```
Route 27: Silverspring / University     D-  ❄️ SNOW
  58% on-time  |  Avg delay: 6.2 min
  ⚠️ Performance degrades 15% in snow
```

### 2. Weather Impact Chart

```
Route 27 Performance by Weather Condition

  Clear   ████████████████░░  78% on-time
  Cloudy  ███████████████░░░  72% on-time
  Rain    ████████████░░░░░░  58% on-time
  Snow    █████████░░░░░░░░░  45% on-time

💡 Recommendation: Take Route 14 during snow (stays at 82%)
```

### 3. System Health with Weather Context

```
Saskatoon Transit - Live Performance
Current Weather: ❄️ Snow, -18°C (Moderate Impact)

System Grade: D+ (61% on-time)
Expected Grade (Clear): C+ (72% on-time)
Weather Impact: -11% on-time performance

⚠️ Bunching alerts: 12 active (3x normal for snow)
```

### 4. Route Comparison with Weather Adjustment

```
Route to University - Current Conditions (Snow)

Route 27  D-  45% on-time  Est. arrival: 20 min
Route 14  B   82% on-time  Est. arrival: 15 min  ⭐ BEST

💡 Route 14 handles snow better (-8% vs -28% impact)
```

---

## API Endpoints

### 1. Current Weather

```
GET /api/weather/current

Response:
{
  "observed_at": "2025-10-11T18:00:00Z",
  "temperature_celsius": -15.2,
  "weather_condition": "snow",
  "transit_impact": "moderate",
  "impact_description": "Expect 10-15% longer travel times"
}
```

### 2. Weather-Adjusted Performance

```
GET /api/route/{routeId}/performance-by-weather

Response:
{
  "route_id": "14536",
  "route_name": "Route 27",
  "performance": {
    "clear": {
      "on_time_percentage": 78.2,
      "avg_delay_sec": 180,
      "days_observed": 120
    },
    "snow": {
      "on_time_percentage": 52.1,
      "avg_delay_sec": 468,
      "days_observed": 45
    },
    "rain": {
      "on_time_percentage": 68.5,
      "avg_delay_sec": 264,
      "days_observed": 30
    }
  },
  "weather_sensitivity": "high",
  "recommendation": "Avoid during snow, use Route 14 alternative"
}
```

### 3. System Impact Forecast

```
GET /api/system/weather-impact-forecast

Response:
{
  "current_impact": "moderate",
  "forecast_24h": [
    {
      "time": "2025-10-11T19:00:00Z",
      "temperature_celsius": -16.5,
      "condition": "snow",
      "transit_impact": "moderate",
      "expected_system_grade": "D+",
      "expected_on_time_pct": 62
    },
    // ... 24 hours
  ]
}
```

---

## Analysis Use Cases

### 1. Winter Operations Report

**Question:** "How does snow impact different routes?"

**Query:**
```sql
SELECT
  r.short_name,
  AVG(CASE WHEN w.weather_condition = 'clear' THEN p.on_time_percentage END) as clear_on_time,
  AVG(CASE WHEN w.weather_condition = 'snow' THEN p.on_time_percentage END) as snow_on_time,
  (AVG(CASE WHEN w.weather_condition = 'clear' THEN p.on_time_percentage END) -
   AVG(CASE WHEN w.weather_condition = 'snow' THEN p.on_time_percentage END)) as impact
FROM route_performance_daily p
JOIN route r ON p.route_id = r.id
JOIN weather_observation w ON DATE(w.observed_at) = p.date
GROUP BY r.id, r.short_name
ORDER BY impact DESC;
```

**Result:**
```
Route  Clear  Snow   Impact
27     78%    45%    -33%   ← Most affected
43     72%    48%    -24%
14     92%    84%    -8%    ← Best in snow
```

**Insight:** Route 27 suffers 4x more than Route 14 in snow. Why? Investigate route design, hills, traffic patterns.

---

### 2. Extreme Cold Impact

**Question:** "At what temperature does performance drop significantly?"

**Query:**
```sql
SELECT
  FLOOR(w.temperature_celsius / 5) * 5 as temp_bucket,
  AVG(p.on_time_percentage) as avg_on_time,
  COUNT(*) as observations
FROM route_performance_daily p
JOIN weather_observation w ON DATE(w.observed_at) = p.date
GROUP BY temp_bucket
ORDER BY temp_bucket;
```

**Result:**
```
Temp      On-Time  Days
-30 to -25   48%    12   ← Sharp drop
-25 to -20   58%    34
-20 to -15   67%    56
-15 to -10   72%    78
-10 to -5    76%    92
-5 to 0      79%    45
0 to 5       82%    30
```

**Insight:** Performance drops sharply below -20°C. Cold start issues, bus breakdowns, frozen doors all contribute.

---

### 3. Bunching Correlation

**Question:** "Does weather cause more bunching?"

**Query:**
```sql
SELECT
  w.weather_condition,
  COUNT(*) as incidents,
  AVG(b.gap_after_sec) as avg_gap
FROM bunching_incident b
JOIN weather_observation w ON b.occurred_at BETWEEN w.observed_at AND w.observed_at + INTERVAL '1 hour'
GROUP BY w.weather_condition
ORDER BY incidents DESC;
```

**Result:**
```
Condition  Incidents  Avg Gap
snow       87         1240s   ← 3x more bunching
rain       32         980s
cloudy     28         720s
clear      24         680s
```

**Insight:** Snow causes 3x more bunching. Slower speeds → vehicles catch up. Deploy supervisors on snowy days.

---

## Rider-Facing Features

### 1. Smart Alerts

> "❄️ Snow alert: Route 27 typically 30% worse in snow. Consider Route 14 (only 8% worse)."

### 2. Weather-Adjusted ETAs

```
Route 27 to University
  Normal ETA: 15 min
  Snow-Adjusted: 22 min (+7 min typical snow delay)
```

### 3. Condition-Based Recommendations

```
Current: Snow, -18°C
Your usual route (27): D- in snow
Better option: Route 14 (B in snow)
  Same destination, 8 min walk to different stop
```

---

## Advocacy Use Cases

### City Council Presentation

**Slide 1: System Performance Misleading**
> "City claims 90% on-time, but that's only 30% of the year (clear days). During winter conditions (40% of days), it's 62%."

**Slide 2: Route Inequity**
> "Route 27 serves lower-income neighborhoods, drops to 45% on-time in snow. Route 14 serves affluent area, maintains 84%. Disproportionate impact."

**Slide 3: Operational Gaps**
> "Bunching increases 300% during snow. Why no supervisor deployment strategy?"

---

## Implementation Roadmap

### Week 1: Schema & Data Collection
- [ ] Create `weather_observation` table
- [ ] Implement `CollectWeatherCommand`
- [ ] Add cron job for hourly collection
- [ ] Test with 48 hours of data

### Week 2: Backfill & Integration
- [ ] Backfill 90 days of historical weather
- [ ] Add foreign keys to performance tables
- [ ] Run daily collector to link weather + performance

### Week 3: Analysis & API
- [ ] Write weather analysis queries
- [ ] Build API endpoints (current weather, performance-by-weather)
- [ ] Test with frontend prototypes

### Week 4: Dashboard Features
- [ ] Weather badges on route cards
- [ ] Performance-by-weather charts
- [ ] Weather-adjusted recommendations

---

## Cost Analysis

**Open-Meteo (Recommended):**
- Free tier: 10,000 requests/day
- Our usage: ~24 requests/day (hourly) + ~1 backfill request
- Cost: $0/month ✅

**Environment Canada:**
- Free forever
- No rate limits
- Cost: $0/month ✅

**OpenWeatherMap:**
- Historical data: $40-150/month
- Not necessary with Open-Meteo historical API
- Cost: $0/month (don't use) ✅

**Total:** $0/month

---

## Privacy & Legal

- Weather data is public domain (no personal information)
- Environment Canada: Open Government License
- Open-Meteo: CC BY 4.0 (attribution required)

**Attribution:**
```
Weather data provided by Open-Meteo.com and Environment Canada
```

---

## Questions?

1. **Should we collect forecasts or just current conditions?**
   - Current: Just current (hourly)
   - Future: Add 24hr forecast for proactive alerts

2. **How often to collect?**
   - Current: Hourly (sufficient for daily analysis)
   - Could increase to every 30 min if needed

3. **What about other factors (traffic, events)?**
   - Phase 1: Weather only
   - Phase 2: Add traffic data (Google Maps API, Waze)
   - Phase 3: Event calendar (concerts, sports games)

---

*Ready to implement? Start with weather collection command and backfill historical data.*
