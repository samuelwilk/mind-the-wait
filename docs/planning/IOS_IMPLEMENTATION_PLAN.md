# Mind-the-Wait iOS Implementation Plan

> **📋 STATUS: PARTIALLY IMPLEMENTED** | Backend complete, iOS app development not started.
>
> **Implementation Status:**
> - ✅ Section 1: Backend Updates (API endpoints created)
> - ✅ Section 2: Infrastructure Changes (CORS, rate limiting, health checks configured)
> - ⏸️ Section 3-6: iOS app development (NOT started)
> **Priority:** High (mobile-first user experience)
> **Estimated Remaining Effort:** 6 weeks for iOS app development
> **Last Updated:** 2025-10-19

## Executive Summary

### Architecture Overview

Mind-the-Wait's existing Symfony backend already performs the heavy lifting: GTFS-RT ingestion (Python sidecar), headway scoring (every 30s), historical aggregation, and AI insights. The iOS app will be a **thin client** consuming JSON APIs, with aggressive caching and MapKit visualization.

**Core Philosophy:** Don't rebuild what works. Expose existing services via clean APIs, add mobile-friendly endpoints where gaps exist, and let SwiftUI focus on rendering + UX.

### Goals

1. **Launch v1.0 on TestFlight within 6 weeks** (single-city: Saskatoon)
2. **Native performance**: MapKit clustering, 60fps scrolling, <200ms API response times
3. **Cost profile**: <$5/month incremental AWS spend (leverage existing ECS tasks)
4. **Scalable foundation**: Architecture supports multi-city expansion without backend rewrites

### Cost Profile (Incremental)

| Service | Current (Web) | iOS Addition | Monthly Cost |
|---------|---------------|--------------|--------------|
| **ECS Tasks** | $15 (php + scheduler) | None (reuse) | $0 |
| **RDS PostgreSQL** | $25 | None | $0 |
| **ElastiCache Redis** | $15 | None | $0 |
| **ALB** | $18 | +10% traffic | ~$2 |
| **CloudFront** | $1 | JSON APIs cached | ~$1 |
| **OpenAI API** | $0.05 | Shared cache | $0 |
| **Total Incremental** | — | — | **~$3/month** |

**Key savings**:
- Reuse existing scheduler (no new containers)
- Redis already caches realtime data (no Lambda cold starts)
- Client-side caching reduces API calls by 80%+
- CloudFront caches route lists, stop data for 5 minutes

---

## 1. Backend Updates

### 1.1 What Stays Unchanged ✅

**Core Infrastructure (Zero Changes):**
- Python sidecar (GTFS-RT polling → Redis)
- Scheduler container (`app:score:tick` every 30s)
- `CollectArrivalLogsCommand` + `CollectDailyPerformanceCommand`
- PostgreSQL schema (Route, Stop, Trip, StopTime, ArrivalLog, RoutePerformanceDaily)
- Redis keys (`mtw:vehicles`, `mtw:trips`, `mtw:alerts`, `mtw:score`)
- AI insight generation (reuse cached insights)

**Why:** This pipeline is mature, tested, and cost-optimized. iOS will consume outputs, not duplicate logic.

### 1.2 New API Endpoints (JSON Only)

Add to `src/Controller/Api/` namespace:

#### **A. Route List API**

**File:** `src/Controller/Api/RouteApiController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Dashboard\RoutePerformanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mobile API endpoints for iOS app.
 */
#[Route('/api/v1', name: 'api_v1_')]
final class RouteApiController extends AbstractController
{
    public function __construct(
        private readonly RoutePerformanceService $performanceService,
    ) {
    }

    /**
     * Get list of all routes with current performance metrics.
     *
     * @return JsonResponse
     */
    #[Route('/routes', name: 'routes_list', methods: ['GET'])]
    public function listRoutes(): JsonResponse
    {
        $routes = $this->performanceService->getRouteListWithMetrics();

        return $this->json([
            'routes' => array_map(fn($r) => [
                'id'              => $r->routeId,
                'short_name'      => $r->shortName,
                'long_name'       => $r->longName,
                'color'           => $r->colour,
                'grade'           => $r->grade,
                'on_time_pct'     => $r->onTimePercentage,
                'active_vehicles' => $r->activeVehicles,
            ], $routes),
            'timestamp' => time(),
        ], headers: [
            'Cache-Control' => 'public, max-age=300', // 5 min cache
        ]);
    }
}
```

#### **B. Route Detail API**

Add to same controller:

```php
/**
 * Get detailed performance metrics for a specific route.
 */
#[Route('/routes/{gtfsId}', name: 'route_detail', methods: ['GET'])]
public function routeDetail(
    string $gtfsId,
    RouteRepository $routeRepo,
): JsonResponse
{
    $route = $routeRepo->findOneBy(['gtfsId' => $gtfsId]);

    if ($route === null) {
        throw $this->createNotFoundException('Route not found');
    }

    $detail = $this->performanceService->getRouteDetail($route);

    return $this->json([
        'route' => [
            'id'         => $route->getGtfsId(),
            'short_name' => $route->getShortName(),
            'long_name'  => $route->getLongName(),
            'color'      => $route->getColour(),
        ],
        'stats' => $detail->stats,
        // Note: Charts omitted for mobile (too heavy)
        'timestamp' => time(),
    ], headers: [
        'Cache-Control' => 'public, max-age=600', // 10 min
    ]);
}
```

#### **C. Stops API**

**File:** `src/Controller/Api/StopApiController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\StopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', name: 'api_v1_')]
final class StopApiController extends AbstractController
{
    /**
     * Get list of stops, optionally filtered by route.
     */
    #[Route('/stops', name: 'stops_list', methods: ['GET'])]
    public function listStops(
        Request $request,
        StopRepository $stopRepo,
    ): JsonResponse
    {
        $routeId = $request->query->get('route_id');

        // If route_id provided, filter stops to that route
        if ($routeId) {
            $stops = $stopRepo->findByRoute($routeId);
        } else {
            $stops = $stopRepo->findAll();
        }

        return $this->json([
            'stops' => array_map(fn($s) => [
                'id'   => $s->getGtfsId(),
                'name' => $s->getName(),
                'lat'  => $s->getLatitude(),
                'lon'  => $s->getLongitude(),
            ], $stops),
        ], headers: [
            'Cache-Control' => 'public, max-age=3600', // 1 hour (stops rarely change)
        ]);
    }

    /**
     * Get arrival predictions for a specific stop.
     */
    #[Route('/stops/{gtfsId}/predictions', name: 'stop_predictions', methods: ['GET'])]
    public function stopPredictions(
        string $gtfsId,
        StopRepository $stopRepo,
        ArrivalPredictorInterface $predictor,
    ): JsonResponse
    {
        $stop = $stopRepo->findOneBy(['gtfsId' => $gtfsId]);

        if ($stop === null) {
            throw $this->createNotFoundException('Stop not found');
        }

        // Get next 5 arrivals
        $predictions = $predictor->predictNextArrivals($stop->getId(), 5);

        return $this->json([
            'stop' => [
                'id'   => $gtfsId,
                'name' => $stop->getName(),
            ],
            'predictions' => array_map(fn($p) => [
                'route_id'         => $p->routeId,
                'route_short_name' => $p->routeShortName,
                'arrival_time'     => $p->predictedArrivalAt->format('c'),
                'delay_sec'        => $p->delaySec,
                'confidence'       => $p->confidence->value,
            ], $predictions),
        ], headers: [
            'Cache-Control' => 'no-cache', // Always fresh
        ]);
    }
}
```

#### **D. Enhanced Realtime API**

Update existing `src/Controller/RealtimeController.php`:

