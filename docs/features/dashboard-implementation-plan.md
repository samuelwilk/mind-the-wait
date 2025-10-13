# Public Dashboard - Incremental Implementation Plan

## 🎯 Vision

A public-facing, story-driven transit transparency dashboard for Saskatoon using **Symfony UX** (Live Components, Turbo, Stimulus), **Tailwind CSS**, **Flowbite**, and **Apache ECharts** to surface insights about how weather, time, and other factors impact service quality.

**Audience:** Saskatoon residents, journalists, city officials, transit agencies, researchers

**Goal:** Tell compelling stories with data to educate and empower transit users

---

## 🏗️ Technology Stack

### Backend
- Symfony 7.3 (existing)
- Twig templates
- Doctrine ORM
- Symfony UX Turbo (page transitions)
- Symfony UX Live Components (real-time updates)

### Frontend
- **Asset Mapper** (no webpack/build step)
- **Stimulus** (JS controllers)
- **Turbo** (smooth navigation, live updates)
- **Tailwind CSS 3.x** (utility-first styling)
- **Flowbite** (component library)
- **Apache ECharts** (powerful charts)

### Theme System
- CSS custom properties for easy theme switching
- Tailwind config with semantic color tokens
- Designed for future dark mode

---

## 📐 Site Structure

```
┌──────────────────────────────────────────────────────────┐
│  Mind the Wait - Saskatoon Transit Insights              │
├──────────────────────────────────────────────────────────┤
│  🏠 Overview  📈 Routes  ❄️ Weather  📊 Analysis  ⚡ Live │
└──────────────────────────────────────────────────────────┘

Page Mapping:
/                   → Overview Dashboard
/routes             → Route List
/route/{id}         → Route Detail
/weather-impact     → Weather Impact Analysis (YOUR INSIGHTS!)
/analysis           → Historical Analysis & Reports
/live               → Live Monitor
```

---

## 🎨 Design System

### Color Palette

```javascript
// tailwind.config.js
{
  colors: {
    // Brand colors
    primary: {
      50: '#f0f9ff',
      500: '#0284c7',  // Primary blue
      900: '#0c4a6e',
    },

    // Status colors (transit performance)
    grade: {
      a: '#10b981',  // Green - excellent
      b: '#84cc16',  // Lime - good
      c: '#f59e0b',  // Amber - fair
      d: '#f97316',  // Orange - poor
      f: '#ef4444',  // Red - failing
    },

    // Transit impact (weather)
    impact: {
      none: '#10b981',      // Green
      minor: '#fbbf24',     // Yellow
      moderate: '#f97316',  // Orange
      severe: '#dc2626',    // Red
    },

    // Weather conditions (backgrounds)
    weather: {
      clear: '#fef3c7',      // Light yellow
      cloudy: '#e5e7eb',     // Gray
      rain: '#dbeafe',       // Light blue
      snow: '#ede9fe',       // Light purple
      thunderstorm: '#1e293b', // Dark slate
    }
  }
}
```

### Typography
- **Headings:** Poppins (display font)
- **Body:** Inter (clean, readable)
- **Mono:** JetBrains Mono (code/data)

### Components (Flow

bite)
- Cards, Badges, Buttons, Modals
- Dropdowns, Tables, Alerts
- Tabs, Tooltips, Progress bars

---

##  📊 ECharts Integration

### Installation

```bash
# Via Asset Mapper
php bin/console importmap:require apache-echarts
```

### Stimulus Controller Pattern

```javascript
// assets/controllers/chart_controller.js
import { Controller } from '@hotwired/stimulus';
import * as echarts from 'apache-echarts';

export default class extends Controller {
  static values = {
    options: Object,
    theme: String
  }

  connect() {
    this.chart = echarts.init(this.element, this.themeValue || 'light');
    this.chart.setOption(this.optionsValue);

    // Responsive resize
    window.addEventListener('resize', () => this.chart.resize());
  }

  disconnect() {
    this.chart.dispose();
  }
}
```

### Twig Usage

```twig
<div
  data-controller="chart"
  data-chart-options-value="{{ chartOptions|json_encode }}"
  data-chart-theme-value="mind-the-wait"
  style="width: 100%; height: 400px;">
</div>
```

### Custom Theme

