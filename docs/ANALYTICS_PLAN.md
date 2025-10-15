# Analytics Implementation Plan

## Overview

This document outlines three options for implementing visitor tracking and analytics for mind-the-wait. The goal is to understand:

- How many visitors use the application daily/monthly
- Which routes and features are most popular
- User behavior patterns (peak usage times, user journeys)
- Geographic distribution of users
- Device/browser breakdown

## Requirements

### Must Have
- Daily/monthly unique visitor counts
- Page views per route/feature
- Privacy-compliant (GDPR, CCPA)
- Low operational overhead
- Minimal performance impact

### Nice to Have
- Real-time metrics
- Custom event tracking (e.g., "Feedback Submitted", "Route Viewed")
- User session tracking
- Referrer tracking
- Geographic insights
- Device/browser analytics

---

## Options Comparison

| Feature | Option 1: ALB Logs + Athena | Option 2: Custom Tracking | Option 3: Google Analytics 4 |
|---------|----------------------------|---------------------------|------------------------------|
| **Cost** | ~$0.01-0.05/day | $0 (existing DB) | Free |
| **Setup Time** | 10 minutes | 30-60 minutes | 5 minutes |
| **Privacy** | ⚠️ Raw IPs stored | ✅ Hashed IPs only | ⚠️ Requires cookie consent |
| **Real-time** | ❌ 5-15 min delay | ✅ Immediate | ✅ Immediate |
| **Custom Events** | ❌ Limited | ✅ Full control | ✅ Full featured |
| **User Sessions** | ❌ No | ✅ Yes | ✅ Yes |
| **Geographic Data** | ⚠️ IP → Location | ❌ No (unless added) | ✅ Built-in |
| **Device/Browser** | ⚠️ User-Agent parsing | ⚠️ User-Agent parsing | ✅ Automatic |
| **Maintenance** | Low | Medium | Very Low |
| **Data Ownership** | ✅ Full | ✅ Full | ❌ Google's servers |
| **GDPR Compliance** | Manual | Built-in (hashed) | ⚠️ Requires consent banner |
| **Performance Impact** | None (async logs) | Minimal (1 INSERT/view) | Minimal (async script) |

---

## Option 1: ALB Access Logs + Amazon Athena

### Description
Use AWS Application Load Balancer's built-in access logs (stored in S3) and query them with Amazon Athena (serverless SQL).

### Architecture
```
User Request → ALB → ECS
                ↓
           S3 Bucket (logs)
                ↓
           Athena (query)
                ↓
         Dashboard/Reports
```

### Implementation Steps

#### 1. Enable ALB Access Logs
Update ALB configuration (CloudFormation/Terraform):
```yaml
LoadBalancer:
  AccessLogs:
    Enabled: true
    Bucket: !Ref LogsBucket
    Prefix: alb-logs
```

#### 2. Create S3 Bucket for Logs
```yaml
LogsBucket:
  Type: AWS::S3::Bucket
  Properties:
    BucketName: mind-the-wait-logs
    LifecycleConfiguration:
      Rules:
        - Id: DeleteOldLogs
          Status: Enabled
          ExpirationInDays: 90  # Keep 90 days of logs
```

#### 3. Create Athena Table
```sql
CREATE EXTERNAL TABLE alb_logs (
    type string,
    time string,
    elb string,
    client_ip string,
    client_port int,
    target_ip string,
    target_port int,
    request_processing_time double,
    target_processing_time double,
    response_processing_time double,
    elb_status_code string,
    target_status_code string,
    received_bytes bigint,
    sent_bytes bigint,
    request_verb string,
    request_url string,
    request_proto string,
    user_agent string,
    ssl_cipher string,
    ssl_protocol string
)
ROW FORMAT SERDE 'org.apache.hadoop.hive.serde2.RegexSerDe'
WITH SERDEPROPERTIES (
'serialization.format' = '1',
'input.regex' =
'([^ ]*) ([^ ]*) ([^ ]*) ([^ ]*):([0-9]*) ([^ ]*)[:-]([0-9]*) ([-.0-9]*) ([-.0-9]*) ([-.0-9]*) (|[-0-9]*) (-|[-0-9]*) ([-0-9]*) ([-0-9]*) \"([^ ]*) ([^ ]*) (- |[^ ]*)\" \"([^\"]*)\" ([A-Z0-9-]+) ([A-Za-z0-9.-]*)')
LOCATION 's3://mind-the-wait-logs/AWSLogs/ACCOUNT_ID/elasticloadbalancing/REGION/';
```