```php
/**
 * Get realtime vehicle positions, optionally filtered by route.
 */
#[Route('/api/realtime', name: 'api_realtime', methods: ['GET'])]
public function snapshot(Request $request): JsonResponse
{
    $routeId = $request->query->get('route_id'); // NEW: filter by route

    $snapshot = $this->realtimeRepo->snapshot();

    // Filter vehicles if route_id provided
    if ($routeId) {
        $snapshot['vehicles'] = array_filter(
            $snapshot['vehicles'],
            fn($v) => ($v['route'] ?? null) === $routeId
        );
    }

    // Enrich with status (existing code)
    $vehicles = array_map(
        fn($v) => $this->vehicleStatus->buildStatus($v),
        $snapshot['vehicles']
    );

    return $this->json([
        'vehicles'  => array_values($vehicles),
        'timestamp' => $snapshot['timestamp'] ?? time(),
    ], headers: [
        'Cache-Control' => 'no-cache', // Always fresh
    ]);
}
```

### 1.3 Repository Additions

**StopRepository::findByRoute()**

Add to `src/Repository/StopRepository.php`:

```php
/**
 * Find all stops served by a specific route.
 *
 * @return list<Stop>
 */
public function findByRoute(string $routeGtfsId): array
{
    return $this->createQueryBuilder('s')
        ->join('s.stopTimes', 'st')
        ->join('st.trip', 't')
        ->join('t.route', 'r')
        ->where('r.gtfsId = :routeId')
        ->setParameter('routeId', $routeGtfsId)
        ->distinct()
        ->getQuery()
        ->getResult();
}
```

### 1.4 CORS Configuration

Create `config/packages/nelmio_cors.yaml`:

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['*'] # Or restrict to app bundle ID in production
        allow_methods: ['GET']
        allow_headers: ['Content-Type', 'Authorization']
        max_age: 3600
    paths:
        '^/api/': ~
```

Install CORS bundle if not present:

```bash
composer require nelmio/cors-bundle
```

### 1.5 Rate Limiting (Optional but Recommended)

Add to `config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        mobile_api:
            policy: 'sliding_window'
            limit: 120  # 120 requests
            interval: '60 seconds'
```

Apply to controllers:

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/v1/routes')]
public function listRoutes(RateLimiterFactory $mobileApiLimiter): JsonResponse
{
    $limiter = $mobileApiLimiter->create($request->getClientIp());

    if (!$limiter->consume(1)->isAccepted()) {
        return $this->json(['error' => 'Too many requests'], 429);
    }

    // ... existing code
}
```

### 1.6 Health Check Endpoint

Ensure ALB health checks work:

**File:** `src/Controller/HealthController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/healthz', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
```

---

## 2. iOS Client Architecture

### 2.1 Tech Stack

- **Framework**: SwiftUI (iOS 16+ minimum, supports async/await)
- **Networking**: URLSession + Codable (no third-party dependencies)
- **Maps**: MapKit (native, free)
- **Persistence**:
  - UserDefaults (user preferences, favorites)
  - FileManager (JSON cache storage)
- **Background**: BackgroundTasks framework (iOS 13+)
- **Push**: APNs (optional Phase 2)
- **Deployment Target**: iOS 16.0+

**Why no third-party networking libraries?**
- URLSession is mature and performant
- Reduces app size and audit surface
- Native async/await support is excellent

### 2.2 Project Structure

```
MindTheWait/
├── App/
│   ├── MindTheWaitApp.swift          # App entry point
│   └── AppDelegate.swift             # Background tasks registration
│
├── Models/
│   ├── Route.swift                   # Codable struct
│   ├── Stop.swift
│   ├── Vehicle.swift
│   ├── Prediction.swift
│   └── RouteDetail.swift
│
├── Services/
│   ├── APIClient.swift               # Centralized HTTP client
│   ├── CacheManager.swift            # File-based JSON cache
│   ├── LocationService.swift         # User location (optional)
│   └── BackgroundRefreshService.swift
│
├── ViewModels/
│   ├── RouteListViewModel.swift      # ObservableObject
│   ├── MapViewModel.swift
│   └── StopDetailViewModel.swift
│
├── Views/
│   ├── RouteListView.swift           # Main tab
│   ├── MapView.swift                 # MapKit integration
│   ├── StopDetailView.swift
│   ├── RouteDetailView.swift
│   └── Components/
│       ├── RouteCard.swift
│       ├── VehicleAnnotation.swift
│       ├── GradeBadge.swift
│       └── PredictionRow.swift
│
├── Extensions/
│   ├── Color+Route.swift             # Parse hex colors
│   ├── Date+Formatting.swift
│   └── Double+Formatting.swift
│
└── Resources/
    ├── Assets.xcassets
    ├── Info.plist
    └── Localizable.strings (optional)
```

### 2.3 Data Models

**Models/Route.swift**

```swift
import SwiftUI

struct Route: Codable, Identifiable, Hashable {
    let id: String
    let shortName: String
    let longName: String
    let color: String
    let grade: String
    let onTimePct: Double
    let activeVehicles: Int

    enum CodingKeys: String, CodingKey {
        case id, grade, color
        case shortName = "short_name"
        case longName = "long_name"
        case onTimePct = "on_time_pct"
        case activeVehicles = "active_vehicles"
    }

    var uiColor: Color {
        Color(hex: color) ?? .blue
    }

    var gradeColor: Color {
        switch grade {
        case "A", "B": return .green
        case "C": return .yellow
        case "D": return .orange
        case "F": return .red
        default: return .gray
        }
    }
}

struct RoutesResponse: Codable {
    let routes: [Route]
    let timestamp: Int
}
```

**Models/Vehicle.swift**

```swift
import MapKit

struct Vehicle: Codable, Identifiable {
    let id: String
    let route: String
    let latitude: Double
    let longitude: Double
    let bearing: Int?
    let speed: Double?
    let delaySec: Int?
    let status: String // "on_time", "late", "early"
    let lastUpdate: Int

    enum CodingKeys: String, CodingKey {
        case id, route, latitude, longitude, bearing, speed, status
        case delaySec = "delay_sec"
        case lastUpdate = "last_update"
    }

    var coordinate: CLLocationCoordinate2D {
        CLLocationCoordinate2D(latitude: latitude, longitude: longitude)
    }

    var statusColor: Color {
        switch status {
        case "on_time": return .green
        case "late": return .red
        case "early": return .orange
        default: return .gray
        }
    }
}

struct VehiclesResponse: Codable {
    let vehicles: [Vehicle]
    let timestamp: Int
}
```

**Models/Stop.swift**

```swift
import MapKit

struct Stop: Codable, Identifiable, Hashable {
    let id: String
    let name: String
    let lat: Double
    let lon: Double

    var coordinate: CLLocationCoordinate2D {
        CLLocationCoordinate2D(latitude: lat, longitude: lon)
    }
}

struct StopsResponse: Codable {
    let stops: [Stop]
}
```

**Models/Prediction.swift**

```swift
import Foundation

struct Prediction: Codable, Identifiable {
    var id: String { "\(routeId)-\(arrivalTime)" }

    let routeId: String
    let routeShortName: String
    let arrivalTime: Date
    let delaySec: Int
    let confidence: String

    enum CodingKeys: String, CodingKey {
        case routeId = "route_id"
        case routeShortName = "route_short_name"
        case arrivalTime = "arrival_time"
        case delaySec = "delay_sec"
        case confidence
    }

    var minutesUntilArrival: Int {
        Int(arrivalTime.timeIntervalSinceNow / 60)
    }

    var delayMinutes: Int {
        delaySec / 60
    }
}

struct StopPredictionsResponse: Codable {
    let stop: StopInfo
    let predictions: [Prediction]

    struct StopInfo: Codable {
        let id: String
        let name: String
    }
}
```

### 2.4 API Client

**Services/APIClient.swift**