```javascript
// Custom ECharts theme matching Tailwind
const mindTheWaitTheme = {
  color: ['#0284c7', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
  backgroundColor: '#ffffff',
  textStyle: {
    fontFamily: 'Inter, sans-serif',
    fontSize: 14
  },
  legend: {
    textStyle: { color: '#374151' }
  },
  // ... full theme config
};

echarts.registerTheme('mind-the-wait', mindTheWaitTheme);
```

---

## 📄 Page Specifications

### 1. Overview Dashboard (`/`)

**Controller:** `DashboardController::overview()`

**Purpose:** Landing page with current status + featured insights

#### Sections

##### Live Weather Banner (Live Component)
```twig
<twig:LiveWeatherBanner />
```
- Current temperature, condition, impact level
- Auto-updates every 60 seconds via Turbo Streams
- Color-coded background based on transit impact

##### Key Metrics Cards (Grid)
```
┌─────────────┬─────────────┬─────────────┐
│  Today      │  This Week  │  This Month │
│  87%        │  82%        │  79%        │
│  On-Time    │  On-Time    │  On-Time    │
│  ▲ 3%       │  ▼ 2%       │  ─ 0%       │
└─────────────┴─────────────┴─────────────┘
```
- Trend indicators (up/down/flat)
- Click to expand details

##### Featured Insights Section ⭐
```
┌───────────────────────────────────────────────────┐
│  💡 Featured Insights                             │
├───────────────────────────────────────────────────┤
│  ❄️ Winter Operations Report                     │
│  Route 27 drops 33% in snow vs clear conditions  │
│  [View Full Analysis →]                           │
├───────────────────────────────────────────────────┤
│  🌡️ Cold Weather Threshold                       │
│  Sharp performance drop below -20°C               │
│  [View Full Analysis →]                           │
├───────────────────────────────────────────────────┤
│  🚌 Bunching by Weather                           │
│  Snow causes 3x more bunching incidents           │
│  [View Full Analysis →]                           │
└───────────────────────────────────────────────────┘
```
- Auto-generated from `InsightGeneratorService`
- Curated, story-driven cards
- Click → Weather Impact page with full charts

##### Route Performance Overview
```
┌────────────────────────────────────────────────────┐
│  Route Performance Today                           │
├────────────────────────────────────────────────────┤
│  Route 1  ████████░░ 83%  🟢 Normal               │
│  Route 2  ████████░░ 85%  🟢 Normal               │
│  Route 27 ████░░░░░░ 45%  🔴 Weather Impact       │
│  Route 43 ██████░░░░ 62%  🟡 Delays               │
│  [View All Routes →]                               │
└────────────────────────────────────────────────────┘
```

---

### 2. Route Pages (`/routes`, `/route/{id}`)

**Controllers:** `RouteController::list()`, `RouteController::show()`

#### Route List (`/routes`)
- Search/filter/sort routes
- Performance cards with grade badges
- Weather impact indicators

#### Route Detail (`/route/{id}`)

##### Performance Trend Chart (ECharts Line)
```javascript
{
  type: 'line',
  data: {
    dates: ['2025-10-01', ...],
    onTimePercentage: [78, 72, 68, ...]
  },
  // Overlay weather conditions as background colors
  visualMap: {
    pieces: [
      {value: 'clear', color: '#fef3c7'},
      {value: 'snow', color: '#ede9fe'}
    ]
  }
}
```

##### Weather Impact Comparison (ECharts Horizontal Bar)
```javascript
{
  type: 'bar',
  data: [
    {condition: 'Clear', value: 78, label: '78%'},
    {condition: 'Cloudy', value: 76},
    {condition: 'Rain', value: 68},
    {condition: 'Snow', value: 45, emphasis: true}  // Highlight worst
  ],
  // Show this route vs system average
  series: [
    {name: 'Route 27', data: [78, 76, 68, 45]},
    {name: 'System Avg', data: [82, 80, 75, 70], itemStyle: {opacity: 0.4}}
  ]
}
```

##### Time-of-Day Heatmap (ECharts)
```javascript
{
  type: 'heatmap',
  xAxis: {data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']},
  yAxis: {data: ['0-6', '6-9', '9-12', '12-15', '15-18', '18-21', '21-24']},
  visualMap: {
    min: 40,
    max: 100,
    inRange: {
      color: ['#dc2626', '#f97316', '#fbbf24', '#84cc16', '#10b981']
    }
  }
}
```

---

### 3. Weather Impact Analysis (`/weather-impact`) ⭐⭐⭐

**Controller:** `WeatherImpactController::index()`