#### 4. Example Queries

**Daily Unique Visitors:**
```sql
SELECT
    DATE(from_iso8601_timestamp(time)) as date,
    COUNT(DISTINCT client_ip) as unique_visitors,
    COUNT(*) as total_requests
FROM alb_logs
WHERE from_iso8601_timestamp(time) >= current_date - interval '30' day
GROUP BY DATE(from_iso8601_timestamp(time))
ORDER BY date DESC;
```

**Most Popular Pages:**
```sql
SELECT
    request_url,
    COUNT(DISTINCT client_ip) as unique_visitors,
    COUNT(*) as page_views
FROM alb_logs
WHERE from_iso8601_timestamp(time) >= current_date - interval '7' day
    AND elb_status_code = '200'
    AND request_url NOT LIKE '%.css'
    AND request_url NOT LIKE '%.js'
    AND request_url NOT LIKE '%.png'
    AND request_url NOT LIKE '%.jpg'
GROUP BY request_url
ORDER BY page_views DESC
LIMIT 20;
```

**Traffic by Hour of Day:**
```sql
SELECT
    HOUR(from_iso8601_timestamp(time)) as hour,
    COUNT(DISTINCT client_ip) as unique_visitors,
    COUNT(*) as requests
FROM alb_logs
WHERE from_iso8601_timestamp(time) >= current_date - interval '7' day
GROUP BY HOUR(from_iso8601_timestamp(time))
ORDER BY hour;
```

**Top User Agents (Browser/Device):**
```sql
SELECT
    user_agent,
    COUNT(*) as requests
FROM alb_logs
WHERE from_iso8601_timestamp(time) >= current_date - interval '7' day
GROUP BY user_agent
ORDER BY requests DESC
LIMIT 10;
```

#### 5. Create QuickSight Dashboard (Optional)
Use AWS QuickSight to create visual dashboards from Athena queries.

**Cost:** $9/user/month for QuickSight Standard

### Pros
- ✅ No application code changes required
- ✅ AWS-native solution (no new services)
- ✅ Captures all HTTP traffic (including bots)
- ✅ Can analyze errors, performance, and traffic patterns
- ✅ Historical data preserved in S3

### Cons
- ❌ Not real-time (5-15 minute delay)
- ❌ Stores raw IP addresses (privacy concern)
- ❌ Requires SQL knowledge to extract insights
- ❌ Cannot track custom events (e.g., "Feedback Submitted")
- ❌ No user session concept
- ❌ Includes bot traffic (requires filtering)

### Cost Analysis
- **S3 Storage:** $0.023/GB/month (~$0.50/month for 1M requests)
- **Athena Queries:** $5/TB scanned (~$0.01-0.05/day)
- **Total:** ~$1-2/month

---

## Option 2: Custom Tracking in PostgreSQL

### Description
Add a lightweight tracking system to the existing PostgreSQL database that records page views with privacy-preserving hashed identifiers.

### Architecture
```
User Request → Symfony EventSubscriber
                      ↓
              PageViewTracker Service
                      ↓
              PostgreSQL (page_view table)
                      ↓
              Analytics Dashboard
```

### Implementation Steps

#### 1. Create Database Schema

**Migration:**
```bash
docker compose exec php bin/console make:migration
```