```swift
import Foundation

enum APIError: Error {
    case invalidResponse
    case networkError
    case decodingError
}

class APIClient {
    static let shared = APIClient()

    private let baseURL = "https://mindthewait.ca/api/v1"
    private let realtimeURL = "https://mindthewait.ca/api/realtime"
    private let session: URLSession

    private init() {
        let config = URLSessionConfiguration.default
        config.requestCachePolicy = .returnCacheDataElseLoad
        config.urlCache = URLCache(
            memoryCapacity: 50_000_000,  // 50 MB
            diskCapacity: 200_000_000     // 200 MB
        )
        config.timeoutIntervalForRequest = 10
        self.session = URLSession(configuration: config)
    }

    // MARK: - Routes

    func fetchRoutes() async throws -> [Route] {
        let url = URL(string: "\(baseURL)/routes")!
        let (data, response) = try await session.data(from: url)

        guard let httpResponse = response as? HTTPURLResponse,
              (200...299).contains(httpResponse.statusCode) else {
            throw APIError.invalidResponse
        }

        let decoded = try JSONDecoder().decode(RoutesResponse.self, from: data)
        return decoded.routes
    }

    func fetchRouteDetail(routeId: String) async throws -> RouteDetailResponse {
        let url = URL(string: "\(baseURL)/routes/\(routeId)")!
        let (data, _) = try await session.data(from: url)
        return try JSONDecoder().decode(RouteDetailResponse.self, from: data)
    }

    // MARK: - Stops

    func fetchStops(routeId: String? = nil) async throws -> [Stop] {
        var components = URLComponents(string: "\(baseURL)/stops")!

        if let routeId {
            components.queryItems = [URLQueryItem(name: "route_id", value: routeId)]
        }

        let (data, _) = try await session.data(from: components.url!)
        let response = try JSONDecoder().decode(StopsResponse.self, from: data)
        return response.stops
    }

    func fetchStopPredictions(stopId: String) async throws -> StopPredictionsResponse {
        let url = URL(string: "\(baseURL)/stops/\(stopId)/predictions")!
        let (data, _) = try await session.data(from: url)

        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601

        return try decoder.decode(StopPredictionsResponse.self, from: data)
    }

    // MARK: - Realtime Vehicles

    func fetchVehicles(routeId: String? = nil) async throws -> [Vehicle] {
        var components = URLComponents(string: realtimeURL)!

        if let routeId {
            components.queryItems = [URLQueryItem(name: "route_id", value: routeId)]
        }

        let (data, _) = try await session.data(from: components.url!)
        let response = try JSONDecoder().decode(VehiclesResponse.self, from: data)
        return response.vehicles
    }
}
```

### 2.5 Cache Manager

**Services/CacheManager.swift**

```swift
import Foundation

class CacheManager {
    static let shared = CacheManager()

    private let cacheDirectory: URL

    private init() {
        let paths = FileManager.default.urls(for: .cachesDirectory, in: .userDomainMask)
        cacheDirectory = paths[0].appendingPathComponent("APICache", isDirectory: true)

        // Create directory if it doesn't exist
        try? FileManager.default.createDirectory(
            at: cacheDirectory,
            withIntermediateDirectories: true
        )
    }

    func cache<T: Codable>(_ object: T, forKey key: String, ttl: TimeInterval = 300) {
        let wrapper = CacheWrapper(data: object, expiresAt: Date().addingTimeInterval(ttl))
        let url = cacheDirectory.appendingPathComponent("\(key).json")

        do {
            let encoded = try JSONEncoder().encode(wrapper)
            try encoded.write(to: url)
        } catch {
            print("Cache write failed: \(error)")
        }
    }

    func retrieve<T: Codable>(_ type: T.Type, forKey key: String) -> T? {
        let url = cacheDirectory.appendingPathComponent("\(key).json")

        guard let data = try? Data(contentsOf: url),
              let wrapper = try? JSONDecoder().decode(CacheWrapper<T>.self, from: data),
              wrapper.expiresAt > Date() else {
            return nil
        }

        return wrapper.data
    }

    func clearExpired() {
        DispatchQueue.global(qos: .utility).async {
            let files = try? FileManager.default.contentsOfDirectory(
                at: self.cacheDirectory,
                includingPropertiesForKeys: [.contentModificationDateKey]
            )

            files?.forEach { url in
                guard let attributes = try? FileManager.default.attributesOfItem(atPath: url.path),
                      let modDate = attributes[.modificationDate] as? Date else {
                    return
                }

                // Delete files older than 1 hour
                if Date().timeIntervalSince(modDate) > 3600 {
                    try? FileManager.default.removeItem(at: url)
                }
            }
        }
    }

    func clearAll() {
        try? FileManager.default.removeItem(at: cacheDirectory)
        try? FileManager.default.createDirectory(
            at: cacheDirectory,
            withIntermediateDirectories: true
        )
    }
}

struct CacheWrapper<T: Codable>: Codable {
    let data: T
    let expiresAt: Date
}
```

### 2.6 View Models

**ViewModels/RouteListViewModel.swift**

```swift
import SwiftUI

@MainActor
class RouteListViewModel: ObservableObject {
    @Published var routes: [Route] = []
    @Published var isLoading = false
    @Published var error: String?
    @Published var sortOrder: SortOrder = .name
    @Published var searchText = ""

    private let apiClient = APIClient.shared
    private let cache = CacheManager.shared

    enum SortOrder: String, CaseIterable {
        case name = "Name"
        case grade = "Grade"
        case performance = "Performance"
    }

    var filteredRoutes: [Route] {
        let filtered = searchText.isEmpty ? routes : routes.filter { route in
            route.shortName.localizedCaseInsensitiveContains(searchText) ||
            route.longName.localizedCaseInsensitiveContains(searchText)
        }

        return sortedRoutes(filtered)
    }

    func loadRoutes(forceRefresh: Bool = false) async {
        // Try cache first
        if !forceRefresh, let cached = cache.retrieve([Route].self, forKey: "routes") {
            routes = cached
            return
        }

        isLoading = true
        error = nil

        do {
            let fetchedRoutes = try await apiClient.fetchRoutes()
            routes = fetchedRoutes
            cache.cache(fetchedRoutes, forKey: "routes", ttl: 300) // 5 min
        } catch {
            self.error = "Failed to load routes: \(error.localizedDescription)"
        }

        isLoading = false
    }

    private func sortedRoutes(_ routes: [Route]) -> [Route] {
        switch sortOrder {
        case .name:
            return routes.sorted { $0.shortName.localizedStandardCompare($1.shortName) == .orderedAscending }
        case .grade:
            let gradeOrder = ["A": 5, "B": 4, "C": 3, "D": 2, "F": 1, "N/A": 0]
            return routes.sorted { (gradeOrder[$0.grade] ?? 0) > (gradeOrder[$1.grade] ?? 0) }
        case .performance:
            return routes.sorted { $0.onTimePct > $1.onTimePct }
        }
    }
}
```

**ViewModels/MapViewModel.swift**

```swift
import SwiftUI
import MapKit

@MainActor
class MapViewModel: ObservableObject {
    @Published var vehicles: [Vehicle] = []
    @Published var selectedRoute: Route?
    @Published var region = MKCoordinateRegion(
        center: CLLocationCoordinate2D(latitude: 52.1324, longitude: -106.6689), // Saskatoon
        span: MKCoordinateSpan(latitudeDelta: 0.1, longitudeDelta: 0.1)
    )

    private var refreshTimer: Timer?

    func startTracking(route: Route) {
        selectedRoute = route

        // Initial load
        Task { await loadVehicles() }

        // Refresh every 15 seconds
        refreshTimer = Timer.scheduledTimer(withTimeInterval: 15, repeats: true) { [weak self] _ in
            Task { await self?.loadVehicles() }
        }
    }

    func stopTracking() {
        refreshTimer?.invalidate()
        refreshTimer = nil
        vehicles = []
    }

    private func loadVehicles() async {
        guard let routeId = selectedRoute?.id else { return }

        do {
            vehicles = try await APIClient.shared.fetchVehicles(routeId: routeId)
        } catch {
            print("Failed to load vehicles: \(error)")
        }
    }
}
```

**ViewModels/StopDetailViewModel.swift**

```swift
import SwiftUI

@MainActor
class StopDetailViewModel: ObservableObject {
    @Published var predictions: [Prediction] = []
    @Published var isLoading = false
    @Published var error: String?

    let stop: Stop

    init(stop: Stop) {
        self.stop = stop
    }

    func loadPredictions() async {
        isLoading = true
        error = nil

        do {
            let response = try await APIClient.shared.fetchStopPredictions(stopId: stop.id)
            predictions = response.predictions
        } catch {
            self.error = "Failed to load predictions: \(error.localizedDescription)"
        }

        isLoading = false
    }
}
```

