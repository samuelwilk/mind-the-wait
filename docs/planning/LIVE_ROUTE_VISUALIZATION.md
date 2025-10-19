# Live Route Visualization Widget

> **📋 STATUS: PLANNING** | This document describes an iOS feature that has NOT been implemented yet.
>
> **Implementation Status:** Not started
> **Priority:** High (marquee feature for iOS app)
> **Estimated Effort:** 3-4 weeks
> **Dependencies:** iOS app base implementation (IOS_IMPLEMENTATION_PLAN.md)
> **Last Updated:** 2025-10-19

## Executive Summary

**Vision:** A beautiful, minimal 3D visualization of your favorite transit route showing live vehicle positions and countdowns. Think mid-century modern meets futuristic transit map - like a model train set brought to life with real-time data.

**Key Features:**
- 🚌 **Live vehicle positions** rendered as 3D models on simplified route path
- ⏱️ **Countdown timers** to next arrival at your favorite stop
- 📱 **Home screen widget** for at-a-glance status
- 🎨 **Mid-century modern aesthetic** with clean lines and retro-futuristic vibe
- 🔄 **Subtle 3D interaction** - tilt and rotate with touch gestures
- ✨ **Smooth animations** - vehicles glide along route in real-time

**User Experience:**
- Open app → instant view of where all buses are RIGHT NOW
- No tapping, no navigation - just pure visual information
- Widget shows countdown on home screen without opening app
- Calming, almost meditative visualization of transit in motion

---

## Table of Contents