```php
// migrations/VersionXXXXXXXXXXXXXX.php
public function up(Schema $schema): void
{
    $this->addSql('
        CREATE TABLE page_view (
            id SERIAL PRIMARY KEY,
            path VARCHAR(255) NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            session_hash VARCHAR(64),
            user_agent_hash VARCHAR(64),
            referrer VARCHAR(255),
            viewed_at TIMESTAMP NOT NULL DEFAULT NOW(),
            INDEX idx_viewed_at (viewed_at),
            INDEX idx_path (path),
            INDEX idx_session_hash (session_hash)
        )
    ');

    // Optional: Add table for custom events
    $this->addSql('
        CREATE TABLE analytics_event (
            id SERIAL PRIMARY KEY,
            event_name VARCHAR(100) NOT NULL,
            properties JSONB,
            session_hash VARCHAR(64),
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            INDEX idx_event_name (event_name),
            INDEX idx_created_at (created_at)
        )
    ');
}
```

#### 2. Create PageViewTracker Service

```php
// src/Service/Analytics/PageViewTracker.php
<?php

namespace App\Service\Analytics;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class PageViewTracker
{
    private const SALT = 'your-random-secret-salt-change-this';

    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {}

    public function track(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $path = $request->getPathInfo();

        // Skip tracking for assets and API endpoints
        if ($this->shouldSkip($path)) {
            return;
        }

        // Hash IP for privacy (GDPR-compliant, cannot reverse)
        $ipHash = hash('sha256', $request->getClientIp() . self::SALT);

        // Track sessions for unique visitor calculation
        $sessionHash = $request->hasSession() && $request->getSession()->isStarted()
            ? hash('sha256', $request->getSession()->getId())
            : null;

        // Hash user agent
        $userAgentHash = hash('sha256', $request->headers->get('User-Agent', ''));

        // Use direct SQL for performance (avoid Doctrine overhead)
        $sql = 'INSERT INTO page_view (path, ip_hash, session_hash, user_agent_hash, referrer)
                VALUES (?, ?, ?, ?, ?)';

        try {
            $this->em->getConnection()->executeStatement($sql, [
                $path,
                $ipHash,
                $sessionHash,
                $userAgentHash,
                $request->headers->get('referer'),
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the application
        }
    }

    /**
     * Track custom events (e.g., "Feedback Submitted", "Route Viewed").
     */
    public function trackEvent(string $eventName, array $properties = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $sessionHash = $request->hasSession() && $request->getSession()->isStarted()
            ? hash('sha256', $request->getSession()->getId())
            : null;

        $sql = 'INSERT INTO analytics_event (event_name, properties, session_hash)
                VALUES (?, ?, ?)';

        try {
            $this->em->getConnection()->executeStatement($sql, [
                $eventName,
                json_encode($properties),
                $sessionHash,
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    private function shouldSkip(string $path): bool
    {
        return str_starts_with($path, '/api/')
            || str_starts_with($path, '/_')
            || str_starts_with($path, '/admin/analytics') // Don't track analytics page itself
            || str_contains($path, '.css')
            || str_contains($path, '.js')
            || str_contains($path, '.png')
            || str_contains($path, '.jpg')
            || str_contains($path, '.svg')
            || str_contains($path, '.ico');
    }
}
```

#### 3. Create Event Subscriber

```php
// src/EventSubscriber/AnalyticsSubscriber.php
<?php

namespace App\EventSubscriber;

use App\Service\Analytics\PageViewTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PageViewTracker $tracker,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Low priority to run after response is ready
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Only track main requests (not sub-requests)
        if (!$event->isMainRequest()) {
            return;
        }

        // Only track successful page loads
        $statusCode = $event->getResponse()->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->tracker->track();
        }
    }
}
```

#### 4. Create Analytics Repository