### 2.7 Views

**Views/RouteListView.swift**

```swift
import SwiftUI

struct RouteListView: View {
    @StateObject private var viewModel = RouteListViewModel()

    var body: some View {
        NavigationStack {
            Group {
                if viewModel.isLoading && viewModel.routes.isEmpty {
                    ProgressView("Loading routes...")
                } else if let error = viewModel.error {
                    ContentUnavailableView(
                        "Error Loading Routes",
                        systemImage: "exclamationmark.triangle",
                        description: Text(error)
                    )
                } else {
                    List(viewModel.filteredRoutes) { route in
                        NavigationLink(destination: RouteDetailView(route: route)) {
                            RouteCard(route: route)
                        }
                    }
                    .searchable(text: $viewModel.searchText, prompt: "Search routes")
                }
            }
            .navigationTitle("Saskatoon Transit")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Menu {
                        Picker("Sort", selection: $viewModel.sortOrder) {
                            ForEach(RouteListViewModel.SortOrder.allCases, id: \.self) { order in
                                Text(order.rawValue).tag(order)
                            }
                        }
                    } label: {
                        Image(systemName: "arrow.up.arrow.down")
                    }
                }
            }
            .refreshable {
                await viewModel.loadRoutes(forceRefresh: true)
            }
            .task {
                await viewModel.loadRoutes()
            }
        }
    }
}
```

**Views/Components/RouteCard.swift**

```swift
import SwiftUI

struct RouteCard: View {
    let route: Route

    var body: some View {
        HStack(spacing: 12) {
            // Route badge
            ZStack {
                RoundedRectangle(cornerRadius: 8)
                    .fill(route.uiColor)
                    .frame(width: 50, height: 50)

                Text(route.shortName)
                    .font(.system(size: 18, weight: .bold))
                    .foregroundColor(.white)
            }

            // Route info
            VStack(alignment: .leading, spacing: 4) {
                Text(route.longName)
                    .font(.headline)
                    .lineLimit(1)

                HStack(spacing: 12) {
                    Label("\(Int(route.onTimePct))%", systemImage: "clock")
                        .font(.caption)
                        .foregroundColor(.secondary)

                    if route.activeVehicles > 0 {
                        Label("\(route.activeVehicles)", systemImage: "bus")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                }
            }

            Spacer()

            // Grade badge
            GradeBadge(grade: route.grade, color: route.gradeColor)
        }
        .padding(.vertical, 4)
    }
}
```

**Views/Components/GradeBadge.swift**

```swift
import SwiftUI

struct GradeBadge: View {
    let grade: String
    let color: Color

    var body: some View {
        Text(grade)
            .font(.system(size: 20, weight: .bold))
            .foregroundColor(color)
            .frame(width: 40, height: 40)
            .background(color.opacity(0.2))
            .clipShape(Circle())
    }
}
```

**Views/MapView.swift**

```swift
import SwiftUI
import MapKit

struct MapView: View {
    @StateObject private var viewModel = MapViewModel()
    let route: Route

    var body: some View {
        Map(coordinateRegion: $viewModel.region, annotationItems: viewModel.vehicles) { vehicle in
            MapAnnotation(coordinate: vehicle.coordinate) {
                VehicleAnnotation(vehicle: vehicle)
            }
        }
        .ignoresSafeArea()
        .navigationTitle("Route \(route.shortName)")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            viewModel.startTracking(route: route)
        }
        .onDisappear {
            viewModel.stopTracking()
        }
    }
}
```

**Views/Components/VehicleAnnotation.swift**

```swift
import SwiftUI

struct VehicleAnnotation: View {
    let vehicle: Vehicle

    var body: some View {
        ZStack {
            Circle()
                .fill(vehicle.statusColor)
                .frame(width: 24, height: 24)

            Image(systemName: "bus.fill")
                .font(.system(size: 12))
                .foregroundColor(.white)
        }
        .shadow(radius: 3)
        .rotationEffect(.degrees(Double(vehicle.bearing ?? 0)))
    }
}
```

**Views/RouteDetailView.swift**

```swift
import SwiftUI

struct RouteDetailView: View {
    let route: Route
    @State private var showingMap = false

    var body: some View {
        List {
            Section {
                // Summary stats
                HStack(spacing: 20) {
                    VStack {
                        Text("\(Int(route.onTimePct))%")
                            .font(.system(size: 32, weight: .bold))
                        Text("On-Time")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }

                    Divider()

                    VStack {
                        GradeBadge(grade: route.grade, color: route.gradeColor)
                        Text("Grade")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }

                    Divider()

                    VStack {
                        Text("\(route.activeVehicles)")
                            .font(.system(size: 32, weight: .bold))
                        Text("Active")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                }
                .frame(maxWidth: .infinity)
                .padding(.vertical, 8)
            }

            Section {
                Button {
                    showingMap = true
                } label: {
                    Label("View Live Map", systemImage: "map")
                        .font(.headline)
                }
            }
        }
        .navigationTitle("Route \(route.shortName)")
        .navigationBarTitleDisplayMode(.large)
        .sheet(isPresented: $showingMap) {
            NavigationStack {
                MapView(route: route)
                    .toolbar {
                        ToolbarItem(placement: .navigationBarTrailing) {
                            Button("Done") {
                                showingMap = false
                            }
                        }
                    }
            }
        }
    }
}
```

### 2.8 Extensions

**Extensions/Color+Route.swift**

```swift
import SwiftUI

extension Color {
    init?(hex: String) {
        let hex = hex.trimmingCharacters(in: CharacterSet.alphanumerics.inverted)
        var int: UInt64 = 0

        guard Scanner(string: hex).scanHexInt64(&int) else {
            return nil
        }

        let r, g, b: UInt64
        switch hex.count {
        case 3: // RGB (12-bit)
            (r, g, b) = ((int >> 8) * 17, (int >> 4 & 0xF) * 17, (int & 0xF) * 17)
        case 6: // RGB (24-bit)
            (r, g, b) = (int >> 16, int >> 8 & 0xFF, int & 0xFF)
        default:
            return nil
        }

        self.init(
            .sRGB,
            red: Double(r) / 255,
            green: Double(g) / 255,
            blue: Double(b) / 255,
            opacity: 1
        )
    }
}
```

**Extensions/Date+Formatting.swift**

```swift
import Foundation

extension Date {
    func relativeString() -> String {
        let formatter = RelativeDateTimeFormatter()
        formatter.unitsStyle = .abbreviated
        return formatter.localizedString(for: self, relativeTo: Date())
    }

    func timeString() -> String {
        let formatter = DateFormatter()
        formatter.timeStyle = .short
        return formatter.string(from: self)
    }
}
```

### 2.9 Background Refresh

**Services/BackgroundRefreshService.swift**

```swift
import BackgroundTasks

class BackgroundRefreshService {
    static let shared = BackgroundRefreshService()
    private let taskIdentifier = "ca.mindthewait.refresh"

    func registerBackgroundTasks() {
        BGTaskScheduler.shared.register(
            forTaskWithIdentifier: taskIdentifier,
            using: nil
        ) { task in
            self.handleAppRefresh(task: task as! BGAppRefreshTask)
        }
    }

    func scheduleAppRefresh() {
        let request = BGAppRefreshTaskRequest(identifier: taskIdentifier)
        request.earliestBeginDate = Date(timeIntervalSinceNow: 15 * 60) // 15 minutes

        do {
            try BGTaskScheduler.shared.submit(request)
        } catch {
            print("Could not schedule app refresh: \(error)")
        }
    }

    private func handleAppRefresh(task: BGAppRefreshTask) {
        scheduleAppRefresh() // Re-schedule next refresh

        Task {
            do {
                // Pre-fetch routes for faster app open
                let routes = try await APIClient.shared.fetchRoutes()
                CacheManager.shared.cache(routes, forKey: "routes", ttl: 900)
                task.setTaskCompleted(success: true)
            } catch {
                task.setTaskCompleted(success: false)
            }
        }
    }
}
```

**App/AppDelegate.swift**

