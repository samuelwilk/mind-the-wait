# Backend API Changes for iOS App

> **📋 STATUS: IMPLEMENTATION READY**
>
> **Priority:** Critical (required for iOS app launch)
> **Estimated Effort:** 3-4 days
> **Last Updated:** 2025-10-19

## Executive Summary

The iOS app requires minimal backend changes because most API endpoints already exist. This document outlines the specific additions and modifications needed to support:
1. Multi-city architecture
2. Live Activities (Dynamic Island)
3. Enhanced realtime data for 3D visualization
4. Widget timeline updates

**Key Principle:** Reuse existing infrastructure. Don't rebuild what works.

---

## Current API Status

### ✅ Already Implemented (No Changes Needed)

**Existing Endpoints:**
- `GET /api/v1/routes` - List all routes with performance metrics
- `GET /api/v1/routes/{gtfsId}` - Route detail with stats
- `GET /api/v1/stops` - List stops (with optional `?route_id` filter)
- `GET /api/v1/stops/{gtfsId}/predictions` - Arrival predictions for a stop
- `GET /api/realtime` - Live vehicle positions (with optional `?route_id` filter)

**These endpoints already return everything needed for:**
- Route list screen
- 3D visualization vehicle positions
- Stop predictions/countdowns
- Basic widgets

---

## Required Changes

### 1. Multi-City Support

#### 1.1 Database Schema

**File:** `migrations/VersionXXX_AddCitySupport.php`

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionXXX_AddCitySupport extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-city support for iOS app expansion';
    }

    public function up(Schema $schema): void
    {
        // Create city table
        $this->addSql("
            CREATE TABLE city (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) UNIQUE NOT NULL,
                country VARCHAR(2) NOT NULL DEFAULT 'CA',
                gtfs_static_url VARCHAR(500),
                gtfs_rt_vehicle_url VARCHAR(500),
                gtfs_rt_trip_url VARCHAR(500),
                gtfs_rt_alert_url VARCHAR(500),
                center_lat NUMERIC(10, 8) NOT NULL,
                center_lon NUMERIC(11, 8) NOT NULL,
                zoom_level SMALLINT DEFAULT 12,
                active BOOLEAN DEFAULT true,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )
        ");

        // Add city_id to existing tables
        $this->addSql('ALTER TABLE route ADD COLUMN city_id INT REFERENCES city(id)');
        $this->addSql('ALTER TABLE stop ADD COLUMN city_id INT REFERENCES city(id)');
        $this->addSql('ALTER TABLE trip ADD COLUMN city_id INT REFERENCES city(id)');

        // Create indexes
        $this->addSql('CREATE INDEX idx_route_city ON route (city_id)');
        $this->addSql('CREATE INDEX idx_stop_city ON stop (city_id)');
        $this->addSql('CREATE INDEX idx_trip_city ON trip (city_id)');

        // Seed Saskatoon as first city
        $this->addSql("
            INSERT INTO city (name, slug, country, center_lat, center_lon, zoom_level, active)
            VALUES ('Saskatoon', 'saskatoon', 'CA', 52.1324, -106.6689, 12, true)
        ");

        // Set all existing data to Saskatoon
        $this->addSql('UPDATE route SET city_id = 1');
        $this->addSql('UPDATE stop SET city_id = 1');
        $this->addSql('UPDATE trip SET city_id = 1');

        // Make city_id NOT NULL after backfill
        $this->addSql('ALTER TABLE route ALTER COLUMN city_id SET NOT NULL');
        $this->addSql('ALTER TABLE stop ALTER COLUMN city_id SET NOT NULL');
        $this->addSql('ALTER TABLE trip ALTER COLUMN city_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route DROP COLUMN city_id');
        $this->addSql('ALTER TABLE stop DROP COLUMN city_id');
        $this->addSql('ALTER TABLE trip DROP COLUMN city_id');
        $this->addSql('DROP TABLE city');
    }
}
```

#### 1.2 City Entity

**File:** `src/Entity/City.php`

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'city')]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 2)]
    private string $country = 'CA';

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $gtfsStaticUrl = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $gtfsRtVehicleUrl = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $gtfsRtTripUrl = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $gtfsRtAlertUrl = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8)]
    private string $centerLat;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 8)]
    private string $centerLon;

    #[ORM\Column(type: 'smallint')]
    private int $zoomLevel = 12;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    // Getters and setters...

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getCenterLat(): float
    {
        return (float) $this->centerLat;
    }

    public function getCenterLon(): float
    {
        return (float) $this->centerLon;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    // ... other getters/setters
}
```