```php
// src/Repository/PageViewRepository.php
<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class PageViewRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Get daily unique visitors and page views.
     *
     * @return array<array{date: string, unique_visitors: int, page_views: int}>
     */
    public function getDailyStats(int $days = 30): array
    {
        $sql = <<<SQL
            SELECT
                DATE(viewed_at) as date,
                COUNT(DISTINCT session_hash) as unique_visitors,
                COUNT(*) as page_views
            FROM page_view
            WHERE viewed_at >= NOW() - INTERVAL '$days days'
            GROUP BY DATE(viewed_at)
            ORDER BY date DESC
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Get most popular pages.
     *
     * @return array<array{path: string, unique_visitors: int, page_views: int}>
     */
    public function getTopPages(int $days = 7, int $limit = 20): array
    {
        $sql = <<<SQL
            SELECT
                path,
                COUNT(DISTINCT session_hash) as unique_visitors,
                COUNT(*) as page_views
            FROM page_view
            WHERE viewed_at >= NOW() - INTERVAL '$days days'
            GROUP BY path
            ORDER BY page_views DESC
            LIMIT $limit
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Get traffic by hour of day (0-23).
     *
     * @return array<array{hour: int, unique_visitors: int, page_views: int}>
     */
    public function getHourlyPattern(int $days = 7): array
    {
        $sql = <<<SQL
            SELECT
                EXTRACT(HOUR FROM viewed_at) as hour,
                COUNT(DISTINCT session_hash) as unique_visitors,
                COUNT(*) as page_views
            FROM page_view
            WHERE viewed_at >= NOW() - INTERVAL '$days days'
            GROUP BY EXTRACT(HOUR FROM viewed_at)
            ORDER BY hour
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Get traffic by day of week (1=Monday, 7=Sunday).
     *
     * @return array<array{day_of_week: int, day_name: string, unique_visitors: int, page_views: int}>
     */
    public function getWeekdayPattern(int $weeks = 4): array
    {
        $sql = <<<SQL
            SELECT
                EXTRACT(DOW FROM viewed_at) as day_of_week,
                CASE EXTRACT(DOW FROM viewed_at)
                    WHEN 0 THEN 'Sunday'
                    WHEN 1 THEN 'Monday'
                    WHEN 2 THEN 'Tuesday'
                    WHEN 3 THEN 'Wednesday'
                    WHEN 4 THEN 'Thursday'
                    WHEN 5 THEN 'Friday'
                    WHEN 6 THEN 'Saturday'
                END as day_name,
                COUNT(DISTINCT session_hash) as unique_visitors,
                COUNT(*) as page_views
            FROM page_view
            WHERE viewed_at >= NOW() - INTERVAL '$weeks weeks'
            GROUP BY EXTRACT(DOW FROM viewed_at)
            ORDER BY day_of_week
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Get referrer statistics.
     *
     * @return array<array{referrer: string, visitors: int}>
     */
    public function getTopReferrers(int $days = 30, int $limit = 10): array
    {
        $sql = <<<SQL
            SELECT
                COALESCE(referrer, 'Direct') as referrer,
                COUNT(DISTINCT session_hash) as visitors
            FROM page_view
            WHERE viewed_at >= NOW() - INTERVAL '$days days'
            GROUP BY referrer
            ORDER BY visitors DESC
            LIMIT $limit
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }

    /**
     * Get custom event statistics.
     *
     * @return array<array{event_name: string, event_count: int, unique_sessions: int}>
     */
    public function getEventStats(int $days = 30): array
    {
        $sql = <<<SQL
            SELECT
                event_name,
                COUNT(*) as event_count,
                COUNT(DISTINCT session_hash) as unique_sessions
            FROM analytics_event
            WHERE created_at >= NOW() - INTERVAL '$days days'
            GROUP BY event_name
            ORDER BY event_count DESC
        SQL;

        return $this->connection->fetchAllAssociative($sql);
    }
}
```