```swift
import UIKit

class AppDelegate: NSObject, UIApplicationDelegate {
    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey : Any]? = nil
    ) -> Bool {
        BackgroundRefreshService.shared.registerBackgroundTasks()
        return true
    }

    func applicationDidEnterBackground(_ application: UIApplication) {
        BackgroundRefreshService.shared.scheduleAppRefresh()
    }
}
```

**App/MindTheWaitApp.swift**

```swift
import SwiftUI

@main
struct MindTheWaitApp: App {
    @UIApplicationDelegateAdaptor(AppDelegate.self) var appDelegate

    var body: some Scene {
        WindowGroup {
            RouteListView()
                .onAppear {
                    // Clear expired cache on launch
                    CacheManager.shared.clearExpired()
                }
        }
    }
}
```

### 2.10 Info.plist Configuration

Add to `Info.plist`:

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>We use your location to show nearby transit stops and routes.</string>

<key>BGTaskSchedulerPermittedIdentifiers</key>
<array>
    <string>ca.mindthewait.refresh</string>
</array>

<key>UIBackgroundModes</key>
<array>
    <string>fetch</string>
</array>

<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>mindthewait</string>
        </array>
    </dict>
</array>
```

---

## 3. Infrastructure Changes

### 3.1 CloudFront Distribution

Add cache behaviors for API endpoints in CloudFormation/Terraform:

```yaml
# cloudformation/cloudfront.yaml (or similar IaC config)
CacheBehaviors:
  - PathPattern: /api/v1/routes
    TargetOriginId: ALB
    CachePolicyId: CachingOptimized
    MinTTL: 300
    DefaultTTL: 300
    MaxTTL: 600
    Compress: true

  - PathPattern: /api/v1/stops
    TargetOriginId: ALB
    CachePolicyId: CachingOptimized
    MinTTL: 3600
    DefaultTTL: 3600
    MaxTTL: 86400
    Compress: true

  - PathPattern: /api/realtime
    TargetOriginId: ALB
    CachePolicyId: CachingDisabled  # Never cache realtime data
```

### 3.2 No New Infrastructure Needed ✅

**What stays the same:**
- ECS tasks (php, scheduler, pyparser) — no changes
- RDS PostgreSQL — no schema changes required
- ElastiCache Redis — same keys, same usage pattern
- ALB — add CORS headers via Symfony, no ALB config changes
- No Lambda functions needed
- No API Gateway needed (ALB + CloudFront is sufficient)

**Cost impact:** ~$3/month (CloudFront + slight ALB traffic increase)

---

## 4. Optional Features Roadmap

### Phase 2: Enhanced Features (Weeks 7-10)

#### **4.1 Push Notifications**

**Use Case:** Alert when user's favorite route drops below 70% on-time

**Backend Implementation:**

**Command:** `src/Command/SendPushAlertsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RoutePerformanceDailyRepository;
use App\Service\PushNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send:push-alerts',
    description: 'Send push notifications for low-performing routes',
)]
final class SendPushAlertsCommand extends Command
{
    public function __construct(
        private readonly RoutePerformanceDailyRepository $performanceRepo,
        private readonly PushNotificationService $pushService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');

        // Query routes with on-time % < 70% today
        $alerts = $this->performanceRepo->findLowPerformanceRoutes($today, 70.0);

        foreach ($alerts as $alert) {
            $this->pushService->sendToSubscribers($alert['routeId'], [
                'title' => "Route {$alert['shortName']} Delayed",
                'body'  => "Currently {$alert['onTimePct']}% on-time",
                'data'  => ['route_id' => $alert['routeId']],
            ]);
        }

        $output->writeln(sprintf('Sent %d push alerts', count($alerts)));

        return Command::SUCCESS;
    }
}
```

**Database Schema:**

```sql
CREATE TABLE device_subscription (
    id SERIAL PRIMARY KEY,
    device_token VARCHAR(255) UNIQUE NOT NULL,
    platform VARCHAR(10) NOT NULL, -- 'ios' or 'android'
    subscribed_routes TEXT, -- JSON array of route IDs
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_device_subscription_token ON device_subscription (device_token);
```

**iOS Implementation:**

```swift
// Request notification permission
import UserNotifications

class NotificationManager {
    static let shared = NotificationManager()

    func requestPermission() async -> Bool {
        let center = UNUserNotificationCenter.current()

        do {
            let granted = try await center.requestAuthorization(options: [.alert, .sound, .badge])
            return granted
        } catch {
            return false
        }
    }

    func registerDeviceToken(_ token: Data) async {
        let tokenString = token.map { String(format: "%02.2hhx", $0) }.joined()

        // Send to backend
        var request = URLRequest(url: URL(string: "https://mindthewait.ca/api/v1/devices")!)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        let body = ["device_token": tokenString, "platform": "ios"]
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)

        let (_, _) = try? await URLSession.shared.data(for: request)
    }
}
```

**Cost:** APNs is free; backend cost ~$0

---

#### **4.2 Offline Mode**

**Implementation:**
- Cache last 24 hours of route performance data in local SQLite database
- Show stale data with warning banner when offline
- Background sync when online

**Storage:** ~5 MB for 30 days × 22 routes

**iOS Implementation:**

```swift
import SQLite

class OfflineDataStore {
    private let db: Connection

    init() {
        let path = FileManager.default
            .urls(for: .documentDirectory, in: .userDomainMask)[0]
            .appendingPathComponent("offline.sqlite3")

        db = try! Connection(path.path)
        try! createTables()
    }

    private func createTables() throws {
        try db.run("""
            CREATE TABLE IF NOT EXISTS route_performance (
                route_id TEXT,
                date TEXT,
                on_time_pct REAL,
                grade TEXT,
                PRIMARY KEY (route_id, date)
            )
        """)
    }

    func cacheRoutePerformance(_ route: Route) async {
        // Store in SQLite for offline access
    }
}
```

---

#### **4.3 Trip Planner**

**Use Case:** "Next bus from Stop A to Stop B"

**Backend API:**

```php
#[Route('/api/v1/plan', name: 'trip_plan', methods: ['GET'])]
public function planTrip(
    Request $request,
    TripPlannerService $planner,
): JsonResponse
{
    $from = $request->query->get('from'); // stop_id
    $to   = $request->query->get('to');   // stop_id

    $trips = $planner->findTrips($from, $to, limit: 3);

    return $this->json([
        'trips' => $trips,
    ]);
}
```

**Complexity:** Medium (requires graph traversal of stop_time table)

---

### Phase 3: Multi-City Expansion (Month 4+)

#### **4.4 Architecture for Multi-City**

**Database Schema Changes:**

```sql
-- Add city table
CREATE TABLE city (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL, -- 'saskatoon', 'regina', etc.
    gtfs_static_url VARCHAR(255),
    gtfs_rt_vehicle_url VARCHAR(255),
    gtfs_rt_trip_url VARCHAR(255),
    center_lat DECIMAL(10, 8),
    center_lon DECIMAL(11, 8),
    zoom_level SMALLINT DEFAULT 12,
    active BOOLEAN DEFAULT true
);

-- Add city_id to existing tables
ALTER TABLE route ADD COLUMN city_id INT REFERENCES city(id);
ALTER TABLE stop ADD COLUMN city_id INT REFERENCES city(id);

-- Create indexes
CREATE INDEX idx_route_city ON route (city_id);
CREATE INDEX idx_stop_city ON stop (city_id);

-- Seed Saskatoon
INSERT INTO city (name, slug, center_lat, center_lon, zoom_level)
VALUES ('Saskatoon', 'saskatoon', 52.1324, -106.6689, 12);
```

**Redis Namespacing:**

```
mtw:saskatoon:vehicles
mtw:saskatoon:score
mtw:regina:vehicles
mtw:regina:score
```

**Python Sidecar (Multi-City):**

Launch multiple sidecar containers (one per city):

```yaml
# docker-compose.yml
services:
  pyparser-saskatoon:
    build: ./pyparser
    environment:
      - CITY_SLUG=saskatoon
      - GTFS_RT_URL=https://saskprdtmgtfs.sasktrpcloud.com/TMGTFSRealTimeWebService/
    depends_on:
      - redis

  pyparser-regina:
    build: ./pyparser
    environment:
      - CITY_SLUG=regina
      - GTFS_RT_URL=https://regina-transit-realtime-url.com/
    depends_on:
      - redis