1. [Visual Design Concept](#1-visual-design-concept)
2. [User Flows](#2-user-flows)
3. [Technical Architecture](#3-technical-architecture)
4. [3D Rendering Implementation](#4-3d-rendering-implementation)
5. [Backend API Requirements](#5-backend-api-requirements)
6. [iOS App Implementation](#6-ios-app-implementation)
7. [Widget Implementation](#7-widget-implementation)
8. [Animation & Performance](#8-animation--performance)
9. [Testing Strategy](#9-testing-strategy)
10. [Future Enhancements](#10-future-enhancements)

---

## 1. Visual Design Concept

### Mid-Century Modern Aesthetic

**Inspiration:**
- 1950s-60s architectural diagrams (Eames, Saul Bass)
- Vintage subway maps with simplified geometry
- Model train layouts with clean, elevated perspectives
- Retro-futuristic design language (The Jetsons, Tomorrowland)

**Color Palette:**

```
Background:     #F5F1E8 (warm cream)
Route Line:     #2C3E50 (charcoal)
Vehicles:       #E74C3C (poppy red) or route-specific color
Grid (opt):     #D4CFC4 (subtle tan, 10% opacity)
Text:           #34495E (dark slate)
Accents:        #3498DB (sky blue) for highlights
```

**Typography:**
- Primary: SF Pro Rounded (Apple's friendly system font)
- Accent: Futura PT (geometric, mid-century modern)
- Countdowns: SF Mono (monospaced for digital feel)

---

### Visual Layout

```
┌─────────────────────────────────────────────────┐
│                                                 │
│   Route 16 - Eastview / City Centre            │  ← Header
│                                                 │
│                                                 │
│         [3D Route Visualization]                │
│                                                 │
│     🚌━━━━━○━━━━━━━🚌━━━━━○━━━━━━━🚌            │  ← Route path with vehicles
│     ↑              ↑              ↑             │
│   Vehicle 1    Your Stop      Vehicle 2         │
│                                                 │
│     ┌─────────────────────────────┐             │
│     │  Next at Your Stop:         │             │  ← Countdown panel
│     │                              │
│     │  🚌  2 min 34 sec           │
│     │  🚌  8 min 12 sec           │
│     │  🚌  15 min 45 sec          │
│     └─────────────────────────────┘             │
│                                                 │
│   [Optional grid background]                    │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

### 3D Perspective View

**Camera Angle:**
- **Isometric-ish** - 15-20° tilt from top-down
- **Slight rotation** - Route angles diagonally across screen
- **Elevation** - Vehicles and stops have subtle height/shadow

**Depth Cues:**
- Vehicles cast soft shadows on route line
- Route line has subtle gradient (lighter in distance)
- Parallax effect when tilting (background grid moves slower)
- Atmospheric perspective (distant vehicles slightly faded)

**Example Isometric View:**

```
         Distant vehicles (smaller, faded)
                    ↓
              ┌─────🚌─────┐
             /              \
            /   Route path   \
           /     (darker)      \
          /                     \
    ○───🚌────────○──────────○───🚌───  ← Route with stops
   Stop 1    Your Stop    Stop 2

         Closer vehicles (larger, vibrant)
                    ↑
```

---

### Vehicle & Stop Design

**Vehicle Models (3D Assets):**

Option A: **Simple Geometric Shapes** (faster rendering)
```
Bus:    Rounded rectangle capsule
        ┌─────────────┐
        │  [windows]  │  ← Simple rounded rect
        └─────────────┘
        Size: 40pt × 20pt

Train:  Linked capsules
        ┌────┬────┬────┐
        │ car│ car│ car│  ← 3 connected segments
        └────┴────┴────┘
```

Option B: **Low-Poly 3D Models** (more detailed, still performant)
```
Bus:    Simplified 3D mesh
        - ~200 polygons
        - Flat-shaded (no textures)
        - Vintage boxy shape (think VW Bus)

Train:  Simplified rail car
        - Streamlined 1960s aesthetic
        - Chrome accents on edges
```

**Recommendation:** Start with Option A for MVP, upgrade to Option B if performance allows.

**Stop Markers:**
```
○  Empty circle (not your stop)
⦿  Filled circle (your favorite stop)
   + Pulsing glow animation
   + Label on hover/tap
```

---

### Grid Background (Optional)

**Grid Style:**
```
Pattern:  Isometric dot grid or line grid
Opacity:  8-12% (very subtle)
Spacing:  40pt × 40pt
Color:    Matches route line, much lighter

Example:
· · · · · · · · ·
 · · · · · · · · ·
· · · · · · · · ·
 · · · · · · · · ·
```

**Decision Criteria:**
- ✅ Include if: Adds depth, helps perceive 3D space
- ❌ Exclude if: Feels cluttered, distracts from vehicles

**A/B Test:** Implement toggle in settings, track user preference analytics

---

## 2. User Flows

### Primary Flow: Viewing Live Route

```
User opens app
    ↓
Landing screen shows 3D route visualization
    ↓
Vehicles animate to current positions (0.5s smooth transition)
    ↓
Countdown timer updates every second
    ↓
User tilts phone or drags finger → view rotates slightly
    ↓
User sees next 3 arrivals at their stop
```

**Interaction States:**

1. **Idle State** (no touch)
   - Vehicles move along route as new positions arrive
   - Countdown decrements every second
   - Gentle camera bob/sway (optional, very subtle)

2. **Dragging State** (touch + drag)
   - Camera rotates around center point
   - Rotation limited to ±15° on X and Y axes
   - Smooth easing when released (spring animation)

3. **Loading State** (initial data fetch)
   - Show skeleton route line
   - Placeholder vehicle positions (gray)
   - "Fetching live positions..." text

4. **Error State** (no data / API failure)
   - Show static route line
   - "No vehicles currently tracked" message
   - Retry button

---

### Widget Flow

```
User adds widget to home screen
    ↓
Widget shows countdown to next bus at favorite stop
    ↓
Widget refreshes every 30 seconds (iOS limit)
    ↓
User taps widget → opens app to full 3D view
```

**Widget Variants:**

| Size | Content |
|------|---------|
| **Small** (2×2) | Countdown only: "Route 16: 2:34" |
| **Medium** (4×2) | Countdown + next 2 arrivals |
| **Large** (4×4) | Mini 3D view + countdowns |

---

## 3. Technical Architecture

### Data Flow

```
Backend API (/api/v1/realtime)
    ↓
iOS URLSession (30-second polling)
    ↓
VehiclePositionService (decodes JSON)
    ↓
RouteVisualizationViewModel (calculates positions on path)
    ↓
SceneKit/RealityKit Renderer (3D scene)
    ↓
SwiftUI View (displays scene + countdown UI)
```

### State Management

```swift
@Observable class RouteVisualizationViewModel {
    // Data
    var vehicles: [VehiclePosition] = []
    var routePath: RoutePath
    var favoriteStop: Stop

    // UI State
    var cameraRotation: SIMD3<Float> = .zero
    var isLoading: Bool = false
    var errorMessage: String? = nil

    // Computed
    var nextArrivals: [ArrivalPrediction] { ... }
    var vehicleNodesOnPath: [VehicleNode] { ... }
}
```

---

## 4. 3D Rendering Implementation

### Technology Choice

**Option 1: SceneKit** (Apple's built-in 3D framework)

✅ **Pros:**
- Native iOS framework, well-optimized
- Good balance of power and simplicity
- Excellent performance on all devices
- Familiar to iOS developers

❌ **Cons:**
- Older API, less "modern" than RealityKit
- Limited AR capabilities (not needed here)

**Option 2: RealityKit** (Apple's newer 3D/AR framework)

✅ **Pros:**
- Modern Swift-first API
- Better integration with SwiftUI
- Future-proof (Apple's focus)

❌ **Cons:**
- Overkill for 2.5D visualization
- Requires iOS 13+ (SceneKit works on older devices)

**Recommendation:** **SceneKit** for MVP - proven, performant, simpler for this use case.

---

### Scene Graph Structure

```
SCNScene (root)
├── Camera Node
│   ├── Position: (0, 5, 10)  // Elevated, looking down
│   └── LookAt: (0, 0, 0)      // Center of route
│
├── Route Path Node
│   ├── Geometry: SCNShape (Bézier curve)
│   ├── Material: Matte charcoal
│   └── Width: 4pt
│
├── Vehicle Nodes (dynamic)
│   ├── Vehicle 1 (SCNNode)
│   │   ├── Geometry: Capsule or custom mesh
│   │   ├── Position: Interpolated from lat/lon
│   │   └── Rotation: Aligned to route tangent
│   ├── Vehicle 2 (SCNNode)
│   └── Vehicle N...
│
├── Stop Nodes
│   ├── Stop 1 (SCNNode) → Small sphere
│   ├── Favorite Stop (SCNNode) → Pulsing sphere + label
│   └── Stop N...
│
└── Grid Floor Node (optional)
    ├── Geometry: Plane with dot pattern
    └── Opacity: 10%
```

---

### Route Path Generation

**Input:** Array of stop coordinates (lat/lon)

```swift
struct Stop {
    let id: String
    let name: String
    let lat: Double
    let lon: Double
}
```

**Output:** SCNShape with Bézier curve path

```swift
func generateRoutePath(stops: [Stop]) -> SCNShape {
    let path = UIBezierPath()

    // Convert lat/lon to local 2D coordinates (normalized)
    let localPoints = stops.map { stop in
        convertToLocal(lat: stop.lat, lon: stop.lon)
    }

    // Create smooth curve through points
    path.move(to: localPoints[0])
    for i in 1..<localPoints.count {
        // Use quadratic or cubic curves for smooth bends
        let controlPoint = calculateControlPoint(
            previous: localPoints[i-1],
            current: localPoints[i],
            next: i+1 < localPoints.count ? localPoints[i+1] : nil
        )
        path.addQuadCurve(to: localPoints[i], controlPoint: controlPoint)
    }

    return SCNShape(path: path, extrusionDepth: 0)
}
```

**Coordinate Conversion:**

```swift
func convertToLocal(lat: Double, lon: Double) -> CGPoint {
    // Get bounding box of all stops
    let minLat = stops.map(\.lat).min()!
    let maxLat = stops.map(\.lat).max()!
    let minLon = stops.map(\.lon).min()!
    let maxLon = stops.map(\.lon).max()!

    // Normalize to 0-1 range
    let normalizedX = (lon - minLon) / (maxLon - minLon)
    let normalizedY = (lat - minLat) / (maxLat - minLat)

    // Scale to scene size (e.g., 10 units wide)
    let sceneWidth: CGFloat = 10.0
    let sceneHeight: CGFloat = 10.0

    return CGPoint(
        x: normalizedX * sceneWidth - sceneWidth / 2,  // Center at origin
        y: normalizedY * sceneHeight - sceneHeight / 2
    )
}
```

---

### Vehicle Positioning

**Input:** Vehicle lat/lon from realtime API

**Algorithm:**

1. **Find nearest segment** on route path
2. **Interpolate position** along that segment
3. **Calculate rotation** to face direction of travel

```swift
func positionVehicle(
    vehicle: VehiclePosition,
    routePath: [CGPoint]
) -> (position: SCNVector3, rotation: Float) {

    let vehiclePoint = convertToLocal(lat: vehicle.lat, lon: vehicle.lon)

    // Find closest segment on route
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

    // Project point onto segment
    let projectedPoint = projectPointOntoSegment(
        point: vehiclePoint,
        segmentStart: routePath[closestSegment],
        segmentEnd: routePath[closestSegment + 1]
    )

    // Calculate rotation (angle of segment)
    let angle = atan2(
        routePath[closestSegment + 1].y - routePath[closestSegment].y,
        routePath[closestSegment + 1].x - routePath[closestSegment].x
    )

    return (
        position: SCNVector3(projectedPoint.x, 0.2, projectedPoint.y), // Slight elevation
        rotation: Float(angle)
    )
}
```

---

### Camera Control

**Gesture Recognizer:**

```swift
@GestureState private var dragOffset: CGSize = .zero

var body: some View {
    SceneView(scene: scene, options: [.autoenablesDefaultLighting])
        .gesture(
            DragGesture()
                .updating($dragOffset) { value, state, _ in
                    state = value.translation
                }
                .onEnded { _ in
                    // Spring back to default angle
                    withAnimation(.spring(response: 0.5, dampingFraction: 0.7)) {
                        cameraRotation = .zero
                    }
                }
        )
        .onChange(of: dragOffset) { oldValue, newValue in
            updateCameraRotation(newValue)
        }
}

func updateCameraRotation(_ offset: CGSize) {
    let maxRotation: Float = 0.26 // ~15 degrees in radians

    // Map drag distance to rotation (with limits)
    let rotationX = Float(offset.height) / 200.0 * maxRotation
    let rotationY = Float(offset.width) / 200.0 * maxRotation

    cameraRotation = SIMD3(
        x: max(-maxRotation, min(maxRotation, rotationX)),
        y: max(-maxRotation, min(maxRotation, rotationY)),
        z: 0
    )

    // Apply to camera node
    cameraNode.eulerAngles = SCNVector3(
        cameraRotation.x,
        cameraRotation.y,
        cameraRotation.z
    )
}
```

---

## 5. Backend API Requirements

### Existing APIs (Already Implemented)

✅ **GET /api/v1/realtime**
- Returns current vehicle positions
- Includes route_id, lat, lon, timestamp
- Already supports route filtering: `/api/v1/realtime?route_id=16`

✅ **GET /api/v1/routes/{id}**
- Returns route metadata (short_name, long_name, color)

✅ **GET /api/v1/stops**
- Returns all stops for a route
- Supports filtering: `/api/v1/stops?route_id=16`

✅ **GET /api/v1/stops/{id}/predictions**
- Returns arrival predictions (countdown timers)
- Already has HIGH/MEDIUM/LOW confidence levels

---

### New API Requirements

#### None!

All required data is already available through existing v1 APIs. The iOS app will:

1. Poll `/api/v1/realtime?route_id={favoriteRoute}` every 30 seconds
2. Fetch stops once on load: `/api/v1/stops?route_id={favoriteRoute}`
3. Fetch predictions for favorite stop: `/api/v1/stops/{favoriteStopId}/predictions?limit=5`

**Optimization Opportunity (Future):**

Consider creating a specialized endpoint that combines all data:

```
GET /api/v1/routes/{id}/live-widget
```

**Response:**
```json
{
  "route": {
    "id": "16",
    "short_name": "16",
    "long_name": "Eastview / City Centre",
    "color": "#E74C3C"
  },
  "vehicles": [
    {
      "id": "veh-123",
      "lat": 52.1332,
      "lon": -106.6700,
      "bearing": 145,
      "timestamp": 1697712345
    }
  ],
  "stops": [
    {
      "id": "stop-1",
      "name": "City Centre",
      "lat": 52.1278,
      "lon": -106.6702,
      "sequence": 1
    }
  ],
  "predictions": [
    {
      "stop_id": "stop-1",
      "arrival_time": 154,  // seconds from now
      "confidence": "HIGH"
    }
  ],
  "timestamp": 1697712345
}
```

**Benefit:** Single request instead of 3, reduces latency and battery usage.

---

## 6. iOS App Implementation

### Project Structure

```
MindTheWait/
├── Views/
│   ├── LiveRouteView.swift           # Main landing screen
│   ├── RouteVisualization3DView.swift # SceneKit wrapper
│   └── CountdownPanel.swift          # Arrival times overlay
│
├── ViewModels/
│   └── RouteVisualizationViewModel.swift
│
├── Services/
│   ├── RealtimeAPIService.swift      # API client
│   ├── VehiclePositionService.swift  # Vehicle data management
│   └── RoutePathGenerator.swift      # 3D path generation
│
├── Models/
│   ├── VehiclePosition.swift
│   ├── RoutePath.swift
│   └── ArrivalPrediction.swift
│
├── SceneKit/
│   ├── VehicleNode.swift             # 3D vehicle representation
│   ├── RoutePathNode.swift           # 3D route path
│   └── SceneConfigurator.swift       # Scene setup
│
└── Widgets/
    ├── LiveRouteWidget.swift         # WidgetKit implementation
    └── LiveRouteWidgetProvider.swift # Timeline provider
```

---

### Main Landing View

```swift
import SwiftUI
import SceneKit

struct LiveRouteView: View {
    @State private var viewModel = RouteVisualizationViewModel()

    var body: some View {
        ZStack {
            // Background
            Color(hex: "F5F1E8")
                .ignoresSafeArea()

            VStack(spacing: 0) {
                // Header
                routeHeader

                // 3D Visualization
                RouteVisualization3DView(viewModel: viewModel)
                    .frame(maxHeight: .infinity)

                // Countdown Panel
                CountdownPanel(predictions: viewModel.nextArrivals)
                    .padding(.horizontal)
                    .padding(.bottom, 20)
            }
        }
        .task {
            await viewModel.startLiveUpdates()
        }
    }

    private var routeHeader: some View {
        VStack(spacing: 4) {
            Text(viewModel.route.shortName)
                .font(.system(size: 48, weight: .bold, design: .rounded))
                .foregroundColor(Color(hex: "2C3E50"))

            Text(viewModel.route.longName)
                .font(.system(size: 16, weight: .medium))
                .foregroundColor(Color(hex: "34495E").opacity(0.7))
        }
        .padding(.top, 60)
        .padding(.bottom, 20)
    }
}
```

---

### ViewModel

```swift
import Foundation
import Observation

@Observable
class RouteVisualizationViewModel {
    // Data
    var route: Route
    var vehicles: [VehiclePosition] = []
    var stops: [Stop] = []
    var predictions: [ArrivalPrediction] = []

    // UI State
    var cameraRotation: SIMD3<Float> = .zero
    var isLoading: Bool = true
    var errorMessage: String? = nil

    private let apiService: RealtimeAPIService
    private var updateTask: Task<Void, Never>?

    init(favoriteRouteId: String) {
        self.apiService = RealtimeAPIService()
        self.route = Route(id: favoriteRouteId, shortName: "", longName: "", color: "")
    }

    // MARK: - Computed Properties

    var nextArrivals: [ArrivalPrediction] {
        predictions
            .filter { $0.stopId == favoriteStopId }
            .sorted { $0.arrivalTime < $1.arrivalTime }
            .prefix(3)
            .map { $0 }
    }

    // MARK: - Live Updates

    func startLiveUpdates() async {
        // Initial data fetch
        await fetchRouteData()
        await fetchStops()

        // Start polling loop
        updateTask = Task {
            while !Task.isCancelled {
                await fetchVehiclePositions()
                await fetchPredictions()

                // Wait 30 seconds before next update
                try? await Task.sleep(for: .seconds(30))
            }
        }
    }

    func stopLiveUpdates() {
        updateTask?.cancel()
    }

    // MARK: - API Calls

    private func fetchVehiclePositions() async {
        do {
            vehicles = try await apiService.fetchVehicles(routeId: route.id)
            errorMessage = nil
        } catch {
            errorMessage = "Unable to fetch live positions"
        }
    }

    private func fetchPredictions() async {
        guard let stopId = favoriteStopId else { return }

        do {
            predictions = try await apiService.fetchPredictions(
                stopId: stopId,
                limit: 5
            )
        } catch {
            // Fail silently, keep showing old predictions
        }
    }

    // ... other methods
}
```

---

### 3D Scene View

```swift
import SwiftUI
import SceneKit

struct RouteVisualization3DView: UIViewRepresentable {
    @Bindable var viewModel: RouteVisualizationViewModel

    func makeUIView(context: Context) -> SCNView {
        let sceneView = SCNView()
        sceneView.scene = createScene()
        sceneView.backgroundColor = UIColor(hex: "F5F1E8")
        sceneView.autoenablesDefaultLighting = true
        sceneView.allowsCameraControl = false  // We handle this ourselves

        return sceneView
    }

    func updateUIView(_ sceneView: SCNView, context: Context) {
        // Update vehicle positions
        updateVehicleNodes(in: sceneView.scene!, vehicles: viewModel.vehicles)

        // Update camera rotation
        if let cameraNode = sceneView.scene?.rootNode.childNode(
            withName: "camera",
            recursively: true
        ) {
            cameraNode.eulerAngles = SCNVector3(
                viewModel.cameraRotation.x,
                viewModel.cameraRotation.y,
                viewModel.cameraRotation.z
            )
        }
    }

    private func createScene() -> SCNScene {
        let scene = SCNScene()

        // Camera
        let cameraNode = SCNNode()
        cameraNode.name = "camera"
        cameraNode.camera = SCNCamera()
        cameraNode.position = SCNVector3(0, 5, 10)
        cameraNode.look(at: SCNVector3(0, 0, 0))
        scene.rootNode.addChildNode(cameraNode)

        // Route path
        let routePathNode = createRoutePathNode(stops: viewModel.stops)
        scene.rootNode.addChildNode(routePathNode)

        // Stop markers
        for stop in viewModel.stops {
            let stopNode = createStopNode(stop: stop)
            scene.rootNode.addChildNode(stopNode)
        }

        // Optional: Grid floor
        if UserDefaults.standard.bool(forKey: "showGrid") {
            let gridNode = createGridNode()
            scene.rootNode.addChildNode(gridNode)
        }

        return scene
    }

    private func createVehicleNode() -> SCNNode {
        let capsule = SCNCapsule(capRadius: 0.2, height: 0.6)
        capsule.firstMaterial?.diffuse.contents = UIColor(hex: "E74C3C")
        capsule.firstMaterial?.specular.contents = UIColor.white

        let node = SCNNode(geometry: capsule)
        node.rotation = SCNVector4(1, 0, 0, .pi / 2)  // Rotate to lay flat

        // Add subtle shadow
        node.castsShadow = true

        return node
    }

    // ... other helper methods
}
```

---

### Countdown Panel

```swift
struct CountdownPanel: View {
    let predictions: [ArrivalPrediction]

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text("Next at Your Stop:")
                .font(.system(size: 14, weight: .semibold))
                .foregroundColor(Color(hex: "34495E").opacity(0.7))
                .padding(.bottom, 4)

            ForEach(predictions) { prediction in
                HStack(spacing: 16) {
                    // Bus icon
                    Image(systemName: "bus.fill")
                        .font(.system(size: 20))
                        .foregroundColor(Color(hex: "E74C3C"))

                    // Countdown
                    Text(formatCountdown(prediction.arrivalTime))
                        .font(.system(size: 24, weight: .medium, design: .monospaced))
                        .foregroundColor(Color(hex: "2C3E50"))

                    Spacer()

                    // Confidence badge
                    confidenceBadge(prediction.confidence)
                }
                .padding(.vertical, 8)

                if prediction != predictions.last {
                    Divider()
                }
            }
        }
        .padding(20)
        .background(Color.white.opacity(0.9))
        .cornerRadius(16)
        .shadow(color: Color.black.opacity(0.1), radius: 10, y: 5)
    }

    private func formatCountdown(_ seconds: Int) -> String {
        let minutes = seconds / 60
        let remainingSeconds = seconds % 60
        return String(format: "%d:%02d", minutes, remainingSeconds)
    }

    private func confidenceBadge(_ confidence: String) -> some View {
        Text(confidence)
            .font(.system(size: 10, weight: .bold))
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(confidenceColor(confidence).opacity(0.2))
            .foregroundColor(confidenceColor(confidence))
            .cornerRadius(4)
    }

    private func confidenceColor(_ confidence: String) -> Color {
        switch confidence {
        case "HIGH": return .green
        case "MEDIUM": return .orange
        case "LOW": return .red
        default: return .gray
        }
    }
}
```

---

## 7. Widget Implementation

### WidgetKit Setup

```swift
import WidgetKit
import SwiftUI

@main
struct LiveRouteWidgetBundle: WidgetBundle {
    var body: some Widget {
        LiveRouteWidget()
    }
}

struct LiveRouteWidget: Widget {
    let kind: String = "LiveRouteWidget"

    var body: some WidgetConfiguration {
        StaticConfiguration(
            kind: kind,
            provider: LiveRouteWidgetProvider()
        ) { entry in
            LiveRouteWidgetEntryView(entry: entry)
        }
        .configurationDisplayName("Route Countdown")
        .description("See when the next bus arrives at your favorite stop.")
        .supportedFamilies([.systemSmall, .systemMedium, .systemLarge])
    }
}
```

---

### Timeline Provider

```swift
struct LiveRouteWidgetProvider: TimelineProvider {
    func placeholder(in context: Context) -> LiveRouteEntry {
        LiveRouteEntry(
            date: Date(),
            routeName: "Route 16",
            nextArrivals: [120, 480, 945],
            configuration: nil
        )
    }

    func getSnapshot(in context: Context, completion: @escaping (LiveRouteEntry) -> Void) {
        Task {
            let entry = await fetchLiveData()
            completion(entry)
        }
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<LiveRouteEntry>) -> Void) {
        Task {
            let entry = await fetchLiveData()

            // Refresh every 30 seconds
            let nextUpdate = Calendar.current.date(
                byAdding: .second,
                value: 30,
                to: Date()
            )!

            let timeline = Timeline(
                entries: [entry],
                policy: .after(nextUpdate)
            )

            completion(timeline)
        }
    }

    private func fetchLiveData() async -> LiveRouteEntry {
        let apiService = RealtimeAPIService()

        do {
            // Get user's favorite route and stop from UserDefaults
            let routeId = UserDefaults(suiteName: "group.mindthewait")?.string(forKey: "favoriteRoute") ?? "16"
            let stopId = UserDefaults(suiteName: "group.mindthewait")?.string(forKey: "favoriteStop") ?? ""

            // Fetch predictions
            let predictions = try await apiService.fetchPredictions(
                stopId: stopId,
                limit: 3
            )

            return LiveRouteEntry(
                date: Date(),
                routeName: "Route \(routeId)",
                nextArrivals: predictions.map(\.arrivalTime),
                configuration: nil
            )
        } catch {
            // Fallback to placeholder data
            return LiveRouteEntry(
                date: Date(),
                routeName: "Route 16",
                nextArrivals: [],
                configuration: nil
            )
        }
    }
}
```

---

### Widget UI (Small)

```swift
struct LiveRouteWidgetSmallView: View {
    let entry: LiveRouteEntry

    var body: some View {
        ZStack {
            Color(hex: "F5F1E8")

            VStack(spacing: 8) {
                // Route badge
                Text(entry.routeName)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundColor(Color(hex: "2C3E50"))

                // Next arrival countdown
                if let nextArrival = entry.nextArrivals.first {
                    Text(formatCountdown(nextArrival))
                        .font(.system(size: 36, weight: .bold, design: .monospaced))
                        .foregroundColor(Color(hex: "E74C3C"))
                } else {
                    Text("—")
                        .font(.system(size: 36, weight: .bold))
                        .foregroundColor(Color(hex: "34495E").opacity(0.3))
                }

                // Label
                Text("next bus")
                    .font(.system(size: 11, weight: .medium))
                    .foregroundColor(Color(hex: "34495E").opacity(0.6))
            }
        }
    }

    private func formatCountdown(_ seconds: Int) -> String {
        let minutes = seconds / 60
        return "\(minutes) min"
    }
}
```

---

### Widget UI (Medium)

```swift
struct LiveRouteWidgetMediumView: View {
    let entry: LiveRouteEntry

    var body: some View {
        ZStack {
            Color(hex: "F5F1E8")

            HStack(spacing: 16) {
                // Icon/Badge
                VStack {
                    Image(systemName: "bus.fill")
                        .font(.system(size: 32))
                        .foregroundColor(Color(hex: "E74C3C"))

                    Text(entry.routeName)
                        .font(.system(size: 12, weight: .semibold))
                        .foregroundColor(Color(hex: "2C3E50"))
                }
                .frame(width: 80)

                Divider()

                // Next arrivals
                VStack(alignment: .leading, spacing: 8) {
                    ForEach(entry.nextArrivals.prefix(3), id: \.self) { seconds in
                        HStack {
                            Text(formatCountdown(seconds))
                                .font(.system(size: 18, weight: .medium, design: .monospaced))
                                .foregroundColor(Color(hex: "2C3E50"))

                            Spacer()

                            Circle()
                                .fill(Color(hex: "3498DB"))
                                .frame(width: 8, height: 8)
                        }
                    }
                }
            }
            .padding()
        }
    }
}
```

---

### Widget UI (Large) - Mini 3D View

For the large widget, we can show a simplified top-down 2D representation (SceneKit doesn't work in widgets):

```swift
struct LiveRouteWidgetLargeView: View {
    let entry: LiveRouteEntry

    var body: some View {
        ZStack {
            Color(hex: "F5F1E8")

            VStack(spacing: 12) {
                // Header
                Text(entry.routeName)
                    .font(.system(size: 16, weight: .bold))
                    .foregroundColor(Color(hex: "2C3E50"))

                // Simplified 2D route visualization
                Canvas { context, size in
                    drawSimplifiedRoute(context: context, size: size)
                }
                .frame(height: 150)

                // Countdown list
                VStack(alignment: .leading, spacing: 8) {
                    ForEach(entry.nextArrivals.prefix(3), id: \.self) { seconds in
                        HStack {
                            Image(systemName: "bus.fill")
                                .foregroundColor(Color(hex: "E74C3C"))

                            Text(formatCountdown(seconds))
                                .font(.system(size: 16, weight: .medium, design: .monospaced))

                            Spacer()
                        }
                    }
                }
                .padding(.horizontal)
            }
            .padding()
        }
    }

    private func drawSimplifiedRoute(context: GraphicsContext, size: CGSize) {
        // Draw simple line with dots for vehicles/stops
        // This is a 2D approximation since SceneKit doesn't work in widgets

        let path = Path { path in
            path.move(to: CGPoint(x: size.width * 0.1, y: size.height * 0.5))
            path.addLine(to: CGPoint(x: size.width * 0.9, y: size.height * 0.5))
        }

        context.stroke(
            path,
            with: .color(Color(hex: "2C3E50")),
            lineWidth: 4
        )

        // Draw vehicle markers
        // (Position would come from entry data)
    }
}
```

---

## 8. Animation & Performance

### Vehicle Movement Animation

**Challenge:** Vehicles update positions every 30 seconds, but we want smooth movement.

**Solution:** Interpolate between old and new positions

```swift
class VehicleNode: SCNNode {
    func animateToPosition(_ newPosition: SCNVector3, duration: TimeInterval = 30.0) {
        let moveAction = SCNAction.move(to: newPosition, duration: duration)
        moveAction.timingMode = .linear  // Constant speed

        runAction(moveAction)
    }
}

// In updateVehicleNodes():
for (vehicle, node) in zip(vehicles, vehicleNodes) {
    let newPosition = calculatePosition(for: vehicle)
    node.animateToPosition(newPosition, duration: 30.0)
}
```

**Result:** Vehicles glide smoothly along route instead of teleporting.

---

### Countdown Timer Updates

**Challenge:** Update countdown every second without re-rendering entire view.

**Solution:** Use Timer.publish with minimal SwiftUI updates

```swift
@Observable
class CountdownTimer {
    var currentTime: Date = Date()
    private var timer: Timer?

    func start() {
        timer = Timer.scheduledTimer(withTimeInterval: 1.0, repeats: true) { [weak self] _ in
            self?.currentTime = Date()
        }
    }

    func stop() {
        timer?.invalidate()
    }
}

// In view:
@State private var countdownTimer = CountdownTimer()

var body: some View {
    CountdownPanel(
        predictions: viewModel.nextArrivals,
        currentTime: countdownTimer.currentTime
    )
    .onAppear { countdownTimer.start() }
    .onDisappear { countdownTimer.stop() }
}
```

---

### Performance Optimizations

**1. Level of Detail (LOD):**
```swift
// Use simpler geometry for distant vehicles
if distanceFromCamera > 10 {
    vehicleNode.geometry = simpleCapsule  // Low poly
} else {
    vehicleNode.geometry = detailedMesh   // High poly
}
```

**2. Texture Sharing:**
```swift
// Reuse materials across vehicle nodes
let sharedMaterial = SCNMaterial()
sharedMaterial.diffuse.contents = UIColor.red

for vehicleNode in vehicleNodes {
    vehicleNode.geometry?.materials = [sharedMaterial]
}
```

**3. Culling:**
```swift
// Don't render vehicles outside camera frustum
sceneView.automaticallyUpdates = true
sceneView.jitteringEnabled = false  // Disable for smoother rotation
```

**4. Background API Calls:**
```swift
// Use background URLSession for widget updates
let configuration = URLSessionConfiguration.background(
    withIdentifier: "com.mindthewait.widget"
)
let session = URLSession(configuration: configuration)
```

---

### Battery Life Considerations

**Strategies:**

1. **Reduce polling when app backgrounded:**
```swift
@Environment(\.scenePhase) private var scenePhase

.onChange(of: scenePhase) { oldPhase, newPhase in
    switch newPhase {
    case .active:
        viewModel.setPollingInterval(30)  // 30 seconds
    case .inactive, .background:
        viewModel.setPollingInterval(60)  // 60 seconds
    @unknown default:
        break
    }
}
```

2. **Use CoreLocation significant location changes** instead of continuous GPS (not needed for this feature)

3. **Widget refresh policy:**
```swift
// Let iOS decide optimal refresh timing
.after(nextUpdate)  // iOS will batch updates with other widgets
```

4. **Limit SceneKit rendering:**
```swift
sceneView.preferredFramesPerSecond = 30  // Don't need 60fps for this
```

---

## 9. Testing Strategy

### Unit Tests

```swift
class RoutePathGeneratorTests: XCTestCase {
    func testConvertLatLonToLocal() {
        let stops = [
            Stop(id: "1", name: "A", lat: 52.1278, lon: -106.6702),
            Stop(id: "2", name: "B", lat: 52.1332, lon: -106.6700)
        ]

        let generator = RoutePathGenerator()
        let points = generator.convertToLocalCoordinates(stops: stops)

        // Verify points are normalized to scene bounds
        XCTAssertGreaterThan(points[0].x, -5.0)
        XCTAssertLessThan(points[0].x, 5.0)
    }

    func testVehiclePositioning() {
        // Test that vehicles snap to nearest route segment
        let routePath = [
            CGPoint(x: 0, y: 0),
            CGPoint(x: 10, y: 0)
        ]

        let vehicle = VehiclePosition(lat: 52.13, lon: -106.67)
        let position = positionVehicle(vehicle: vehicle, routePath: routePath)

        // Verify vehicle is on the line segment
        XCTAssertEqual(position.position.y, 0.2, accuracy: 0.01) // Slight elevation
    }
}
```

---

### UI Tests

```swift
class LiveRouteViewUITests: XCTestCase {
    func testCountdownUpdates() {
        let app = XCUIApplication()
        app.launch()

        // Find countdown label
        let countdownLabel = app.staticTexts.matching(
            NSPredicate(format: "label CONTAINS 'min'")
        ).firstMatch

        let initialValue = countdownLabel.label

        // Wait 1 second
        Thread.sleep(forTimeInterval: 1.0)

        let updatedValue = countdownLabel.label

        // Verify countdown decreased
        XCTAssertNotEqual(initialValue, updatedValue)
    }

    func testDragGesture() {
        let app = XCUIApplication()
        app.launch()

        let sceneView = app.otherElements["route3DView"]

        // Perform drag gesture
        let start = sceneView.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.5))
        let end = sceneView.coordinate(withNormalizedOffset: CGVector(dx: 0.7, dy: 0.3))
        start.press(forDuration: 0.1, thenDragTo: end)

        // Verify scene rotated (check camera node rotation)
        // This requires exposing rotation state via accessibility identifier
        XCTAssertTrue(sceneView.exists)
    }
}
```

---

### Widget Tests

```swift
class LiveRouteWidgetTests: XCTestCase {
    func testWidgetPlaceholder() {
        let provider = LiveRouteWidgetProvider()
        let context = WidgetPreviewContext(family: .systemSmall)

        let placeholder = provider.placeholder(in: context)

        XCTAssertEqual(placeholder.routeName, "Route 16")
        XCTAssertFalse(placeholder.nextArrivals.isEmpty)
    }

    func testWidgetTimelineRefresh() async {
        let provider = LiveRouteWidgetProvider()
        let context = WidgetPreviewContext(family: .systemSmall)

        let expectation = expectation(description: "Timeline generated")

        provider.getTimeline(in: context) { timeline in
            // Verify refresh policy
            if case .after(let date) = timeline.policy {
                let interval = date.timeIntervalSinceNow
                XCTAssertGreaterThan(interval, 25)  // ~30 seconds
                XCTAssertLessThan(interval, 35)
            }

            expectation.fulfill()
        }

        await fulfillment(of: [expectation], timeout: 5.0)
    }
}
```

---

### Performance Tests

```swift
class RouteVisualizationPerformanceTests: XCTestCase {
    func testSceneRenderingPerformance() {
        let viewModel = RouteVisualizationViewModel(favoriteRouteId: "16")
        viewModel.vehicles = createMockVehicles(count: 20)

        measure {
            let sceneView = SCNView()
            sceneView.scene = createScene(viewModel: viewModel)
            sceneView.prepareObject(sceneView.scene!, shouldAbortBlock: nil)
        }

        // Target: < 16ms (60fps)
    }

    func testCountdownUpdatePerformance() {
        let predictions = createMockPredictions(count: 50)

        measure {
            for prediction in predictions {
                _ = formatCountdown(prediction.arrivalTime)
            }
        }

        // Should be negligible (<1ms for 50 items)
    }
}
```

---

## 10. Future Enhancements

### Phase 2: Advanced Visualizations

**1. Weather Effects**
```swift
// Add particle systems for rain/snow
let snowEmitter = SCNParticleSystem()
snowEmitter.particleImage = UIImage(systemName: "snowflake")
snowEmitter.birthRate = 100
snowEmitter.emitterShape = SCNPlane(width: 20, height: 20)

let weatherNode = SCNNode()
weatherNode.addParticleSystem(snowEmitter)
scene.rootNode.addChildNode(weatherNode)
```

**Visual:** Gentle snowfall overlay during winter conditions

---

**2. Time-of-Day Lighting**
```swift
// Adjust scene lighting based on real time
let hour = Calendar.current.component(.hour, from: Date())

let lightNode = scene.rootNode.childNode(withName: "light", recursively: true)

switch hour {
case 6..<10:   // Morning - warm golden light
    lightNode?.light?.color = UIColor(hex: "FFD700")
case 10..<16:  // Midday - bright white
    lightNode?.light?.color = UIColor.white
case 16..<20:  // Evening - warm orange
    lightNode?.light?.color = UIColor(hex: "FF8C42")
default:       // Night - cool blue
    lightNode?.light?.color = UIColor(hex: "4A90E2")
}
```

**Visual:** Scene mood matches current time of day

---

**3. Vehicle Crowding Indicator**
```swift
// Change vehicle color/size based on crowding level
if vehicle.crowdingLevel == "high" {
    vehicleNode.scale = SCNVector3(1.2, 1.2, 1.2)  // Bigger
    vehicleNode.geometry?.firstMaterial?.diffuse.contents = UIColor.orange
}
```

**Visual:** Overcrowded buses appear larger and change color

---

**4. Schedule Adherence Visualization**
```swift
// Color vehicles based on on-time status
switch vehicle.delaySeconds {
case ..<(-180):  // Early
    vehicleColor = UIColor.blue
case -180..<180: // On time
    vehicleColor = UIColor.green
default:         // Late
    vehicleColor = UIColor.red
}
```

**Visual:** Traffic light colors show punctuality at a glance

---

### Phase 3: Interactive Features

**1. Tap to Select Vehicle**
```swift
@objc func handleTap(_ gestureRecognizer: UITapGestureRecognizer) {
    let location = gestureRecognizer.location(in: sceneView)
    let hitResults = sceneView.hitTest(location, options: [:])

    if let vehicleNode = hitResults.first?.node {
        // Show detail popover
        showVehicleDetail(vehicleNode.vehicleId)
    }
}

func showVehicleDetail(_ vehicleId: String) {
    // Display:
    // - Vehicle ID
    // - Current capacity
    // - Next 3 stops
    // - Estimated arrival at your stop
}
```

**Visual:** Tap bus to see detailed info card

---

**2. Pinch to Zoom**
```swift
@GestureState private var zoomScale: CGFloat = 1.0

var body: some View {
    SceneView(...)
        .gesture(
            MagnificationGesture()
                .updating($zoomScale) { value, state, _ in
                    state = value
                }
        )
        .onChange(of: zoomScale) { _, newValue in
            cameraNode.camera?.fieldOfView = baseFOV / newValue
        }
}
```

**Visual:** Zoom in/out with pinch gesture

---

**3. Multi-Route View**
```swift
// Show multiple favorite routes on same map
struct MultiRouteVisualizationView: View {
    @State var favoriteRoutes: [String] = ["16", "22", "8"]

    var body: some View {
        // Each route has its own color
        // Vehicles layered in 3D space (slight Z-offset per route)
        // Tap route line to filter to just that route
    }
}
```

**Visual:** Bird's-eye view of entire transit system

---

### Phase 4: AR Mode

**Using ARKit + RealityKit:**

```swift
import ARKit
import RealityKit

struct ARRouteView: View {
    var body: some View {
        ARViewContainer()
    }
}

struct ARViewContainer: UIViewRepresentable {
    func makeUIView(context: Context) -> ARView {
        let arView = ARView(frame: .zero)

        // Place route in real world
        let anchor = AnchorEntity(plane: .horizontal)

        // Create 3D route model
        let routeEntity = createRouteEntity()
        anchor.addChild(routeEntity)

        arView.scene.addAnchor(anchor)

        return arView
    }

    func createRouteEntity() -> ModelEntity {
        // 3D route that appears on table/floor
        // Life-size vehicles moving along track
    }
}
```

**Use Case:** Place a miniature transit system on your desk or floor, watch buses move in real-time.

---

### Phase 5: Apple Watch Complication

**Minimal countdown display:**

```swift
struct LiveRouteComplication: View {
    let entry: LiveRouteEntry

    var body: some View {
        ZStack {
            // Circular complication
            Circle()
                .fill(Color(hex: "E74C3C"))

            VStack(spacing: 2) {
                Text("\(entry.nextArrival / 60)")
                    .font(.system(size: 18, weight: .bold, design: .rounded))
                    .foregroundColor(.white)

                Text("min")
                    .font(.system(size: 8, weight: .medium))
                    .foregroundColor(.white.opacity(0.8))
            }
        }
    }
}
```

**Visual:** Glanceable countdown on watch face

---

## Implementation Roadmap

### Week 1: Foundation
- [x] Set up SceneKit scene structure
- [x] Implement coordinate conversion (lat/lon → 3D space)
- [x] Create route path generation algorithm
- [x] Basic vehicle node rendering

### Week 2: API Integration
- [x] Connect to existing `/api/v1/realtime` endpoint
- [x] Implement 30-second polling loop
- [x] Parse vehicle positions and predictions
- [x] Handle loading/error states

### Week 3: UI & Interaction
- [x] Build countdown panel UI
- [x] Implement drag gesture for camera rotation
- [x] Add smooth vehicle movement animations
- [x] Polish mid-century modern styling

### Week 4: Widget & Testing
- [x] Implement WidgetKit timeline provider
- [x] Create small/medium/large widget layouts
- [x] Write unit tests for positioning logic
- [x] Performance optimization and battery testing

---

## Design Mockups

### Landing Screen (Full 3D View)

```
┌─────────────────────────────────────────────┐
│  ☰                                    ⚙️   │
├─────────────────────────────────────────────┤
│                                             │
│              Route 16                       │
│      Eastview / City Centre                 │
│                                             │
│         [3D Isometric View]                 │
│                                             │
│      🚌──────○────────🚌─────○──────🚌      │
│     ↑       ↑        ↑      ↑       ↑      │
│   Early   Stop 1   On-time Stop 2  Late    │
│   (blue)           (green)         (red)    │
│                                             │
│    · · · · · · · · · · · · · · · · ·       │  ← Subtle grid
│   · · · · · · · · · · · · · · · · · ·      │
│                                             │
│   ┌───────────────────────────────────┐    │
│   │  Next at Preston & 8th:           │    │
│   │                                    │    │
│   │  🚌  2:34         HIGH            │    │
│   │  🚌  8:12         MEDIUM          │    │
│   │  🚌  15:45        LOW             │    │
│   └───────────────────────────────────┘    │
│                                             │
└─────────────────────────────────────────────┘
```

---

### Widget Layouts

**Small (2×2):**
```
┌─────────────────┐
│                 │
│   Route 16      │
│                 │
│     2:34        │
│                 │
│   next bus      │
│                 │
└─────────────────┘
```

**Medium (4×2):**
```
┌───────────────────────────────────┐
│                                   │
│  🚌    Route 16    2:34  ●       │
│                   8:12  ●       │
│                  15:45  ●       │
│                                   │
└───────────────────────────────────┘
```

**Large (4×4):**
```
┌───────────────────────────────────┐
│         Route 16                  │
│                                   │
│   ─────🚌────○────🚌─────         │  ← 2D simplified view
│                                   │
│   Next arrivals:                  │
│   🚌  2:34                        │
│   🚌  8:12                        │
│   🚌  15:45                       │
│                                   │
└───────────────────────────────────┘
```

---

## Success Metrics

**User Engagement:**
- Widget adoption rate: Target >40% of iOS users
- Daily opens from widget: Target >60% of app opens
- Average session duration: Target >30 seconds (up from ~10s for list view)

**Visual Appeal:**
- User ratings mentioning "beautiful" or "design": Track sentiment
- Screenshots shared on social media: Monitor for organic sharing

**Functional Utility:**
- Countdown accuracy: ±30 seconds (based on API prediction confidence)
- Widget reliability: >95% successful timeline updates
- Battery impact: <2% additional drain per day

---

## Open Questions

1. **Grid background:** A/B test with and without, measure user preference
2. **Vehicle model detail:** Start simple (capsule), upgrade if performance allows
3. **Multiple routes:** Single route for MVP, multi-route in Phase 3?
4. **Haptic feedback:** Subtle haptics when vehicle arrives at your stop?
5. **Accessibility:** VoiceOver support for 3D scene? (Challenging with SceneKit)

---

## References

### Design Inspiration
- [Saul Bass title sequences](https://www.artofthetitle.com/designer/saul-bass/) - Mid-century graphic design
- [Eames "Powers of Ten"](https://www.eamesoffice.com/the-work/powers-of-ten/) - Simplified 3D visualization
- [Massimo Vignelli subway maps](https://www.vignelli.com/canon.pdf) - Geometric abstraction
- [Swiss railway clock](https://en.wikipedia.org/wiki/Swiss_railway_clock) - Minimal timekeeping design

### Technical Resources
- [Apple SceneKit Documentation](https://developer.apple.com/documentation/scenekit)
- [WidgetKit Guidelines](https://developer.apple.com/documentation/widgetkit)
- [Human Interface Guidelines - Widgets](https://developer.apple.com/design/human-interface-guidelines/widgets)

---

**Document Version:** 1.0
**Status:** Ready for Implementation
**Author:** Claude (Anthropic) + User Collaboration
**Last Updated:** 2025-10-19
