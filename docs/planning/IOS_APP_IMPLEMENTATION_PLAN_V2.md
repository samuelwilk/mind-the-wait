# Mind-the-Wait iOS App Implementation Plan v2.0

> **📋 STATUS: IMPLEMENTATION READY** | Aggressive 5-week timeline to TestFlight
>
> **Priority:** Critical (revenue-generating)
> **Estimated Effort:** 5 weeks to TestFlight, +2 weeks to multi-city App Store launch
> **Target Devices:** iPhone 14 Pro+ (Dynamic Island), iPhone 11+ (standard notifications)
> **Last Updated:** 2025-10-19

## Executive Summary

**Vision:** A beautiful, real-time transit monitoring app featuring 3D route visualization and Live Activities in the Dynamic Island. No other transit app does this.

**Unique Value Propositions:**
1. **Live 3D Route Visualization** - See all buses moving along the route in real-time with mid-century modern aesthetic
2. **Dynamic Island Integration** - Live countdown timer in the Dynamic Island (iPhone 14 Pro+)
3. **Performance Grading** - A-F scores for routes (only app that does this)
4. **Historical Context** - 90-day trend data for Premium subscribers

**Monetization:**
- Free tier: Browse routes, basic predictions, no favorites
- Premium ($2.99/month or $24.99/year): 3D visualization, Dynamic Island, unlimited favorites, 90-day history

**Multi-City Strategy:**
- Week 1-5: Build full featured app
- Week 5: TestFlight with Saskatoon
- Week 6-7: Add Regina, Winnipeg, Calgary/Edmonton
- Week 7: Submit to App Store with 4 cities
- Month 2+: Expand to 10+ Canadian cities, then US markets