```

**iOS Changes:**

```swift
// Add city picker on first launch
struct CityPickerView: View {
    @AppStorage("selectedCity") private var selectedCity = "saskatoon"
    @State private var cities: [City] = []

    var body: some View {
        List(cities) { city in
            Button {
                selectedCity = city.slug
            } label: {
                HStack {
                    Text(city.name)
                    Spacer()
                    if selectedCity == city.slug {
                        Image(systemName: "checkmark")
                    }
                }
            }
        }
        .task {
            await loadCities()
        }
    }
}

// Update API calls to include city
extension APIClient {
    func fetchRoutes(city: String = "saskatoon") async throws -> [Route] {
        let url = URL(string: "\(baseURL)/routes?city=\(city)")!
        // ...
    }
}
```

**Cost Impact:** +$15/month per city (new ECS task for sidecar + scheduler)

---

## 5. Cost Control & Scaling

### 5.1 Minimize AWS Spend

| Strategy | Implementation | Savings |
|----------|----------------|---------|
| **Aggressive client caching** | Cache routes for 5 min, stops for 1 hour | 80% fewer API calls |
| **CloudFront edge caching** | Cache `/api/v1/routes` for 5 min globally | $1-2/month vs direct ALB |
| **Conditional requests** | Send `If-None-Modified` headers | 304 responses are cheap |
| **Gzip compression** | Enable on ALB + CloudFront | 70% bandwidth reduction |
| **Lazy loading** | Only fetch map vehicles when tab is visible | 50% fewer realtime calls |
| **Background refresh limits** | Max once every 15 minutes | Prevent runaway tasks |

### 5.2 Database Query Optimization

**Before launching iOS, add indexes:**

```sql
-- Ensure these indexes exist
CREATE INDEX IF NOT EXISTS idx_route_gtfs_id ON route (gtfs_id);
CREATE INDEX IF NOT EXISTS idx_stop_gtfs_id ON stop (gtfs_id);
CREATE INDEX IF NOT EXISTS idx_stop_time_trip_stop ON stop_time (trip_id, stop_id);
```

**Verify with EXPLAIN:**

```sql
EXPLAIN ANALYZE
SELECT * FROM route WHERE gtfs_id = '123';
-- Should show "Index Scan" not "Seq Scan"
```

### 5.3 Monitoring & Alerts

**CloudWatch Alarms:**

```yaml
# cloudformation/alarms.yaml
ALB5xxAlarm:
  Type: AWS::CloudWatch::Alarm
  Properties:
    MetricName: HTTPCode_Target_5XX_Count
    Threshold: 10
    ComparisonOperator: GreaterThanThreshold
    AlarmActions:
      - !Ref SNSTopic

RDSCPUAlarm:
  Type: AWS::CloudWatch::Alarm
  Properties:
    MetricName: CPUUtilization
    Threshold: 80
    ComparisonOperator: GreaterThanThreshold
```

**App-side metrics (optional):**

```swift
// Log API response times to CloudWatch from iOS
import OSLog

let logger = Logger(subsystem: "ca.mindthewait", category: "networking")

func logAPIPerformance(endpoint: String, duration: TimeInterval) {
    logger.info("API \(endpoint) took \(duration)ms")
}
```

---

## 6. Development Milestones

### **Week 1-2: Backend API Development**

**Tasks:**
- [ ] Create `src/Controller/Api/RouteApiController.php`
- [ ] Implement `/api/v1/routes` endpoint
- [ ] Implement `/api/v1/routes/{id}` endpoint
- [ ] Create `src/Controller/Api/StopApiController.php`
- [ ] Implement `/api/v1/stops` with route filtering
- [ ] Implement `/api/v1/stops/{id}/predictions` endpoint
- [ ] Update `RealtimeController` to accept `route_id` parameter
- [ ] Add `StopRepository::findByRoute()` method
- [ ] Install and configure `nelmio/cors-bundle`
- [ ] Add rate limiting configuration
- [ ] Create `/api/healthz` endpoint
- [ ] Write PHPUnit tests for all new endpoints
- [ ] Test with Postman/curl
- [ ] Deploy to staging ECS environment
- [ ] Smoke test all APIs in staging

**Deliverable:** Fully functional JSON APIs with Postman collection

---

### **Week 3-4: iOS App Foundation**

**Tasks:**
- [ ] Create new Xcode project (SwiftUI, iOS 16+)
- [ ] Set up project structure (Models, Services, ViewModels, Views)
- [ ] Implement `APIClient.swift` with URLSession
- [ ] Implement `CacheManager.swift` with file-based storage
- [ ] Create data models (Route, Vehicle, Stop, Prediction)
- [ ] Implement `RouteListViewModel`
- [ ] Implement `RouteListView`
- [ ] Create `RouteCard` component
- [ ] Create `GradeBadge` component
- [ ] Add pull-to-refresh functionality
- [ ] Add loading states and error handling
- [ ] Implement route sorting (name, grade, performance)
- [ ] Add route search functionality
- [ ] Implement `Color+Route` extension for hex parsing
- [ ] Unit tests for APIClient
- [ ] Unit tests for CacheManager
- [ ] Test on iPhone SE and iPhone 15 Pro Max

**Deliverable:** Working route list screen with real data

---

### **Week 5: MapKit Integration**

**Tasks:**
- [ ] Implement `MapViewModel` with vehicle tracking
- [ ] Create `VehicleAnnotation` view (bus icon + status color)
- [ ] Implement `MapView` with realtime vehicle positions
- [ ] Add 15-second auto-refresh timer
- [ ] Implement vehicle clustering for 10+ vehicles (if needed)
- [ ] Handle bearing rotation for vehicle icons
- [ ] Add "View Live Map" button in route detail
- [ ] Test with 50+ simultaneous vehicles
- [ ] Optimize map performance (lazy loading, clustering)
- [ ] Test on low-end devices (iPhone SE)

**Deliverable:** Real-time map view with live vehicle positions

---

### **Week 6: Polish + TestFlight**

**Tasks:**
- [ ] Implement `StopDetailView` with predictions
- [ ] Create `PredictionRow` component
- [ ] Add "Favorites" feature (persist in UserDefaults)
- [ ] Implement `BackgroundRefreshService`
- [ ] Register background tasks in AppDelegate
- [ ] Design app icon (1024×1024)
- [ ] Create launch screen
- [ ] Add accessibility labels (VoiceOver support)
- [ ] Write App Store description
- [ ] Create App Store screenshots (6.7", 6.5", 5.5")
- [ ] Set up App Store Connect account
- [ ] Configure bundle ID and certificates
- [ ] Build and archive for TestFlight
- [ ] Submit to TestFlight (internal testing)
- [ ] Invite 5-10 internal testers
- [ ] Fix bugs from TestFlight feedback
- [ ] Monitor crash reports in Xcode Organizer

**Deliverable:** TestFlight beta available to internal testers

---

### **Week 7-8: Public Beta**

**Tasks:**
- [ ] Expand TestFlight to 100 external testers
- [ ] Collect crash reports and analytics
- [ ] Fix critical bugs (crashes, UI glitches, network errors)
- [ ] Optimize API caching based on user feedback
- [ ] Add onboarding tutorial (3 screens)
- [ ] Improve error messages and empty states
- [ ] Test on all supported devices (SE to Pro Max)
- [ ] Test with slow network (airplane mode toggle)
- [ ] Write privacy policy
- [ ] Write terms of service
- [ ] Localize for French (optional, if targeting Canada-wide)

**Deliverable:** Stable public beta with <5% crash rate

---

### **Week 9-10: App Store Launch**

**Tasks:**
- [ ] Record App Store preview video (30 seconds)
- [ ] Design App Store screenshots (all sizes)
- [ ] Write App Store metadata (title, subtitle, description)
- [ ] Add keywords for ASO (transit, bus, Saskatoon, etc.)
- [ ] Submit for App Store review
- [ ] Respond to review feedback within 24 hours
- [ ] Monitor App Store Connect for approval status
- [ ] Prepare launch announcement (Twitter, Reddit, local news)
- [ ] Launch press release (optional)
- [ ] Monitor App Store reviews and ratings
- [ ] Respond to user reviews
- [ ] Set up analytics dashboard (App Store Connect Analytics)

**Deliverable:** Mind-the-Wait iOS app live on App Store 🚀

---

## 7. Risk Analysis

### 7.1 Technical Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **GTFS-RT feed outage** | Medium | High | Cache last known vehicle positions for 5 min; show stale data banner |
| **iOS background refresh throttling** | High | Low | Accept it; educate users on manual refresh; add pull-to-refresh |
| **MapKit annotation performance with 50+ vehicles** | Medium | Medium | Implement clustering; test on iPhone SE; lazy load annotations |
| **API rate limits hit by aggressive polling** | Low | Medium | Exponential backoff + retry logic; respect HTTP 429 |
| **App Store rejection for privacy issues** | Medium | High | Follow guidelines strictly; add privacy policy; test data collection |
| **CORS issues in production** | Low | High | Test with production domain before launch; verify CORS headers |
| **Trip ID mismatch (realtime vs static)** | High | Medium | Already documented in CLAUDE.md; show "Limited data" when mismatch |

### 7.2 Business Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **Low adoption (<1000 downloads in 3 months)** | Medium | Low | Focus on quality over marketing; leverage word-of-mouth; Reddit/local forums |
| **City changes GTFS feed structure** | Low | High | Monitor feeds daily; add automated alerts for schema changes; version APIs |
| **Saskatoon Transit discontinues GTFS-RT** | Very Low | Critical | Archive data; have contingency plan; pivot to predictions-only mode |
| **Increased AWS costs beyond budget** | Low | Medium | Set billing alerts at $100/month; optimize caching; CloudFront logs |
| **User complaints about battery drain** | Medium | Low | Limit background refresh to 15 min intervals; profile with Instruments |

---

## 8. CI/CD Pipeline

### 8.1 Backend CI (GitHub Actions)

**File:** `.github/workflows/deploy-ios-api.yml`

```yaml
name: Deploy iOS APIs