#### 5. Create Analytics Dashboard Controller

```php
// src/Controller/AnalyticsController.php
<?php

namespace App\Controller;

use App\Repository\PageViewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnalyticsController extends AbstractController
{
    #[Route('/admin/analytics', name: 'admin_analytics')]
    public function dashboard(PageViewRepository $repo): Response
    {
        return $this->render('analytics/dashboard.html.twig', [
            'daily_stats' => $repo->getDailyStats(30),
            'top_pages' => $repo->getTopPages(7, 20),
            'hourly_pattern' => $repo->getHourlyPattern(7),
            'weekday_pattern' => $repo->getWeekdayPattern(4),
            'top_referrers' => $repo->getTopReferrers(30, 10),
            'event_stats' => $repo->getEventStats(30),
        ]);
    }
}
```

#### 6. Create Dashboard Template

```twig
{# templates/analytics/dashboard.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Analytics Dashboard{% endblock %}

{% block body %}
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Analytics Dashboard</h1>

    {# Daily Stats Chart #}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Daily Traffic (Last 30 Days)</h2>
        <div id="daily-chart" style="height: 300px;"></div>
    </div>

    {# Top Pages #}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Most Popular Pages (Last 7 Days)</h2>
        <table class="w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Page</th>
                    <th class="text-right py-2">Unique Visitors</th>
                    <th class="text-right py-2">Page Views</th>
                </tr>
            </thead>
            <tbody>
                {% for page in top_pages %}
                <tr class="border-b">
                    <td class="py-2">{{ page.path }}</td>
                    <td class="text-right">{{ page.unique_visitors }}</td>
                    <td class="text-right">{{ page.page_views }}</td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>

    {# Hourly Pattern #}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Traffic by Hour of Day</h2>
        <div id="hourly-chart" style="height: 250px;"></div>
    </div>

    {# Weekday Pattern #}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Traffic by Day of Week</h2>
        <div id="weekday-chart" style="height: 250px;"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script>
    // Daily chart
    const dailyChart = echarts.init(document.getElementById('daily-chart'));
    dailyChart.setOption({
        xAxis: { type: 'category', data: {{ daily_stats|map(s => s.date)|json_encode|raw }} },
        yAxis: { type: 'value' },
        series: [
            { name: 'Unique Visitors', type: 'line', data: {{ daily_stats|map(s => s.unique_visitors)|json_encode|raw }} },
            { name: 'Page Views', type: 'line', data: {{ daily_stats|map(s => s.page_views)|json_encode|raw }} }
        ],
        legend: { data: ['Unique Visitors', 'Page Views'] }
    });

    // Hourly chart
    const hourlyChart = echarts.init(document.getElementById('hourly-chart'));
    hourlyChart.setOption({
        xAxis: { type: 'category', data: {{ hourly_pattern|map(h => h.hour ~ ':00')|json_encode|raw }} },
        yAxis: { type: 'value' },
        series: [{ name: 'Page Views', type: 'bar', data: {{ hourly_pattern|map(h => h.page_views)|json_encode|raw }} }]
    });

    // Weekday chart
    const weekdayChart = echarts.init(document.getElementById('weekday-chart'));
    weekdayChart.setOption({
        xAxis: { type: 'category', data: {{ weekday_pattern|map(w => w.day_name)|json_encode|raw }} },
        yAxis: { type: 'value' },
        series: [{ name: 'Page Views', type: 'bar', data: {{ weekday_pattern|map(w => w.page_views)|json_encode|raw }} }]
    });
</script>
{% endblock %}
```

#### 7. Track Custom Events (Optional)

**Example: Track when users submit feedback**
```php
// In VehicleFeedbackController.php
public function submit(Request $request, PageViewTracker $tracker): Response
{
    // ... existing feedback logic ...

    $tracker->trackEvent('Feedback Submitted', [
        'vote' => $vote,
        'route_id' => $vehicleDto->routeId ?? null,
    ]);

    return $this->json(['success' => true]);
}
```