#### 1.3 City Repository

**File:** `src/Repository/CityRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    /**
     * Find all active cities for iOS app city picker.
     *
     * @return list<City>
     */
    public function findActiveCities(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find city by slug (e.g., 'saskatoon').
     */
    public function findBySlug(string $slug): ?City
    {
        return $this->findOneBy(['slug' => $slug, 'active' => true]);
    }
}
```

#### 1.4 City API Controller

**File:** `src/Controller/Api/CityApiController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cities', name: 'api_v1_cities_')]
final class CityApiController extends AbstractController
{
    public function __construct(
        private readonly CityRepository $cityRepo,
    ) {
    }

    /**
     * Get list of all active cities for iOS app city picker.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $cities = $this->cityRepo->findActiveCities();

        return $this->json([
            'cities' => array_map(fn ($c) => [
                'id'         => $c->getId(),
                'name'       => $c->getName(),
                'slug'       => $c->getSlug(),
                'country'    => $c->getCountry(),
                'center_lat' => $c->getCenterLat(),
                'center_lon' => $c->getCenterLon(),
                'zoom_level' => $c->getZoomLevel(),
            ], $cities),
        ], headers: [
            'Cache-Control' => 'public, max-age=86400', // 24 hours
        ]);
    }
}
```

#### 1.5 Update Existing Route APIs to Filter by City

**File:** `src/Controller/Api/RouteApiController.php`

```php
/**
 * Get list of all routes with current performance metrics.
 * Optionally filter by city slug.
 */
#[Route('/routes', name: 'routes_list', methods: ['GET'])]
public function listRoutes(
    Request $request,
    RoutePerformanceService $performanceService,
    CityRepository $cityRepo,
): JsonResponse {
    $citySlug = $request->query->get('city');
    $city = null;

    if ($citySlug) {
        $city = $cityRepo->findBySlug($citySlug);
        if ($city === null) {
            return $this->json(['error' => 'City not found'], 404);
        }
    }

    $routes = $performanceService->getRouteListWithMetrics($city);

    return $this->json([
        'routes' => array_map(fn ($r) => [
            'id'              => $r->routeId,
            'short_name'      => $r->shortName,
            'long_name'       => $r->longName,
            'color'           => $r->colour,
            'grade'           => $r->grade,
            'on_time_pct'     => $r->onTimePercentage,
            'active_vehicles' => $r->activeVehicles,
        ], $routes),
        'timestamp' => time(),
        'city'      => $city ? $city->getSlug() : null,
    ], headers: [
        'Cache-Control' => 'public, max-age=300', // 5 min cache
    ]);
}
```

**Update RoutePerformanceService:**

```php
public function getRouteListWithMetrics(?City $city = null): array
{
    $qb = $this->routeRepo->createQueryBuilder('r');

    if ($city !== null) {
        $qb->where('r.city = :city')
           ->setParameter('city', $city);
    }

    $routes = $qb->getQuery()->getResult();

    // ... existing logic
}
```

---

### 2. Live Activities API (Dynamic Island)

#### 2.1 Route Status Endpoint