on:
  push:
    branches: [main]
    paths:
      - 'src/Controller/Api/**'
      - 'config/routes/api.yaml'

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Start containers
        run: docker compose up -d

      - name: Run PHPUnit tests
        run: docker compose exec -T php vendor/bin/phpunit tests/Controller/Api/

      - name: Run code style check
        run: docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v2
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: us-east-1

      - name: Deploy to ECS
        run: |
          aws ecs update-service \
            --cluster mind-the-wait \
            --service php \
            --force-new-deployment

      - name: Wait for deployment
        run: |
          aws ecs wait services-stable \
            --cluster mind-the-wait \
            --services php
```

### 8.2 iOS CI (Fastlane + GitHub Actions)

**Install Fastlane:**

```bash
gem install fastlane
cd ios/
fastlane init
```

**File:** `ios/fastlane/Fastfile`

```ruby
default_platform(:ios)

platform :ios do
  desc "Run tests"
  lane :test do
    run_tests(scheme: "MindTheWait")
  end

  desc "Build and upload to TestFlight"
  lane :beta do
    increment_build_number(xcodeproj: "MindTheWait.xcodeproj")
    build_app(scheme: "MindTheWait")
    upload_to_testflight(skip_waiting_for_build_processing: true)
  end

  desc "Build and submit to App Store"
  lane :release do
    increment_build_number(xcodeproj: "MindTheWait.xcodeproj")
    build_app(scheme: "MindTheWait")
    upload_to_app_store(
      submit_for_review: true,
      automatic_release: false
    )
  end
end
```

**File:** `.github/workflows/ios-testflight.yml`

```yaml
name: iOS TestFlight

on:
  push:
    tags:
      - 'v*.*.*-beta*'

jobs:
  deploy:
    runs-on: macos-latest
    steps:
      - uses: actions/checkout@v3

      - name: Set up Ruby
        uses: ruby/setup-ruby@v1
        with:
          ruby-version: 3.0

      - name: Install Fastlane
        run: gem install fastlane

      - name: Upload to TestFlight
        env:
          FASTLANE_USER: ${{ secrets.APPLE_ID }}
          FASTLANE_PASSWORD: ${{ secrets.APP_SPECIFIC_PASSWORD }}
          MATCH_PASSWORD: ${{ secrets.MATCH_PASSWORD }}
        run: |
          cd ios
          fastlane beta
```

---

## 9. Alternative Architectures (Evaluated & Rejected)

### 9.1 ❌ React Native / Flutter

**Why rejected:**
- Adds 3-5 MB bundle size (SwiftUI is native)
- MapKit integration is clunky in cross-platform frameworks
- No performance benefit for our use case (data visualization)
- Harder to leverage native iOS features (widgets, Live Activities, Dynamic Island)
- Additional build complexity (Metro bundler, bridge overhead)

**When to reconsider:** If Android is added in Phase 4 (cost/benefit of shared codebase)

---

### 9.2 ❌ GraphQL API

**Why rejected:**
- Adds complexity (new backend service, schema stitching)
- Our APIs are simple CRUD (no over-fetching problem)
- REST + CloudFront caching is sufficient for mobile bandwidth
- No query builder needed (only 5 endpoints)

**When to reconsider:** If API grows to 20+ endpoints with complex nested relationships

---

### 9.3 ❌ Separate Mobile Backend (BFF Pattern)

**Why rejected:**
- Duplicates logic from Symfony (route performance, scoring)
- Increases operational overhead (deploy 2 services, 2 databases)
- No performance gain (ALB + CloudFront already sub-200ms)
- More surface area for bugs and security issues

**When to reconsider:** If mobile needs diverge significantly (e.g., social features, chat)

---

### 9.4 ❌ WebView Wrapper (Hybrid App)

**Why rejected:**
- Poor UX (web scrolling, no native animations)
- Can't access native features (MapKit, background refresh, push)
- Still requires App Store approval but with web limitations
- No offline capability

**When to reconsider:** Never (defeats purpose of native app)

---

## 10. Stretch Goals (Optional)

### 10.1 iOS Widgets (Home Screen)

**Implementation:**

```swift
// WidgetKit target
import WidgetKit
import SwiftUI

struct RouteWidget: Widget {
    let kind = "RouteWidget"

    var body: some WidgetConfiguration {
        StaticConfiguration(kind: kind, provider: RouteProvider()) { entry in
            RouteWidgetView(entry: entry)
        }
        .configurationDisplayName("Route Status")
        .description("Shows on-time % for your favorite routes")
        .supportedFamilies([.systemSmall, .systemMedium])
    }
}

struct RouteWidgetView: View {
    let entry: RouteEntry

    var body: some View {
        VStack(alignment: .leading) {
            Text("Route \(entry.route.shortName)")
                .font(.headline)

            HStack {
                Text("\(Int(entry.route.onTimePct))%")
                    .font(.largeTitle)
                GradeBadge(grade: entry.route.grade, color: entry.route.gradeColor)
            }
        }
    }
}
```

**Refresh:** Every 15 minutes (iOS limit)

---

### 10.2 Apple Watch Companion

**Features:**
- Glanceable on-time % for favorited routes
- Complication showing next bus arrival
- Haptic alerts when bus is 5 minutes away

**Complexity:** Medium (requires WatchKit target, WatchConnectivity framework)

---

### 10.3 Siri Shortcuts

**Examples:**
- "Hey Siri, when's the next bus on Route 4?"
- "Hey Siri, is Route 27 running on time?"

**Implementation:**

```swift
import Intents