**Example: Track route views**
```php
// In RouteController.php
public function show(Route $route, PageViewTracker $tracker): Response
{
    $tracker->trackEvent('Route Viewed', [
        'route_id' => $route->getGtfsId(),
        'route_name' => $route->getShortName(),
    ]);

    return $this->render('route/show.html.twig', ['route' => $route]);
}
```

#### 8. Data Cleanup (Optional but Recommended)

Create a command to clean up old analytics data:

```php
// src/Command/CleanupAnalyticsCommand.php
<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup:analytics',
    description: 'Remove analytics data older than 90 days',
)]
final class CleanupAnalyticsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->connection->executeStatement(
            "DELETE FROM page_view WHERE viewed_at < NOW() - INTERVAL '90 days'"
        );

        $output->writeln("Deleted $deleted old page view records");

        $deletedEvents = $this->connection->executeStatement(
            "DELETE FROM analytics_event WHERE created_at < NOW() - INTERVAL '90 days'"
        );

        $output->writeln("Deleted $deletedEvents old event records");

        return Command::SUCCESS;
    }
}
```

**Schedule cleanup weekly via cron or ECS scheduled task:**
```bash
0 2 * * 0 cd /var/www/app && php bin/console app:cleanup:analytics
```

### Pros
- ✅ Full control over data and privacy
- ✅ Zero additional cost (uses existing infrastructure)
- ✅ Real-time analytics
- ✅ Custom event tracking for transit-specific insights
- ✅ GDPR-compliant (hashed IPs, no PII)
- ✅ Can track user sessions and journeys
- ✅ Easy to extend with new metrics

### Cons
- ❌ Requires application code changes
- ❌ Adds 1 INSERT per page view (minimal performance impact)
- ❌ No geographic data (unless you add IP → Location lookup)
- ❌ No automatic device/browser breakdown (requires User-Agent parsing)
- ❌ Requires building your own dashboard

### Cost Analysis
- **Database Storage:** ~1-2 MB/day for 1000 visitors (~$0.01/month)
- **Total:** $0 (uses existing PostgreSQL)

### Performance Optimization

**Option A: Async Tracking with Messenger**
```php
// Dispatch tracking to background worker
$this->messageBus->dispatch(new TrackPageViewMessage(...));
```

**Option B: Batch Inserts**
```php
// Buffer page views and insert every 10 requests
```

---

## Option 3: Google Analytics 4 (GA4)

### Description
Use Google's free analytics platform for comprehensive visitor tracking and insights.

### Architecture
```
User Browser → GA4 Script → Google Servers
                                  ↓
                          GA4 Dashboard (Web UI)
```

### Implementation Steps

#### 1. Create GA4 Property
1. Go to https://analytics.google.com/
2. Click "Admin" → "Create Property"
3. Enter property name: "mind-the-wait"
4. Configure timezone and currency
5. Accept terms of service

#### 2. Get Measurement ID
After creating property:
1. Go to "Admin" → "Data Streams"
2. Click "Add stream" → "Web"
3. Enter URL: `https://mindthewait.ca`
4. Get your Measurement ID (format: `G-XXXXXXXXXX`)

#### 3. Add GA4 Script to Base Template

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html>
<head>
    {# ... existing head content ... #}

    {% if app.environment == 'prod' %}
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-XXXXXXXXXX', {
            // Privacy settings
            anonymize_ip: true,
            allow_google_signals: false,
            allow_ad_personalization_signals: false
        });
    </script>
    {% endif %}