**Purpose:** THIS IS WHERE YOUR EXCITING INSIGHTS LIVE!

#### Current Weather Banner (Live Component)
```twig
<twig:LiveWeatherBanner detailed="true" />
```
```
Current: -12°C, Light Snow • Transit Impact: MINOR
System-wide: 72% on-time (typical for these conditions: 70-75%)
```

#### 1. Winter Operations Report (ECharts Grouped Bar)

**SQL Query:**
```sql
SELECT
  r.short_name,
  AVG(CASE WHEN w.weather_condition = 'clear' THEN p.on_time_percentage END) as clear_on_time,
  AVG(CASE WHEN w.weather_condition = 'snow' THEN p.on_time_percentage END) as snow_on_time
FROM route_performance_daily p
JOIN route r ON p.route_id = r.id
JOIN weather_observation w ON DATE(w.observed_at) = p.date
WHERE w.weather_condition IN ('clear', 'snow')
GROUP BY r.id, r.short_name
ORDER BY (clear_on_time - snow_on_time) DESC
LIMIT 10;
```

**Visualization:**
```javascript
{
  type: 'bar',
  xAxis: {type: 'category', data: ['Route 27', 'Route 43', 'Route 14']},
  yAxis: {type: 'value', name: 'On-Time %', max: 100},
  series: [
    {
      name: 'Clear Weather',
      data: [78, 72, 92],
      itemStyle: {color: '#fef3c7'}
    },
    {
      name: 'Snow',
      data: [45, 48, 84],
      itemStyle: {color: '#ede9fe'},
      label: {
        show: true,
        formatter: '{c}% (-{@diff}%)',  // Show delta
        position: 'right'
      }
    }
  ]
}
```

**Story Card:**
```
┌────────────────────────────────────────────────────┐
│  ❄️ Winter Operations Report                      │
├────────────────────────────────────────────────────┤
│  Route 27 performance drops 33% in snow           │
│  vs clear conditions.                              │
│                                                     │
│  Why? Route 27 travels Silverspring area with:    │
│  • Steep hills (8th Street)                       │
│  • High traffic volume                             │
│  • Limited snow storage                            │
│                                                     │
│  Route 14 only drops 8% - flatter terrain,        │
│  better snow clearing.                             │
└────────────────────────────────────────────────────┘
```

#### 2. Temperature Threshold Analysis (ECharts Scatter + Line)

**SQL Query:**
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

**Visualization:**
```javascript
{
  xAxis: {name: 'Temperature (°C)', min: -35, max: 35},
  yAxis: {name: 'On-Time %', max: 100},
  series: [
    {
      type: 'scatter',
      data: [[−30, 48], [−25, 58], [−20, 67], ...],
      symbolSize: (val) => val[1] / 2  // Size by observation count
    },
    {
      type: 'line',
      data: [...],  // Trend line
      lineStyle: {type: 'dashed'}
    }
  ],
  // Annotate the -20°C threshold
  markLine: {
    data: [{xAxis: -20, label: 'Sharp drop', lineStyle: {color: '#dc2626'}}]
  }
}
```

**Story Card:**
```
┌────────────────────────────────────────────────────┐
│  🌡️ Cold Weather Threshold                        │
├────────────────────────────────────────────────────┤
│  Performance drops sharply below -20°C:            │
│                                                     │
│  Above -20°C: 72-79% on-time                      │
│  Below -20°C: 48-58% on-time                      │
│                                                     │
│  Contributing factors:                             │
│  • Bus cold-start failures                        │
│  • Frozen doors/windows                            │
│  • Reduced battery performance                     │
│  • Driver shortages (illness)                      │
│                                                     │
│  City should pre-position extra buses on           │
│  forecasted cold days.                             │
└────────────────────────────────────────────────────┘
```

#### 3. Weather Impact Matrix (ECharts Heatmap)

**SQL Query:**
```sql
SELECT
  r.short_name,
  w.weather_condition,
  AVG(p.on_time_percentage) as avg_performance
FROM route_performance_daily p
JOIN route r ON p.route_id = r.id
JOIN weather_observation w ON DATE(w.observed_at) = p.date
GROUP BY r.id, r.short_name, w.weather_condition;
```