class NextBusIntentHandler: NSObject, NextBusIntentHandling {
    func handle(intent: NextBusIntent, completion: @escaping (NextBusIntentResponse) -> Void) {
        Task {
            do {
                let predictions = try await APIClient.shared.fetchStopPredictions(stopId: intent.stopId!)
                let response = NextBusIntentResponse(code: .success, userActivity: nil)
                response.nextArrival = predictions.predictions.first?.arrivalTime
                completion(response)
            } catch {
                completion(NextBusIntentResponse(code: .failure, userActivity: nil))
            }
        }
    }
}
```

---

### 10.4 Live Activities (iOS 16.1+)

**Use Case:** Track a specific bus in Dynamic Island

**Implementation:**

```swift
import ActivityKit

struct BusTrackingAttributes: ActivityAttributes {
    public struct ContentState: Codable, Hashable {
        var currentStop: String
        var etaMinutes: Int
        var delaySec: Int
    }

    var routeName: String
    var vehicleId: String
}

// Start tracking
let attributes = BusTrackingAttributes(routeName: "Route 4", vehicleId: "veh-123")
let initialState = BusTrackingAttributes.ContentState(
    currentStop: "8th St & Broadway",
    etaMinutes: 5,
    delaySec: 120
)

let activity = try Activity<BusTrackingAttributes>.request(
    attributes: attributes,
    contentState: initialState,
    pushType: nil
)

// Update position every 15 seconds
Task {
    while true {
        try await Task.sleep(nanoseconds: 15_000_000_000)
        let newState = // fetch from API
        await activity.update(using: newState)
    }
}
```

**Battery impact:** Moderate (test carefully with Instruments)

---

## 11. Final Recommendations

### Start Simple, Iterate Fast

**Phase 1 Priorities (Weeks 1-6):**
1. ✅ Route list with real-time grades
2. ✅ Map view with vehicle tracking
3. ✅ Stop predictions
4. ✅ Background refresh
5. ✅ Favorites (local storage)

**Defer to Phase 2:**
- Push notifications
- Widgets
- Apple Watch
- Trip planner
- Offline mode

---

### Backend Changes: Minimal & Additive

**What to add:**
- 5 new API controllers in `src/Controller/Api/`
- CORS configuration
- Rate limiting (optional but recommended)
- 1 new repository method (`StopRepository::findByRoute()`)

**What NOT to change:**
- Existing scheduler logic
- Python sidecar
- Redis data structure
- PostgreSQL schema (no migrations needed for v1)
- Web app functionality

---

### Cost-Conscious by Default

**Target spend:** <$5/month incremental

**Key savings:**
- Client caching (5 min routes, 15 sec vehicles)
- CloudFront edge caching (global CDN)
- No new infrastructure (Lambda, API Gateway, etc.)
- Reuse existing ECS tasks

**Monitoring:**
- Set AWS billing alert at $100/month
- Monitor ALB request count in CloudWatch
- Track CloudFront cache hit ratio

---

### TestFlight Path

**Timeline:**
- **Week 6:** Internal beta (5-10 testers)
- **Week 8:** Public beta (100+ testers)
- **Week 10:** App Store submission
- **Week 11:** App Store approval (if no rejections)

**Critical for approval:**
- Works on iPhone SE (small screen, low memory)
- Dark mode support
- Accessibility labels (VoiceOver)
- Privacy policy URL in Info.plist
- No crashes on launch (< 1% crash rate)

---

## 12. Open Questions & Decisions Needed

### Q1: Should we expose AI insights in the mobile app?

**Option A:** Yes, add `/api/v1/insights` endpoint
- **Pros:** Differentiated feature, leverages existing OpenAI integration
- **Cons:** Increases API payload size, may not fit mobile UX, adds ~200ms latency

**Option B:** No, keep insights web-only for now
- **Pros:** Faster initial launch, simpler API, better mobile performance
- **Cons:** Feature parity gap with web app

**Recommendation:** Defer to Phase 2; focus on core transit features first. Collect user feedback on whether insights are valuable on mobile.

---

### Q2: Should iOS app support route shapes (polylines on map)?

**Current state:** Backend doesn't store GTFS `shapes.txt`

**Option A:** Add shape storage to PostgreSQL
```sql
CREATE TABLE shape (
    id SERIAL PRIMARY KEY,
    route_id INT REFERENCES route(id),
    shape_pt_lat DECIMAL(10, 8),
    shape_pt_lon DECIMAL(11, 8),
    shape_pt_sequence INT
);
```
- **Pros:** Beautiful map visualization, accurate route paths
- **Cons:** 50-100 MB additional DB storage, complex API response, slower map rendering

**Option B:** Use straight lines between stops
- **Pros:** Zero backend work, simple implementation, fast rendering
- **Cons:** Less accurate map representation, may confuse users

**Recommendation:** Start with Option B (straight lines). Add shapes in Phase 2 if user feedback demands it.

---

### Q3: Analytics & crash reporting?

**Options:**
- **Google Analytics for Firebase** — Free, comprehensive, heavy SDK (~5 MB)
- **Mixpanel** — Free tier, lighter SDK, good event tracking
- **TelemetryDeck** — Privacy-focused, paid ($10/month), GDPR-compliant
- **Apple Analytics only** — Free, basic, built-in to App Store Connect

**Recommendation:** Start with Apple Analytics (zero setup, privacy-friendly). Add TelemetryDeck or Mixpanel in Phase 2 if more granular analytics are needed.

---

### Q4: Should we support landscape orientation?

**Option A:** Portrait only (lock orientation)
- **Pros:** Simpler UI design, consistent UX
- **Cons:** Less flexible, some users prefer landscape

**Option B:** Support both orientations
- **Pros:** Better UX on large iPhones (Pro Max), iPad support
- **Cons:** More UI testing, potential layout bugs

**Recommendation:** Portrait only for v1.0. Add landscape support in Phase 2 after collecting user feedback.

---

## 13. Success Metrics

### Launch Targets (3 months post-release)

| Metric | Target | Measurement |
|--------|--------|-------------|
| **Downloads** | 1,000+ | App Store Connect Analytics |
| **Active Users (30-day)** | 500+ | App Store Connect Analytics |
| **App Store Rating** | 4.0+ stars | App Store reviews |
| **Crash Rate** | <5% | Xcode Organizer crash reports |
| **7-Day Retention** | >70% | App Store Connect Analytics |
| **API Response Time** | <200ms p95 | CloudWatch ALB metrics |
| **Background Refresh Success** | >80% | iOS Settings > Background App Refresh |

### User Feedback Goals

- 10+ positive reviews mentioning real-time accuracy
- 5+ feature requests for Phase 2 features
- <3 one-star reviews related to bugs

---

## Conclusion

This plan delivers a **production-ready iOS app in 6 weeks** by:

1. ✅ Reusing 90% of existing backend infrastructure
2. ✅ Adding 5 minimal JSON APIs (no architectural changes)
3. ✅ Building a native SwiftUI app with aggressive caching
4. ✅ Keeping incremental AWS costs under $5/month
5. ✅ Setting up CI/CD for automated TestFlight deploys

**Implementation Path:**
- **Weeks 1-2:** Backend API development + testing
- **Weeks 3-4:** iOS app foundation (routes list, data layer)
- **Week 5:** MapKit integration (real-time vehicle tracking)
- **Week 6:** Polish + TestFlight beta
- **Weeks 7-8:** Public beta + bug fixes
- **Weeks 9-10:** App Store submission + launch

**Phase 2 (Optional):**
- Push notifications for route delays
- Home screen widgets
- Apple Watch companion
- Trip planner
- Offline mode

**Phase 3 (Multi-City):**
- Add Regina, Winnipeg, Calgary
- ~$15/month incremental cost per city
- Shared codebase (iOS + backend)

**Success Factors:**
- Leverage existing, proven backend
- Native iOS performance
- Cost-conscious architecture
- Iterative development (ship v1.0 fast, iterate based on feedback)

**Next Steps:**
1. ✅ Review and approve this plan
2. Create GitHub project board with milestones
3. Set up Xcode project + App Store Connect account
4. Start Week 1: Backend API development

The foundation is solid. The backend is proven. The iOS app will be a natural extension of what already works. **Let's ship it.** 🚀

---

**Document Version:** 1.0
**Last Updated:** 2025-10-18
**Author:** Claude (Anthropic)
**Status:** Implementation Ready