**File:** `src/Controller/Api/LiveActivityController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RouteRepository;
use App\Repository\RealtimeRepository;
use App\Repository\StopRepository;
use App\Service\Realtime\VehicleStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/live-activity', name: 'api_v1_live_activity_')]
final class LiveActivityController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepo,
        private readonly StopRepository $stopRepo,
        private readonly RealtimeRepository $realtimeRepo,
        private readonly VehicleStatusService $vehicleStatus,
    ) {
    }

    /**
     * Get route status data optimized for Live Activities (Dynamic Island).
     * Returns only essential data with minimal payload size.
     */
    #[Route('/route/{gtfsId}', name: 'route_status', methods: ['GET'])]
    public function routeStatus(string $gtfsId): JsonResponse
    {
        $route = $this->routeRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($route === null) {
            return $this->json(['error' => 'Route not found'], 404);
        }

        // Get realtime vehicles for this route
        $snapshot = $this->realtimeRepo->snapshot();
        $vehicles = array_filter(
            $snapshot['vehicles'],
            fn (array $v) => ($v['route'] ?? null) === $gtfsId
        );

        // Count vehicles by status
        $onTimeCount = 0;
        $lateCount = 0;
        $earlyCount = 0;

        foreach ($vehicles as $vehicle) {
            $status = $this->vehicleStatus->buildStatus($vehicle);

            if ($status['delay_sec'] > 180) {
                ++$lateCount;
            } elseif ($status['delay_sec'] < -180) {
                ++$earlyCount;
            } else {
                ++$onTimeCount;
            }
        }

        $totalVehicles = count($vehicles);
        $healthPercent = $totalVehicles > 0
            ? round(($onTimeCount / $totalVehicles) * 100, 0)
            : 0;

        // Minimal payload for Live Activities (keep under 4KB)
        return $this->json([
            'route_id'         => $gtfsId,
            'short_name'       => $route->getShortName(),
            'color'            => $route->getColour(),
            'active_vehicles'  => $totalVehicles,
            'on_time_vehicles' => $onTimeCount,
            'late_vehicles'    => $lateCount,
            'early_vehicles'   => $earlyCount,
            'health_percent'   => $healthPercent,
            'timestamp'        => $snapshot['timestamp'] ?? time(),
        ], headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Get next arrival predictions for a stop (for Live Activity countdowns).
     */
    #[Route('/stop/{gtfsId}/next-arrivals', name: 'stop_next_arrivals', methods: ['GET'])]
    public function stopNextArrivals(string $gtfsId): JsonResponse
    {
        $stop = $this->stopRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($stop === null) {
            return $this->json(['error' => 'Stop not found'], 404);
        }

        // Get next 3 arrivals only (Live Activities have strict size limits)
        $predictions = $this->arrivalPredictor->predictNextArrivals($stop->getId(), 3);

        return $this->json([
            'stop_id'   => $gtfsId,
            'stop_name' => $stop->getName(),
            'arrivals'  => array_map(fn ($p) => [
                'route_short_name' => $p->routeShortName,
                'seconds_until'    => (int) $p->predictedArrivalAt->getTimestamp() - time(),
                'confidence'       => $p->confidence->value,
            ], $predictions),
            'timestamp' => time(),
        ], headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
```

---

### 3. Enhanced Realtime Data for 3D Visualization

The existing `/api/realtime` endpoint already returns everything needed, but we should ensure it includes bearing data for vehicle rotation in 3D scene.

#### 3.1 Verify Realtime Response Includes Bearing

**Check:** `src/Controller/RealtimeController.php`

Ensure the response includes:
```php
[
    'id' => $vehicle['id'],
    'route' => $vehicle['route'],
    'latitude' => $vehicle['latitude'],
    'longitude' => $vehicle['longitude'],
    'bearing' => $vehicle['bearing'] ?? null,  // ← Essential for 3D rotation
    'speed' => $vehicle['speed'] ?? null,
    'delay_sec' => $status['delay_sec'] ?? null,
    'status' => $status['status'] ?? 'unknown',
    'last_update' => $vehicle['timestamp'],
]
```

**If bearing is missing from Redis data:**

Update the Python sidecar (`pyparser/`) to ensure it extracts bearing from GTFS-RT protobuf:

```python
# pyparser/main.py
vehicle_data = {
    'id': entity.id,
    'route': vehicle.trip.route_id,
    'latitude': vehicle.position.latitude,
    'longitude': vehicle.position.longitude,
    'bearing': vehicle.position.bearing if vehicle.position.HasField('bearing') else None,  # Add this
    'speed': vehicle.position.speed if vehicle.position.HasField('speed') else None,
    'timestamp': vehicle.timestamp,
}
```