**Visualization:**
```javascript
{
  type: 'heatmap',
  xAxis: {data: ['Clear', 'Cloudy', 'Rain', 'Snow', 'Storm']},
  yAxis: {data: ['Route 1', 'Route 2', ..., 'Route 43']},
  visualMap: {
    min: 40,
    max: 100,
    calculable: true,
    inRange: {
      color: ['#dc2626', '#f97316', '#fbbf24', '#84cc16', '#10b981']
    }
  },
  series: [{
    type: 'heatmap',
    data: [[0, 0, 92], [0, 1, 89], ...]  // [x, y, value]
  }]
}
```

**Insight:** Instantly see which routes are most vulnerable to which weather conditions

#### 4. Bunching by Weather (ECharts Bar)

**SQL Query:**
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

**Visualization:**
```javascript
{
  type: 'bar',
  xAxis: {data: ['Snow', 'Rain', 'Cloudy', 'Clear']},
  yAxis: {name: 'Bunching Incidents'},
  series: [{
    data: [
      {value: 87, itemStyle: {color: '#ede9fe'}},  // Snow
      {value: 32, itemStyle: {color: '#dbeafe'}},  // Rain
      {value: 28},
      {value: 24}
    ],
    label: {
      show: true,
      formatter: '{c} incidents'
    }
  }]
}
```

**Story Card:**
```
┌────────────────────────────────────────────────────┐
│  🚌 Bunching by Weather                            │
├────────────────────────────────────────────────────┤
│  Snow causes 3x more bunching than clear weather   │
│                                                     │
│  Snow:   87 incidents (3.6/day)                   │
│  Rain:   32 incidents (1.3/day)                   │
│  Clear:  24 incidents (1.0/day)                   │
│                                                     │
│  Why? Slower speeds → vehicles catch up            │
│                                                     │
│  Recommendation: Deploy supervisors on snowy       │
│  days to actively manage spacing.                  │
└────────────────────────────────────────────────────┘
```

---

### 4. Historical Analysis (`/analysis`)

**Controller:** `AnalysisController::index()`

#### Date Range Selector (Live Component)
```twig
<twig:LiveDateRangeSelector />
```
- Presets: Last 7/30/90 days, Custom
- Updates charts via Turbo Frames

#### Performance Trends (ECharts Multi-Line)
- Monthly on-time percentage trend
- Confidence distribution (stacked area)
- Delay distribution (histogram)

#### Route Comparison Tool
```
Select routes to compare:
[Route 1 ▾] [Route 27 ▾] [+ Add route]

[Multi-line chart showing performance over time]
```

#### Pre-built Reports
- Winter Performance Summary (Dec-Mar)
- Summer Performance Summary (Jun-Aug)
- Peak Hour Analysis
- Weekend vs Weekday Comparison
- Route Reliability Rankings

**Export:** PDF, CSV, JSON

---

### 5. Live Monitor (`/live`)

**Controller:** `LiveMonitorController::index()`

#### Live System Status (Live Component, updates every 10s)
```twig
<twig:LiveSystemStatus />
```

#### Live Route Scores Table (Live Component)
```
Route  Headway Score  Status
1      A  (95%)       🟢 Excellent
27     D  (52%)       🔴 Delayed (Weather)
43     C+ (78%)       🟡 Fair
```
- Updates via Turbo Streams
- Color-coded rows

#### Activity Stream (Live Component)
```twig
<twig:LiveActivityFeed />
```
- Auto-scrolling feed of events
- Bunching detected, weather updates, delays

---

## 🚀 Implementation Phases

### Phase 1: Foundation & Theme (Week 1)

**Goal:** Set up frontend stack with basic layout

```bash
# Install dependencies
composer require symfony/ux-turbo symfony/ux-live-component
composer require symfony/stimulus-bundle
php bin/console importmap:require tailwindcss flowbite apache-echarts
```

**Tasks:**
- [ ] Install Tailwind CSS via Asset Mapper
- [ ] Configure Tailwind with custom theme
- [ ] Install Flowbite components
- [ ] Install Apache ECharts
- [ ] Create base template (`base.html.twig`)
- [ ] Set up navigation component
- [ ] Create reusable card components
- [ ] Set up ECharts Stimulus controller
- [ ] Register custom ECharts theme

**Files Created:**
```
assets/
  app.js
  styles/
    app.css           # Tailwind imports
    theme.css         # Custom CSS variables
  controllers/
    chart_controller.js

templates/
  base.html.twig      # Main layout
  components/
    navbar.html.twig

tailwind.config.js
config/packages/live_components.yaml
```

**Deliverable:** Static homepage with styled layout (no data yet)

---

### Phase 2: Overview Dashboard (Week 2)