**Revenue Target:**
- 15,000-20,000 users across Canadian metros
- 5% paid conversion = 750-1,000 subscribers
- **$1,600-$2,100/month revenue** (after Apple's 30% cut)
- Break-even on infrastructure at ~35 subscribers

---

## What's Already Implemented ✅

Based on `/Users/sam/Repos/MindTheWait`, the following is complete:

### ✅ Foundation (Complete)
- Xcode project structure
- App configuration with environment-based API URLs
- Color+Route extension (hex parsing)
- Date+Formatting extension

### ✅ Models (Complete)
- `Route.swift` - with grade colors and UI color parsing
- `Vehicle.swift` - with coordinate conversion
- `Stop.swift` - with coordinate properties
- `Prediction.swift` - with countdown calculations
- `RouteDetail.swift` - for detailed route views

### ✅ Services (Complete)
- `APIClient.swift` - Full implementation with all endpoints
  - `fetchRoutes()`
  - `fetchRouteDetail(routeId:)`
  - `fetchStops(routeId:)`
  - `fetchStopPredictions(stopId:)`
  - `fetchVehicles(routeId:)`
- `CacheManager.swift` - File-based JSON caching
- `BackgroundRefreshService.swift` - Background task scheduling

### ✅ ViewModels (Complete)
- `RouteListViewModel.swift` - With sorting, filtering, cache integration
- `MapViewModel.swift` - Vehicle tracking with 15-second refresh
- `StopDetailViewModel.swift` - Prediction fetching

### ✅ Views (Partial)
- `RouteListView.swift` - List of routes with search/sort
- `RouteDetailView.swift` - Route details with stats
- `MapView.swift` - Basic MapKit integration
- Components:
  - `RouteCard.swift` - Route list item
  - `GradeBadge.swift` - A-F grade display
  - `VehicleAnnotation.swift` - Map annotations

### ❌ Not Yet Implemented
- 3D route visualization (SceneKit integration)
- Live Activities (Dynamic Island)
- Home screen widgets
- City picker
- Multi-city support
- Premium subscription flow
- Favorites persistence
- Historical data views

---

## 5-Week Implementation Timeline

### **Week 1: 3D Visualization Foundation**

#### Day 1: SceneKit Scene Setup
**Goal:** Get a basic 3D scene rendering

**Tasks:**
- [ ] Create `SceneKit/` folder structure
- [ ] Implement `RoutePathGenerator.swift`
  - Coordinate conversion (lat/lon → local 2D)
  - Bézier curve generation from stop array
- [ ] Create `SceneConfigurator.swift`
  - Scene graph structure
  - Camera setup (isometric view, 15-20° tilt)
  - Lighting configuration
- [ ] Test with hardcoded Saskatoon Route 16 data

**Deliverables:**
```swift
// RoutePathGenerator.swift
struct RoutePathGenerator {
    func generatePath(stops: [Stop]) -> UIBezierPath
    func convertToLocal(lat: Double, lon: Double, bounds: GeoBounds) -> CGPoint
}

// SceneConfigurator.swift
class SceneConfigurator {
    func createScene(route: Route, stops: [Stop]) -> SCNScene
    func setupCamera() -> SCNNode
    func setupLighting() -> SCNNode
}
```

#### Day 2: Route Path Rendering
**Goal:** See a route path rendered in 3D

**Tasks:**
- [ ] Create `RoutePathNode.swift`
  - SCNShape with path geometry
  - Mid-century color palette (#2C3E50 charcoal)
  - 4pt width, smooth curves
- [ ] Create `StopNode.swift`
  - Circle markers for stops
  - Pulsing animation for favorite stop
  - Label on tap/hover
- [ ] Test rendering Route 16 path with all stops

**Deliverables:**
```swift
// RoutePathNode.swift
class RoutePathNode: SCNNode {
    init(path: UIBezierPath, color: UIColor)
}

// StopNode.swift
class StopNode: SCNNode {
    init(stop: Stop, isFavorite: Bool)
    func startPulseAnimation()
}
```

#### Day 3: Vehicle Node Implementation
**Goal:** Render 3D vehicles on route

**Tasks:**
- [ ] Create `VehicleNode.swift`
  - Simple capsule geometry (40pt × 20pt)
  - Color-coded by status (green/yellow/red)
  - Shadow effect
- [ ] Implement vehicle positioning algorithm
  - Snap to nearest route segment
  - Calculate rotation to align with tangent
  - Atmospheric perspective (fade distant vehicles)
- [ ] Test with live vehicle data from API

**Deliverables:**
```swift
// VehicleNode.swift
class VehicleNode: SCNNode {
    init(vehicle: Vehicle, routePath: [CGPoint])
    func animateToPosition(_ newPosition: SCNVector3, duration: TimeInterval)
    func updateStatus(color: UIColor)
}

// VehiclePositioner.swift
struct VehiclePositioner {
    func positionOnPath(vehicle: Vehicle, routePath: [CGPoint]) -> (position: SCNVector3, rotation: Float)
}
```

#### Day 4: UIViewRepresentable Integration
**Goal:** Embed SceneKit in SwiftUI

**Tasks:**
- [ ] Create `RouteVisualization3DView.swift`
  - UIViewRepresentable wrapper for SCNView
  - Bind to ViewModel
  - Handle scene updates
- [ ] Create `RouteVisualizationViewModel.swift`
  - Observable state management
  - 30-second polling loop
  - Vehicle position interpolation
- [ ] Test in SwiftUI preview

**Deliverables:**
```swift
// RouteVisualization3DView.swift
struct RouteVisualization3DView: UIViewRepresentable {
    @Bindable var viewModel: RouteVisualizationViewModel

    func makeUIView(context: Context) -> SCNView
    func updateUIView(_ sceneView: SCNView, context: Context)
}

// RouteVisualizationViewModel.swift
@Observable
class RouteVisualizationViewModel {
    var vehicles: [Vehicle] = []
    var routePath: [Stop] = []
    var favoriteStop: Stop?
    var cameraRotation: SIMD3<Float> = .zero
    var isLoading: Bool = false

    func startLiveUpdates() async
    func stopLiveUpdates()
}
```

#### Day 5: Camera Interaction
**Goal:** Add gesture controls

**Tasks:**
- [ ] Implement drag gesture for camera rotation
  - ±15° limits on X and Y axes
  - Spring animation when released
- [ ] Add pinch-to-zoom (optional)
- [ ] Test on device (simulator gestures are limited)

**Deliverables:**
```swift
// CameraController.swift
class CameraController {
    func handleDrag(_ translation: CGSize)
    func resetToDefault(animated: Bool)
}
```

**Week 1 Milestone:** Can view Route 16 in 3D with live vehicle positions updating every 30 seconds.

---

### **Week 2: UI Polish & Landing View**

#### Day 1-2: Main Landing View
**Goal:** Beautiful full-screen 3D visualization

**Tasks:**
- [ ] Create `LiveRouteView.swift`
  - Header with route name/color
  - 3D scene (full height)
  - Countdown panel overlay
- [ ] Apply mid-century modern styling
  - Background: #F5F1E8 (warm cream)
  - Typography: SF Pro Rounded
  - Spacing: generous padding
- [ ] Add loading states
  - Skeleton route line
  - "Fetching live positions..." message
- [ ] Add error states
  - No vehicles: "No vehicles currently tracked"
  - API failure: Retry button

**Deliverables:**
```swift
// LiveRouteView.swift
struct LiveRouteView: View {
    @State private var viewModel: RouteVisualizationViewModel

    var body: some View {
        ZStack {
            Color.warmCream.ignoresSafeArea()

            VStack {
                routeHeader
                RouteVisualization3DView(viewModel: viewModel)
                CountdownPanel(predictions: viewModel.nextArrivals)
            }
        }
    }
}
```

#### Day 3: Countdown Panel
**Goal:** Show next 3 arrivals at favorite stop

**Tasks:**
- [ ] Create `CountdownPanel.swift`
  - Next 3 arrival times
  - Countdown timer (updates every second)
  - Confidence badges (HIGH/MEDIUM/LOW)
  - Bus icons
- [ ] Create `CountdownTimer` observable
  - Tick every second
  - Calculate minutes:seconds from timestamp
- [ ] Integrate with API predictions

**Deliverables:**
```swift
// CountdownPanel.swift
struct CountdownPanel: View {
    let predictions: [Prediction]
    @State private var currentTime = Date()

    var body: some View {
        VStack {
            ForEach(predictions) { prediction in
                HStack {
                    Image(systemName: "bus.fill")
                    Text(formatCountdown(prediction.arrivalTime))
                        .font(.system(.title2, design: .monospaced))
                    ConfidenceBadge(confidence: prediction.confidence)
                }
            }
        }
        .onReceive(Timer.publish(every: 1, on: .main, in: .common).autoconnect()) { _ in
            currentTime = Date()
        }
    }
}
```

#### Day 4: Animation Polish
**Goal:** Smooth, delightful interactions

**Tasks:**
- [ ] Implement smooth vehicle movement
  - 30-second linear interpolation
  - No teleporting
- [ ] Add stop marker pulse animation
- [ ] Add camera "breathing" (subtle bob when idle)
- [ ] Test on iPhone 11 (slowest supported device)

**Deliverables:**
- Vehicles glide smoothly along route
- Favorite stop pulses gently
- No dropped frames (<16ms render time)

#### Day 5: Integration Testing
**Goal:** Everything works together

**Tasks:**
- [ ] Test with all 22 Saskatoon routes
- [ ] Test with 0 vehicles (empty state)
- [ ] Test with 20+ vehicles (performance)
- [ ] Test network errors (airplane mode)
- [ ] Test memory usage (no leaks)

**Week 2 Milestone:** Beautiful, functional 3D landing view with live data.

---

### **Week 3: Live Activities (Dynamic Island)**

#### Day 1: Live Activities Setup
**Goal:** Configure project for Live Activities

**Tasks:**
- [ ] Add Live Activity target to Xcode project
- [ ] Configure Info.plist
  - `NSSupportsLiveActivities = YES`
  - Background modes: processing, fetch
- [ ] Create `LiveActivityAttributes.swift`
  - Define static attributes (route info)
  - Define dynamic content state (countdown, health)

**Deliverables:**
```swift
// LiveActivityAttributes.swift
import ActivityKit

struct RouteTrackingAttributes: ActivityAttributes {
    public struct ContentState: Codable, Hashable {
        var nextArrivalSeconds: Int
        var healthPercent: Int
        var activeVehicles: Int
        var timestamp: Date
    }

    var routeName: String
    var routeColor: String
}
```

#### Day 2: Live Activity UI
**Goal:** Design Dynamic Island + Lock Screen views

**Tasks:**
- [ ] Create `LiveActivityView.swift`
  - Compact Leading: Route badge
  - Compact Trailing: Countdown timer
  - Minimal: Route name + countdown
  - Expanded: Route viz + next 3 arrivals
- [ ] Create `LockScreenLiveActivityView.swift`
  - Countdown + route name
  - Health percentage indicator

**Deliverables:**
```swift
// LiveActivityView.swift
struct RouteTrackingLiveActivity: Widget {
    var body: some WidgetConfiguration {
        ActivityConfiguration(for: RouteTrackingAttributes.self) { context in
            // Lock screen view
            LockScreenLiveActivityView(context: context)
        } dynamicIsland: { context in
            DynamicIsland {
                // Expanded view
                DynamicIslandExpandedRegion(.leading) {
                    Text(context.attributes.routeName)
                }
                DynamicIslandExpandedRegion(.trailing) {
                    Text("\(context.state.nextArrivalSeconds / 60) min")
                }
            } compactLeading: {
                Text(context.attributes.routeName)
            } compactTrailing: {
                Text("\(context.state.nextArrivalSeconds / 60)")
            } minimal: {
                Text("\(context.state.nextArrivalSeconds / 60)")
            }
        }
    }
}
```

#### Day 3: Live Activity Lifecycle
**Goal:** Start/update/end Live Activities

**Tasks:**
- [ ] Create `LiveActivityManager.swift`
  - Start tracking route
  - Update every 30 seconds via background task
  - End when user dismisses or route inactive
- [ ] Implement push token handling
  - Register device for push updates
  - Send token to backend
- [ ] Handle stale state (mark as stale after 5 minutes)

**Deliverables:**
```swift
// LiveActivityManager.swift
class LiveActivityManager {
    static let shared = LiveActivityManager()

    func startTracking(route: Route, favoriteStop: Stop) async throws
    func updateActivity(nextArrival: Int, health: Int) async
    func endTracking() async
}
```

#### Day 4: Background Updates
**Goal:** Keep Live Activity fresh

**Tasks:**
- [ ] Configure background processing
  - BGProcessingTask for 30-second updates
  - URLSession with background configuration
- [ ] Implement update loop
  - Fetch `/api/v1/live-activity/route/{id}`
  - Update Activity content state
  - Schedule next update
- [ ] Handle iOS throttling gracefully

**Deliverables:**
```swift
// BackgroundActivityUpdater.swift
class BackgroundActivityUpdater {
    func scheduleUpdate(in seconds: TimeInterval)
    func performUpdate() async
}
```

#### Day 5: Testing & Edge Cases
**Goal:** Bulletproof Live Activities

**Tasks:**
- [ ] Test on iPhone 14 Pro (Dynamic Island)
- [ ] Test on iPhone 13 (standard notification)
- [ ] Test battery impact (should be <2% per hour)
- [ ] Test with airplane mode (graceful degradation)
- [ ] Test with app killed (Activity persists)

**Week 3 Milestone:** Live Activities working on Dynamic Island with real-time countdown.

---

### **Week 4: Widgets & Multi-City Support**

#### Day 1-2: Home Screen Widgets
**Goal:** 3 widget sizes

**Tasks:**
- [ ] Create widget extension target
- [ ] Create `LiveRouteWidgetProvider.swift`
  - Timeline generation (30-second refresh)
  - Fetch data in background
- [ ] Build Small Widget
  - Route name + next arrival countdown
- [ ] Build Medium Widget
  - Route + next 3 arrivals
- [ ] Build Large Widget
  - Simplified 2D route viz + countdowns
  - Use SwiftUI Canvas (not SceneKit)

**Deliverables:**
```swift
// LiveRouteWidget.swift
struct LiveRouteWidget: Widget {
    let kind = "LiveRouteWidget"

    var body: some WidgetConfiguration {
        StaticConfiguration(kind: kind, provider: Provider()) { entry in
            LiveRouteWidgetEntryView(entry: entry)
        }
        .supportedFamilies([.systemSmall, .systemMedium, .systemLarge])
    }
}

// Widget sizes
struct SmallWidgetView: View { /* Countdown only */ }
struct MediumWidgetView: View { /* Route + 3 arrivals */ }
struct LargeWidgetView: View { /* 2D viz + arrivals */ }
```

#### Day 3: City Picker
**Goal:** Multi-city support

**Tasks:**
- [ ] Create `CityPickerView.swift`
  - List of cities from `/api/v1/cities`
  - Store selection in `@AppStorage`
  - Show on first launch
- [ ] Create `City.swift` model
- [ ] Update APIClient to include city parameter
  - `fetchRoutes(city: "saskatoon")`
  - `fetchVehicles(city: "saskatoon")`
- [ ] Update all ViewModels to use selected city

**Deliverables:**
```swift
// CityPickerView.swift
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
    }
}
```

#### Day 4: Favorites System
**Goal:** Persist user favorites

**Tasks:**
- [ ] Create `FavoritesManager.swift`
  - Store in UserDefaults (or Core Data if >10 favorites)
  - Sync via App Groups (share with widgets)
- [ ] Add "Favorite" button to route detail
- [ ] Filter route list to show favorites first
- [ ] Widget uses favorite route by default

**Deliverables:**
```swift
// FavoritesManager.swift
class FavoritesManager: ObservableObject {
    @Published var favoriteRoutes: Set<String> = []

    func addFavorite(routeId: String)
    func removeFavorite(routeId: String)
    func isFavorite(routeId: String) -> Bool
}
```

#### Day 5: Premium Subscription Setup
**Goal:** Configure StoreKit

**Tasks:**
- [ ] Create in-app purchases in App Store Connect
  - Monthly: $2.99/month
  - Annual: $24.99/year (save 30%)
- [ ] Implement `SubscriptionManager.swift`
  - Purchase flow
  - Restore purchases
  - Verify receipt
- [ ] Create paywall view
  - Show before accessing Premium features
  - Free trial: 7 days

**Deliverables:**
```swift
// SubscriptionManager.swift
class SubscriptionManager: ObservableObject {
    @Published var isPremium: Bool = false

    func purchase(productId: String) async throws
    func restorePurchases() async
}

// PaywallView.swift
struct PaywallView: View {
    // Feature comparison, pricing, purchase buttons
}
```

**Week 4 Milestone:** Widgets working, multi-city support, subscription configured.

---

### **Week 5: TestFlight & Polish**

#### Day 1: App Icon & Branding
**Goal:** Professional visual identity

**Tasks:**
- [ ] Design app icon (1024×1024)
  - Mid-century modern aesthetic
  - Bus/route motif
  - Vibrant color
- [ ] Create launch screen
- [ ] Add app name "Mind the Wait"
- [ ] Screenshots for App Store (6.7", 6.5", 5.5")

#### Day 2: Onboarding Flow
**Goal:** First-time user experience

**Tasks:**
- [ ] Create `OnboardingView.swift`
  - 3 screens:
    1. "Track your bus in real-time"
    2. "See where delays happen"
    3. "Get countdowns in Dynamic Island"
  - City selection at end
- [ ] Show only on first launch
- [ ] Skip button option

**Deliverables:**
```swift
// OnboardingView.swift
struct OnboardingView: View {
    @AppStorage("hasCompletedOnboarding") private var hasCompleted = false
    @State private var currentPage = 0

    var body: some View {
        TabView(selection: $currentPage) {
            OnboardingPage1()
            OnboardingPage2()
            OnboardingPage3()
            CityPickerView()
        }
    }
}
```

#### Day 3: Accessibility & Dark Mode
**Goal:** Inclusive design

**Tasks:**
- [ ] Add VoiceOver labels to all interactive elements
- [ ] Test with VoiceOver enabled
- [ ] Ensure color contrast meets WCAG AA
- [ ] Test dark mode
  - Invert background colors
  - Reduce visual intensity
- [ ] Add Dynamic Type support (text scaling)

#### Day 4: TestFlight Build
**Goal:** Submit to TestFlight

**Tasks:**
- [ ] Run all tests (XCTest suite)
- [ ] Fix any crashes
- [ ] Verify no memory leaks (Instruments)
- [ ] Configure bundle ID: `com.mindthewait.ios`
- [ ] Set version: 1.0.0 (1)
- [ ] Build and archive
- [ ] Submit to TestFlight internal testing
- [ ] Write TestFlight release notes

**Deliverables:**
- TestFlight build available to internal testers

#### Day 5: Beta Testing
**Goal:** Get real user feedback

**Tasks:**
- [ ] Invite 10-20 Saskatoon transit riders
  - Post on r/saskatoon
  - Contact local transit advocacy groups
  - Friends/family who ride transit
- [ ] Monitor crash reports
- [ ] Collect feedback via TestFlight feedback form
- [ ] Prioritize critical bugs

**Week 5 Milestone:** TestFlight beta live with 10-20 testers providing feedback.

---

### **Week 6-7: Multi-City Expansion & App Store Launch**

#### Week 6: Backend Multi-City Setup
**Day 1-2:**
- [ ] Implement backend changes from `BACKEND_API_CHANGES_FOR_IOS.md`
  - City database schema
  - City API endpoints
  - Redis namespacing
  - Live Activity endpoints

**Day 3-4:**
- [ ] Load GTFS data for 4 cities:
  - Regina
  - Winnipeg
  - Calgary OR Edmonton
- [ ] Test APIs with each city
- [ ] Verify realtime feeds work

**Day 5:**
- [ ] Deploy backend changes to production
- [ ] Monitor logs for errors
- [ ] Smoke test all 4 cities

#### Week 7: iOS Multi-City Integration & Submission
**Day 1:**
- [ ] Test iOS app with all 4 cities
- [ ] Fix any city-specific bugs
- [ ] Verify widget works for each city

**Day 2-3:**
- [ ] Create App Store listing
  - Title: "Mind the Wait - Transit Tracker"
  - Subtitle: "Real-time bus tracking with 3D visualization"
  - Description (full marketing copy)
  - Keywords: transit, bus, real-time, tracking, saskatoon, calgary, winnipeg
  - Privacy policy URL
  - Support URL
- [ ] Record app preview video (30 seconds)
- [ ] Take final screenshots (all device sizes)

**Day 4:**
- [ ] Submit to App Store review
- [ ] Monitor review status
- [ ] Respond to any feedback within 24 hours

**Day 5:**
- [ ] (Waiting for review)
- [ ] Prepare launch materials
  - Press release
  - Social media posts
  - Reddit announcement posts

**Milestone:** App submitted to App Store with 4 Canadian cities.

---

## Technical Architecture

### Data Flow

```
Backend API (Symfony)
    ↓
APIClient (URLSession)
    ↓
CacheManager (File-based JSON)
    ↓
ViewModel (@Observable)
    ↓
SwiftUI View
    ↓
SceneKit 3D Scene / Widget / Live Activity
```

### State Management

Using Swift's new `@Observable` macro (iOS 17+):

```swift
@Observable
class RouteVisualizationViewModel {
    var vehicles: [Vehicle] = []
    var routePath: [Stop] = []
    var predictions: [Prediction] = []
    var isLoading: Bool = false
    var errorMessage: String?

    // Computed
    var nextArrivals: [Prediction] {
        predictions.prefix(3).map { $0 }
    }
}
```

### Caching Strategy

1. **Route list**: 5-minute cache
2. **Vehicle positions**: No cache (always fresh)
3. **Stop predictions**: No cache (always fresh)
4. **City list**: 24-hour cache
5. **Route details**: 10-minute cache

### Performance Targets

- **App launch**: <2 seconds to first screen
- **3D scene render**: <16ms per frame (60fps)
- **Network requests**: <200ms p95
- **Widget update**: <3 seconds background execution
- **Live Activity update**: <1 second background execution
- **Battery drain**: <2% per hour with Live Activity active

---

## Project Structure

```
MindTheWait/
├── App/
│   ├── MindTheWaitApp.swift           ✅ Done
│   ├── AppDelegate.swift              ✅ Done
│   └── AppConfig.swift                ✅ Done
│
├── Models/
│   ├── Route.swift                    ✅ Done
│   ├── Vehicle.swift                  ✅ Done
│   ├── Stop.swift                     ✅ Done
│   ├── Prediction.swift               ✅ Done
│   ├── RouteDetail.swift              ✅ Done
│   └── City.swift                     ❌ TODO
│
├── Services/
│   ├── APIClient.swift                ✅ Done
│   ├── CacheManager.swift             ✅ Done
│   ├── BackgroundRefreshService.swift ✅ Done
│   ├── LiveActivityManager.swift      ❌ TODO
│   ├── FavoritesManager.swift         ❌ TODO
│   └── SubscriptionManager.swift      ❌ TODO
│
├── ViewModels/
│   ├── RouteListViewModel.swift       ✅ Done
│   ├── MapViewModel.swift             ✅ Done
│   ├── StopDetailViewModel.swift      ✅ Done
│   └── RouteVisualizationViewModel.swift ❌ TODO
│
├── Views/
│   ├── RouteListView.swift            ✅ Done
│   ├── RouteDetailView.swift          ✅ Done
│   ├── MapView.swift                  ✅ Done
│   ├── LiveRouteView.swift            ❌ TODO
│   ├── RouteVisualization3DView.swift ❌ TODO
│   ├── CountdownPanel.swift           ❌ TODO
│   ├── CityPickerView.swift           ❌ TODO
│   ├── OnboardingView.swift           ❌ TODO
│   ├── PaywallView.swift              ❌ TODO
│   └── Components/
│       ├── RouteCard.swift            ✅ Done
│       ├── GradeBadge.swift           ✅ Done
│       ├── VehicleAnnotation.swift    ✅ Done
│       └── ConfidenceBadge.swift      ❌ TODO
│
├── SceneKit/
│   ├── SceneConfigurator.swift        ❌ TODO
│   ├── RoutePathGenerator.swift       ❌ TODO
│   ├── RoutePathNode.swift            ❌ TODO
│   ├── VehicleNode.swift              ❌ TODO
│   ├── StopNode.swift                 ❌ TODO
│   ├── VehiclePositioner.swift        ❌ TODO
│   └── CameraController.swift         ❌ TODO
│
├── Extensions/
│   ├── Color+Route.swift              ✅ Done
│   └── Date+Formatting.swift          ✅ Done
│
├── LiveActivity/
│   ├── LiveActivityAttributes.swift   ❌ TODO
│   ├── LiveActivityView.swift         ❌ TODO
│   └── BackgroundActivityUpdater.swift ❌ TODO
│
└── Widgets/
    ├── LiveRouteWidget.swift          ❌ TODO
    └── LiveRouteWidgetProvider.swift  ❌ TODO
```

**Progress:** 22 files done, ~25 files remaining

---

## Device Support Strategy

### Primary Targets (Optimized)
- **iPhone 14 Pro / 15 Pro** - Full Dynamic Island experience
- **iPhone 13 / 14** - Standard Live Activities notification
- **iPhone 11 / 12** - All features except Live Activities

### Testing Devices
Minimum required for testing:
- iPhone 11 (oldest supported, slowest performance)
- iPhone 14 Pro (Dynamic Island testing)
- iPad (optional, not optimized but should work)

### iOS Version Support
- **Minimum:** iOS 16.0 (for Live Activities)
- **Recommended:** iOS 17.0+ (for @Observable macro)

### Why NOT support older iPhones?
- **Live Activities** are the killer differentiator (requires iOS 16+)
- iPhone X/XS (iOS 15) market share is <5% of transit riders
- Reduces testing burden significantly
- Faster development (use latest Swift features)

---

## Premium Feature Gating

### Free Tier
✅ Can use:
- Browse all routes with grades
- View basic route details
- See current vehicle positions on map
- Get basic arrival predictions
- **1 favorite route** (limited)

❌ Cannot use:
- 3D route visualization
- Live Activities (Dynamic Island)
- Home screen widgets
- Historical data (90 days)
- Unlimited favorites

### Premium ($2.99/month)
✅ Unlocks:
- **3D route visualization** (main differentiator)
- **Live Activities** in Dynamic Island
- **Home screen widgets** (all 3 sizes)
- **Unlimited favorite routes**
- **90-day historical trends**
- **Route comparison** (coming soon)
- **Priority support**

### Implementation

```swift
// Paywall trigger points
struct LiveRouteView: View {
    @EnvironmentObject var subscriptionManager: SubscriptionManager

    var body: some View {
        if subscriptionManager.isPremium {
            // Show 3D visualization
            RouteVisualization3DView(viewModel: viewModel)
        } else {
            // Show paywall
            PaywallView()
        }
    }
}
```

---

## Marketing & Launch Strategy

### Pre-Launch (Week 6)
- [ ] Create landing page: mindthewait.ca/ios
- [ ] Build email list (50+ signups)
- [ ] Contact local transit advocacy groups
- [ ] Reach out to tech bloggers in Canada

### Launch Day (Week 7)
- [ ] Post on r/saskatoon, r/regina, r/winnipeg, r/calgary
- [ ] Submit to Product Hunt
- [ ] Email list announcement
- [ ] Social media: Twitter, LinkedIn
- [ ] Press release to local news outlets

### Post-Launch (Week 8+)
- [ ] Monitor reviews, respond to all feedback
- [ ] Track analytics: downloads, conversions, retention
- [ ] A/B test pricing ($2.99 vs $1.99)
- [ ] Iterate based on user feedback
- [ ] Plan expansion to Vancouver, Toronto

---

## Risk Mitigation

### Technical Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| SceneKit performance on iPhone 11 | Medium | High | Test early, use LOD, limit vehicle count |
| Live Activities battery drain | Medium | Medium | Limit update frequency, profile with Instruments |
| App Store rejection | Low | High | Follow guidelines carefully, have privacy policy ready |
| Backend API downtime | Low | Critical | Graceful error handling, cache fallback |
| Dynamic Island not compelling | Medium | Medium | A/B test with and without in TestFlight |

### Business Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Low conversion to Premium | Medium | Critical | Launch with 7-day free trial, showcase features clearly |
| Insufficient downloads | Medium | High | Expand to 10+ cities within 3 months |
| Free alternatives (Transit app) | High | Medium | Differentiate with 3D viz and Dynamic Island |
| Seasonal usage drop (summer) | High | Low | Expect 30-40% drop in summer, plan for annual subscriptions |

---

## Success Metrics

### Week 5 (TestFlight)
- [ ] 10+ active testers
- [ ] <5% crash rate
- [ ] 4+ star rating from testers
- [ ] 3+ bugs fixed

### Week 7 (App Store Launch)
- [ ] 100+ downloads in first week
- [ ] <3% crash rate
- [ ] 4.0+ star average rating
- [ ] 2%+ conversion to Premium

### Month 2
- [ ] 1,000+ downloads across 4 cities
- [ ] 5%+ conversion rate
- [ ] 50+ paying subscribers ($105-150/month revenue)
- [ ] 7-day retention >40%

### Month 6
- [ ] 10,000+ downloads across 10+ cities
- [ ] 500+ paying subscribers ($1,050-1,500/month revenue)
- [ ] Break-even on infrastructure costs
- [ ] 4.5+ star average rating

---

## Cost Analysis

### Development Costs (Your Time)
- 5 weeks @ 3 hours/day = 75 hours
- Hourly rate: $0 (side project)
- **Total: $0**

### Infrastructure Costs (6 months)
- Current: $100/month × 6 = $600
- **Total: $600**

### Apple Costs
- Apple Developer Program: $99/year
- Apple's 30% cut of subscriptions
- **Total Year 1: $99 + ~$600 in fees = ~$700**

### Break-Even Analysis
- Need ~35 subscribers at $2.99/month to break even on infrastructure
- Need ~45 subscribers to break even on infrastructure + Apple fees
- Need ~100 subscribers to make $100/month profit

**Realistic Timeline to Break-Even:** 3-4 months after multi-city launch

---

## Next Steps

### Immediate (This Week)
1. Review this plan
2. Confirm backend API changes timeline
3. Start Week 1, Day 1: SceneKit scene setup

### Communication Protocol
- Daily check-in on progress
- Blockers raised immediately
- Quick decisions (no analysis paralysis)
- Ship imperfect, iterate fast

### Development Environment Setup
```bash
# iOS repo
cd /Users/sam/Repos/MindTheWait

# Run on simulator
open MindTheWait.xcodeproj
# Cmd+R to build and run

# Test on device
# Connect iPhone via USB
# Select device in Xcode
# Cmd+R
```

---

## Appendix: Code Snippets

### A. RoutePathGenerator Implementation

```swift
import UIKit

struct GeoBounds {
    let minLat: Double
    let maxLat: Double
    let minLon: Double
    let maxLon: Double
}

struct RoutePathGenerator {
    func generatePath(stops: [Stop]) -> UIBezierPath {
        let bounds = calculateBounds(stops: stops)
        let localPoints = stops.map { convertToLocal($0, bounds: bounds) }

        let path = UIBezierPath()
        guard let first = localPoints.first else { return path }

        path.move(to: first)

        for i in 1..<localPoints.count {
            let current = localPoints[i]
            let previous = localPoints[i - 1]
            let next = i + 1 < localPoints.count ? localPoints[i + 1] : nil

            let controlPoint = calculateControlPoint(
                previous: previous,
                current: current,
                next: next
            )

            path.addQuadCurve(to: current, controlPoint: controlPoint)
        }

        return path
    }

    func convertToLocal(_ stop: Stop, bounds: GeoBounds) -> CGPoint {
        let sceneWidth: CGFloat = 10.0
        let sceneHeight: CGFloat = 10.0

        let normalizedX = (stop.lon - bounds.minLon) / (bounds.maxLon - bounds.minLon)
        let normalizedY = (stop.lat - bounds.minLat) / (bounds.maxLat - bounds.minLat)

        return CGPoint(
            x: normalizedX * sceneWidth - sceneWidth / 2,
            y: normalizedY * sceneHeight - sceneHeight / 2
        )
    }

    private func calculateBounds(stops: [Stop]) -> GeoBounds {
        let lats = stops.map(\.lat)
        let lons = stops.map(\.lon)

        return GeoBounds(
            minLat: lats.min() ?? 0,
            maxLat: lats.max() ?? 0,
            minLon: lons.min() ?? 0,
            maxLon: lons.max() ?? 0
        )
    }

    private func calculateControlPoint(
        previous: CGPoint,
        current: CGPoint,
        next: CGPoint?
    ) -> CGPoint {
        if let next = next {
            // Use midpoint for smooth curves
            return CGPoint(
                x: (previous.x + current.x + next.x) / 3,
                y: (previous.y + current.y + next.y) / 3
            )
        } else {
            // Last point, use midpoint to previous
            return CGPoint(
                x: (previous.x + current.x) / 2,
                y: (previous.y + current.y) / 2
            )
        }
    }
}
```

### B. VehiclePositioner Implementation

```swift
import SceneKit

struct VehiclePositioner {
    func positionOnPath(
        vehicle: Vehicle,
        routePath: [CGPoint]
    ) -> (position: SCNVector3, rotation: Float) {
        let vehiclePoint = CGPoint(x: vehicle.longitude, y: vehicle.latitude)

        // Find closest segment
        var closestSegment = 0
        var closestDistance = Double.infinity

        for i in 0..<(routePath.count - 1) {
            let distance = pointToSegmentDistance(
                point: vehiclePoint,
                segmentStart: routePath[i],
                segmentEnd: routePath[i + 1]
            )

            if distance < closestDistance {
                closestDistance = distance
                closestSegment = i
            }
        }

        // Project onto segment
        let projected = projectPointOntoSegment(
            point: vehiclePoint,
            segmentStart: routePath[closestSegment],
            segmentEnd: routePath[closestSegment + 1]
        )

        // Calculate rotation
        let angle = atan2(
            routePath[closestSegment + 1].y - routePath[closestSegment].y,
            routePath[closestSegment + 1].x - routePath[closestSegment].x
        )

        return (
            position: SCNVector3(projected.x, 0.2, projected.y),
            rotation: Float(angle)
        )
    }

    private func pointToSegmentDistance(
        point: CGPoint,
        segmentStart: CGPoint,
        segmentEnd: CGPoint
    ) -> Double {
        let projected = projectPointOntoSegment(
            point: point,
            segmentStart: segmentStart,
            segmentEnd: segmentEnd
        )

        let dx = point.x - projected.x
        let dy = point.y - projected.y

        return sqrt(dx * dx + dy * dy)
    }

    private func projectPointOntoSegment(
        point: CGPoint,
        segmentStart: CGPoint,
        segmentEnd: CGPoint
    ) -> CGPoint {
        let dx = segmentEnd.x - segmentStart.x
        let dy = segmentEnd.y - segmentStart.y

        if dx == 0 && dy == 0 {
            return segmentStart
        }

        let t = max(0, min(1, (
            (point.x - segmentStart.x) * dx +
            (point.y - segmentStart.y) * dy
        ) / (dx * dx + dy * dy)))

        return CGPoint(
            x: segmentStart.x + t * dx,
            y: segmentStart.y + t * dy
        )
    }
}
```

---

**Document Version:** 2.0
**Last Updated:** 2025-10-19
**Status:** Implementation Ready
**Estimated Completion:** Week 7 (App Store submission)