---

### 4. Redis Namespacing for Multi-City

Update Redis keys to include city slug:

**Current:**
```
mtw:vehicles
mtw:trips
mtw:alerts
mtw:score
```

**New:**
```
mtw:saskatoon:vehicles
mtw:saskatoon:trips
mtw:saskatoon:alerts
mtw:saskatoon:score
mtw:regina:vehicles
mtw:regina:trips
...
```

#### 4.1 Update RealtimeRepository

**File:** `src/Repository/RealtimeRepository.php`

```php
public function __construct(
    private readonly PredisClient $redis,
    private string $citySlug = 'saskatoon', // Default to saskatoon for backwards compat
) {
}

public function setCitySlug(string $slug): void
{
    $this->citySlug = $slug;
}

private function getKey(string $type): string
{
    return sprintf('mtw:%s:%s', $this->citySlug, $type);
}

public function snapshot(): array
{
    $data = $this->redis->hgetall($this->getKey('vehicles'));
    // ... rest of existing code
}
```

#### 4.2 Update Controllers to Accept City Parameter

```php
#[Route('/api/realtime', name: 'api_realtime', methods: ['GET'])]
public function snapshot(
    Request $request,
    CityRepository $cityRepo,
): JsonResponse {
    $citySlug = $request->query->get('city', 'saskatoon');
    $city = $cityRepo->findBySlug($citySlug);

    if ($city === null) {
        return $this->json(['error' => 'City not found'], 404);
    }

    $this->realtimeRepo->setCitySlug($citySlug);
    $snapshot = $this->realtimeRepo->snapshot();

    // ... existing code
}
```

---

### 5. Health Check Endpoint (Already Exists)

Verify `src/Controller/HealthController.php` exists:

```php
#[Route('/api/healthz', methods: ['GET'])]
public function health(): JsonResponse
{
    return $this->json(['status' => 'ok']);
}
```

---

## Testing Checklist

**After Implementation:**

- [ ] `/api/v1/cities` returns Saskatoon with correct coordinates
- [ ] `/api/v1/routes?city=saskatoon` filters routes correctly
- [ ] `/api/v1/routes` without city param returns all routes (backwards compat)
- [ ] `/api/realtime?city=saskatoon` returns vehicles for Saskatoon
- [ ] `/api/v1/live-activity/route/{gtfsId}` returns minimal payload (<4KB)
- [ ] `/api/v1/live-activity/stop/{gtfsId}/next-arrivals` returns next 3 arrivals
- [ ] Bearing is included in realtime vehicle data
- [ ] Redis keys are namespaced by city
- [ ] CORS headers allow iOS app domain
- [ ] Rate limiting allows widget refresh (2 requests/minute per device)

---

## Deployment Steps

### 1. Database Migration
```bash
make database-migrations-generate
make database-migrations-execute
```

### 2. Seed Additional Cities

**File:** `src/Command/SeedCitiesCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:cities', description: 'Seed Canadian cities for iOS app')]
final class SeedCitiesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cities = [
            [
                'name'       => 'Regina',
                'slug'       => 'regina',
                'country'    => 'CA',
                'center_lat' => 50.4452,
                'center_lon' => -104.6189,
            ],
            [
                'name'       => 'Winnipeg',
                'slug'       => 'winnipeg',
                'country'    => 'CA',
                'center_lat' => 49.8951,
                'center_lon' => -97.1384,
            ],
            [
                'name'       => 'Calgary',
                'slug'       => 'calgary',
                'country'    => 'CA',
                'center_lat' => 51.0447,
                'center_lon' => -114.0719,
            ],
            [
                'name'       => 'Edmonton',
                'slug'       => 'edmonton',
                'country'    => 'CA',
                'center_lat' => 53.5461,
                'center_lon' => -113.4938,
            ],
        ];

        foreach ($cities as $cityData) {
            $city = new City();
            $city->setName($cityData['name']);
            $city->setSlug($cityData['slug']);
            $city->setCountry($cityData['country']);
            $city->setCenterLat((string) $cityData['center_lat']);
            $city->setCenterLon((string) $cityData['center_lon']);
            $city->setActive(false); // Start inactive, enable after loading GTFS

            $this->em->persist($city);
            $output->writeln(sprintf('Seeded city: %s', $cityData['name']));
        }

        $this->em->flush();

        return Command::SUCCESS;
    }
}
```