**Goal:** Build landing page with live components

**Tasks:**
- [ ] Create `DashboardController`
- [ ] Create `OverviewService` (system metrics query)
- [ ] Build LiveWeatherBanner component
- [ ] Build metric cards component
- [ ] Create featured insights section (static first)
- [ ] Add route performance bar chart
- [ ] Set up Turbo Streams for live updates
- [ ] Make it responsive (mobile/tablet/desktop)

**Files Created:**
```
src/
  Controller/
    DashboardController.php
  Service/
    Dashboard/
      OverviewService.php
  Twig/
    Components/
      LiveWeatherBanner.php
      MetricCard.php
      InsightCard.php
  Dto/
    SystemMetricsDto.php

templates/
  dashboard/
    overview.html.twig
  components/
    LiveWeatherBanner.html.twig
    MetricCard.html.twig
    InsightCard.html.twig
```

**Deliverable:** Live, updating Overview dashboard

---

### Phase 3: Route Pages ✅ **COMPLETE** (Week 3)

**Goal:** Route list + detail pages with first ECharts

**Tasks:**
- [x] Create `RouteController`
- [x] Create `RoutePerformanceService`
- [x] Build route list page with filters and search (Live Component)
- [x] Build route detail page layout
- [x] Implement 30-day performance trend chart (ECharts line)
- [x] Add weather overlay to performance chart
- [x] Create weather impact comparison chart (ECharts bar)
- [x] Add time-of-day heatmap (ECharts)
- [x] Make charts responsive
- [x] Add summary statistics cards

**Files Created:**
```
src/
  Controller/
    DashboardController.php (routes list, route detail)
  Service/
    Dashboard/
      RoutePerformanceService.php
  Dto/
    RouteDetailDto.php
    RouteMetricDto.php
  Twig/
    Components/
      RouteSearch.php (Live Component)

templates/
  dashboard/
    routes_list.html.twig
    route_detail.html.twig
  components/
    RouteSearch.html.twig
    RouteBadge.html.twig
    RouteListItem.html.twig

assets/
  controllers/
    chart_controller.js
  themes/
    echarts-theme.js (custom Mind the Wait theme)
```

**Deliverable:** ✅ Complete route exploration experience with interactive charts