</head>
<body>
    {# ... existing body content ... #}
</body>
</html>
```

#### 4. Configure Cookie Consent Banner (GDPR Requirement)

**Option A: Use Cookiebot (Free for small sites)**
```html
<script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="YOUR-ID" type="text/javascript" async></script>
```

**Option B: Simple custom banner**
```twig
{# templates/components/cookie_banner.html.twig #}
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-gray-900 text-white p-4 z-50" style="display: none;">
    <div class="container mx-auto flex items-center justify-between">
        <p class="text-sm">
            We use cookies to improve your experience. By using this site, you accept our
            <a href="/privacy" class="underline">privacy policy</a>.
        </p>
        <button id="accept-cookies" class="bg-blue-600 px-4 py-2 rounded">Accept</button>
    </div>
</div>

<script>
    // Check if user has already accepted
    if (!localStorage.getItem('cookies-accepted')) {
        document.getElementById('cookie-banner').style.display = 'block';
    }

    document.getElementById('accept-cookies').addEventListener('click', function() {
        localStorage.setItem('cookies-accepted', 'true');
        document.getElementById('cookie-banner').style.display = 'none';

        // Load GA4 only after consent
        gtag('consent', 'update', {
            'analytics_storage': 'granted'
        });
    });
</script>
```

#### 5. Track Custom Events

**Route View Event:**
```twig
{# templates/route/show.html.twig #}
<script>
    gtag('event', 'view_route', {
        'route_id': '{{ route.gtfsId }}',
        'route_name': '{{ route.shortName }}',
        'grade': '{{ grade }}'
    });
</script>
```

**Feedback Submission Event:**
```javascript
// When user submits feedback
gtag('event', 'feedback_submitted', {
    'vote': 'late',
    'route_id': '14'
});
```

**Weather Impact Page View:**
```twig
{# templates/weather/impact.html.twig #}
<script>
    gtag('event', 'view_weather_impact');
</script>
```

#### 6. Configure GA4 Dashboard

**Recommended Reports to Enable:**
1. **Realtime** → See live visitors
2. **Engagement** → Pages and screens
3. **Acquisition** → How users found your site
4. **Demographics** → Age, gender, interests
5. **Tech** → Browser, OS, device type
6. **Geography** → Where users are located

**Create Custom Reports:**
1. Go to "Explore" → "Create new exploration"
2. Add dimensions: Page path, Event name, City
3. Add metrics: Users, Sessions, Page views
4. Save as "Transit Route Analytics"

#### 7. Set Up Conversions (Optional)

Define key actions as conversions:
1. Go to "Admin" → "Events" → "Mark as conversion"
2. Select events:
   - `feedback_submitted`
   - `view_route`
   - `view_weather_impact`

### Pros
- ✅ Free and feature-rich
- ✅ Easy setup (5 minutes)
- ✅ Real-time analytics
- ✅ Automatic geographic, device, and browser tracking
- ✅ User demographics and interests
- ✅ Integration with Google Search Console
- ✅ Predictive metrics (likely purchases, churn prediction)
- ✅ Professional reports and visualizations
- ✅ Mobile app available

### Cons
- ❌ Requires cookie consent banner (GDPR/CCPA)
- ❌ Data stored on Google's servers (privacy concern)
- ❌ Some users block GA with ad blockers (~15-30% of traffic)
- ❌ Learning curve for GA4 interface
- ❌ Less control over data retention and privacy
- ❌ Requires separate privacy policy updates

### Cost Analysis
- **GA4:** Free (up to 10M events/month)
- **Cookie Consent Banner:** Free (Cookiebot) or $9/month (premium)
- **Total:** $0-9/month

### Privacy Compliance

**Required Updates:**

1. **Privacy Policy** - Add section about GA4:
```markdown
## Analytics

We use Google Analytics to understand how visitors use our site.
Google Analytics collects information such as:
- Pages you visit
- Time spent on pages
- Browser and device type
- Geographic location (city/country)

This data is anonymous and used only to improve our service.
You can opt-out using browser extensions or cookie settings.
```

2. **Cookie Policy** - Document GA4 cookies:
```markdown
## Cookies We Use

- _ga: Distinguishes users (expires: 2 years)
- _ga_*: Used by Google Analytics (expires: 2 years)
```

---

## Recommendation

### Phase 1: Start Simple (Month 1)
**Implement Option 2: Custom Tracking**

**Why:**
- ✅ Zero additional cost
- ✅ Privacy-friendly (GDPR-compliant by default)
- ✅ No cookie consent banner needed
- ✅ Full control over data
- ✅ Can track transit-specific events (route views, feedback)

**What to Build:**
1. Basic page view tracking (Week 1)
2. Simple analytics dashboard (Week 2)
3. Custom event tracking for key actions (Week 3)

### Phase 2: Evaluate Growth (Month 3-6)

**If traffic is low (<1000 visitors/month):**
- Keep custom tracking
- Consider adding ALB logs for debugging

**If traffic grows (>5000 visitors/month):**
- **Add GA4** for detailed insights:
  - Geographic distribution
  - Device/browser breakdown
  - User demographics
  - Acquisition sources
- Keep custom tracking for transit-specific events

### Phase 3: Scale (Month 6+)

**If traffic is significant (>50k visitors/month):**
- Keep both GA4 and custom tracking
- Add ALB logs for ops/security
- Consider Plausible Analytics ($9/month) if privacy becomes priority

---

## Decision Matrix

| Your Situation | Recommended Option |
|----------------|-------------------|
| **Just launched, <100 visitors/day** | Option 2: Custom Tracking |
| **Privacy is critical priority** | Option 2: Custom Tracking |
| **Want detailed demographics/devices** | Option 3: GA4 |
| **Need operational/security insights** | Option 1: ALB + Athena |
| **High traffic (>50k/month)** | All three options combined |
| **Budget-conscious** | Option 2: Custom Tracking |
| **Want professional reports** | Option 3: GA4 |

---

## Next Steps

1. **Decide on initial approach** based on current priorities
2. **Implement Phase 1** (recommended: Custom Tracking)
3. **Monitor for 30 days** to understand baseline traffic
4. **Re-evaluate** and add additional options if needed

---

## Questions to Answer Before Implementation

1. **What's more important: detailed insights or privacy?**
   - Privacy → Custom Tracking
   - Insights → GA4

2. **Do you need to know user demographics and interests?**
   - Yes → GA4 (only option that provides this)
   - No → Custom Tracking

3. **Are you comfortable maintaining a cookie consent banner?**
   - No → Custom Tracking
   - Yes → GA4

4. **What's your monthly analytics budget?**
   - $0 → Custom Tracking or GA4
   - $1-5 → ALB + Athena + Custom
   - $9+ → Add Plausible

5. **What insights matter most?**
   - Page views, popular routes → All options
   - Custom events (feedback, route views) → Custom or GA4
   - Geographic/device breakdown → GA4
   - Performance/errors → ALB logs

---

## Appendix: Sample Analytics Questions Each Option Can Answer

### Questions All Options Can Answer:
- ✅ How many daily/monthly visitors?
- ✅ Which pages are most popular?
- ✅ What time of day is peak traffic?

### Option 1 (ALB + Athena) Only:
- ✅ What's the average response time per route?
- ✅ How many 404/500 errors occurred?
- ✅ Which IPs are making the most requests? (security)

### Option 2 (Custom Tracking) Only:
- ✅ Which routes get the most feedback submissions?
- ✅ What's the user journey from homepage → route detail?
- ✅ How many users view weather impact analysis?

### Option 3 (GA4) Only:
- ✅ What percentage of users are on mobile vs desktop?
- ✅ Which cities have the most visitors?
- ✅ What's the average session duration?
- ✅ Where do users come from (Google, social media, direct)?
- ✅ What's the bounce rate per page?
- ✅ User age/gender/interests (if demographics enabled)

---

**Last Updated:** October 2025
**Status:** Planning Document
**Owner:** @samuelwilk