Run:
```bash
docker compose exec php bin/console app:seed:cities
```

### 3. Update Python Sidecar

**Add city support to `pyparser/`:**

```yaml
# docker-compose.yml
services:
  pyparser-saskatoon:
    build: ./pyparser
    environment:
      - CITY_SLUG=saskatoon
      - GTFS_RT_VEHICLE_URL=${MTW_GTFS_RT_VEHICLE_URL}
      - GTFS_RT_TRIP_URL=${MTW_GTFS_RT_TRIP_URL}
    depends_on:
      - redis

  # Add more cities as needed:
  # pyparser-regina:
  #   build: ./pyparser
  #   environment:
  #     - CITY_SLUG=regina
  #     - GTFS_RT_VEHICLE_URL=...
```

Update `pyparser/main.py` to use `CITY_SLUG` in Redis keys:

```python
import os

CITY_SLUG = os.getenv('CITY_SLUG', 'saskatoon')

def write_to_redis(redis_client, key_suffix, data):
    key = f'mtw:{CITY_SLUG}:{key_suffix}'
    redis_client.hset(key, mapping={'ts': int(time.time()), 'json': json.dumps(data)})
```

---

## API Response Examples

### GET /api/v1/cities

```json
{
  "cities": [
    {
      "id": 1,
      "name": "Saskatoon",
      "slug": "saskatoon",
      "country": "CA",
      "center_lat": 52.1324,
      "center_lon": -106.6689,
      "zoom_level": 12
    },
    {
      "id": 2,
      "name": "Regina",
      "slug": "regina",
      "country": "CA",
      "center_lat": 50.4452,
      "center_lon": -104.6189,
      "zoom_level": 12
    }
  ]
}
```

### GET /api/v1/live-activity/route/16

```json
{
  "route_id": "16",
  "short_name": "16",
  "color": "#E74C3C",
  "active_vehicles": 3,
  "on_time_vehicles": 2,
  "late_vehicles": 1,
  "early_vehicles": 0,
  "health_percent": 67,
  "timestamp": 1697712345
}
```

### GET /api/realtime?city=saskatoon

```json
{
  "vehicles": [
    {
      "id": "veh-123",
      "route": "16",
      "latitude": 52.1332,
      "longitude": -106.6700,
      "bearing": 145,
      "speed": 35.5,
      "delay_sec": 120,
      "status": "late",
      "last_update": 1697712340
    }
  ],
  "timestamp": 1697712345
}
```

---

## Performance Considerations

**Optimization:**
- City list cached for 24 hours (changes rarely)
- Route list cached for 5 minutes per city
- Live Activity endpoint has no caching (always fresh)
- Realtime endpoint has no caching (always fresh)

**Expected Load:**
- 1,000 iOS users
- 4 cities
- Widget refresh every 30 seconds
- Live Activity updates every 10 seconds (when active)
- **Peak: ~400 requests/minute** (well within current capacity)

---

## Summary

**Total Implementation Time: 3-4 days**

| Task | Time | Priority |
|------|------|----------|
| Multi-city database schema | 4 hours | Critical |
| City entity + repository + API | 3 hours | Critical |
| Update existing APIs for city filtering | 4 hours | Critical |
| Redis namespacing | 3 hours | Critical |
| Live Activities API endpoints | 4 hours | Critical |
| Verify bearing in realtime data | 2 hours | High |
| Testing + deployment | 4 hours | Critical |

**All changes are additive** - existing web app functionality is not affected.

**Backwards compatibility:** All endpoints support optional `?city=` parameter, defaulting to current behavior if omitted.

---

**Document Version:** 1.0
**Last Updated:** 2025-10-19
**Status:** Ready for Implementation