**Important Notes:**
- **ECharts + AssetMapper:** Use the full dist bundle (`echarts/dist/echarts.js`) instead of modular imports. AssetMapper cannot properly resolve ECharts' complex internal module dependencies.
- **Custom Theme:** Custom Tailwind-matched ECharts theme works perfectly once loaded correctly
- **Date Formatting:** Dates formatted as "Sep 14" instead of "2025-09-14" for better readability
- **Heatmap Formatters:** Tooltip formatters must be added in JavaScript (can't be serialized from PHP)

---

### Phase 4: Weather Impact Analysis ⭐ (Week 4)

**Goal:** YOUR EXCITING INSIGHTS PAGE!

**Tasks:**
- [ ] Create `WeatherImpactController`
- [ ] Create `WeatherAnalysisService` with SQL queries:
  - [ ] Winter Operations Report query
  - [ ] Temperature Threshold query
  - [ ] Weather Impact Matrix query
  - [ ] Bunching by Weather query
- [ ] Build LiveWeatherBanner (detailed version)
- [ ] Implement Winter Operations Report chart (grouped bar)
- [ ] Implement Temperature Threshold chart (scatter + line)
- [ ] Implement Weather Impact Matrix (heatmap)
- [ ] Implement Bunching by Weather chart (bar)
- [ ] Create story cards for each insight
- [ ] Add insight explanations and recommendations

**Files Created:**
```
src/
  Controller/
    WeatherImpactController.php
  Service/
    Dashboard/
      WeatherAnalysisService.php
  Dto/
    WeatherImpactDto.php

templates/
  dashboard/
    weather_impact.html.twig
  components/
    InsightStoryCard.html.twig
```

**Deliverable:** Complete Weather Impact page with all insights!

---

### Phase 5: Historical Analysis (Week 5)

**Goal:** Deep dive analysis tools

**Tasks:**
- [ ] Create `AnalysisController`
- [ ] Build date range selector (Live Component)
- [ ] Implement performance trends charts
- [ ] Create route comparison tool
- [ ] Build pre-built reports section
- [ ] Add export functionality (PDF/CSV)
- [ ] Create InsightGeneratorService for auto-insights

**Files Created:**
```
src/
  Controller/
    AnalysisController.php
  Service/
    Dashboard/
      InsightGeneratorService.php
  Twig/
    Components/
      LiveDateRangeSelector.php

templates/
  dashboard/
    analysis.html.twig
```

**Deliverable:** Full analysis capabilities with reports

---

### Phase 6: Live Monitor (Week 6)

**Goal:** Real-time monitoring page

**Tasks:**
- [ ] Create `LiveMonitorController`
- [ ] Create `LiveDataService`
- [ ] Build LiveSystemStatus component
- [ ] Build LiveActivityFeed component
- [ ] Create live route scores table (Live Component)
- [ ] Add gauge charts for system health
- [ ] Optimize for frequent Turbo Stream updates

**Files Created:**
```
src/
  Controller/
    LiveMonitorController.php
  Service/
    Dashboard/
      LiveDataService.php
  Twig/
    Components/
      LiveSystemStatus.php
      LiveActivityFeed.php
      LiveRouteScores.php

templates/
  dashboard/
    live_monitor.html.twig
```

**Deliverable:** Complete live monitoring experience

---

### Phase 7: Polish & Launch (Week 7+)

**Goal:** Production-ready dashboard

**Tasks:**
- [ ] Performance optimization (lazy loading, caching)
- [ ] Accessibility audit (WCAG 2.1 AA)
- [ ] Mobile UX refinement
- [ ] Add dark mode support
- [ ] SEO optimization
- [ ] Analytics integration (privacy-respecting)
- [ ] User feedback mechanism
- [ ] Documentation (user guide)
- [ ] Deployment (production)

**Deliverable:** Public launch! 🎉

---

## 📁 Final File Structure

```
src/
├── Controller/
│   ├── DashboardController.php
│   ├── RouteController.php
│   ├── WeatherImpactController.php
│   ├── AnalysisController.php
│   └── LiveMonitorController.php
├── Service/
│   └── Dashboard/
│       ├── OverviewService.php
│       ├── RoutePerformanceService.php
│       ├── WeatherAnalysisService.php        # ⭐ Your SQL queries!
│       ├── InsightGeneratorService.php
│       └── LiveDataService.php
├── Twig/
│   └── Components/
│       ├── LiveWeatherBanner.php
│       ├── LiveSystemStatus.php
│       ├── LiveActivityFeed.php
│       ├── LiveDateRangeSelector.php
│       ├── MetricCard.php
│       ├── InsightCard.php
│       └── InsightStoryCard.php
└── Dto/
    ├── SystemMetricsDto.php
    ├── RouteDetailDto.php
    └── WeatherImpactDto.php

templates/
├── base.html.twig
├── dashboard/
│   ├── overview.html.twig
│   ├── route_list.html.twig
│   ├── route_detail.html.twig
│   ├── weather_impact.html.twig              # ⭐ Your insights page!
│   ├── analysis.html.twig
│   └── live_monitor.html.twig
└── components/
    ├── navbar.html.twig
    ├── LiveWeatherBanner.html.twig
    ├── LiveSystemStatus.html.twig
    ├── LiveActivityFeed.html.twig
    ├── MetricCard.html.twig
    ├── InsightCard.html.twig
    └── InsightStoryCard.html.twig

assets/
├── app.js
├── styles/
│   ├── app.css
│   └── theme.css
└── controllers/
    ├── chart_controller.js
    ├── filter_controller.js
    └── live_update_controller.js

tailwind.config.js
```

---

## 🎯 Success Criteria

### Phase 4 Completion (Weather Impact Analysis)
✅ All 4 key insights visualized with ECharts:
- Winter Operations Report (grouped bar)
- Temperature Threshold (scatter + line)
- Weather Impact Matrix (heatmap)
- Bunching by Weather (bar)

✅ Story cards explaining each insight
✅ Responsive on all devices
✅ Charts interactive (tooltips, zoom)
✅ Page loads < 3s

---

## 🔧 Development Commands

```bash
# Watch Tailwind CSS
php bin/console tailwind:build --watch

# Start dev server
symfony serve

# Watch assets (if using watch mode)
php bin/console asset-map:compile --watch

# Clear cache
php bin/console cache:clear
```

---

## 📝 Next Steps

**Let's start with Phase 1!**

I'll help you:
1. Install and configure Tailwind CSS
2. Set up Asset Mapper with ECharts
3. Create the base template
4. Build the theme system

**Ready to begin Phase 1?** 🚀
