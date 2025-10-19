# Code Quality Refactoring Plan

> **📋 STATUS: PLANNING** | This document describes refactoring tasks that are PARTIALLY implemented.
>
> **Implementation Status:** Phase 4 completed (Temperature Threshold). Remaining phases not started.
> **Priority:** High (ongoing code quality initiative)
> **Last Updated:** 2025-10-19

## Executive Summary

**Goal:** Improve code quality, type safety, and maintainability across the Mind-the-Wait codebase by implementing repository pattern best practices, DTOs, enums, and value objects.

**Timeline:** 3-4 weeks (can be done incrementally per module)

**Impact:**
- **Type Safety:** Replace array access with typed DTOs and enums
- **Maintainability:** Clear separation of concerns (repositories vs services)
- **Testability:** Easier to mock and test with typed interfaces
- **Developer Experience:** Better IDE autocomplete and static analysis

**Risk:** Low (backward compatible, can be done incrementally)

---

## Table of Contents

1. [Remove EntityManager from Services](#1-remove-entitymanager-from-services)
2. [Repository Methods Return DTOs](#2-repository-methods-return-dtos)
3. [Use Enums for Weather Conditions](#3-use-enums-for-weather-conditions)
4. [Chart Object and Service](#4-chart-object-and-service)
5. [Implementation Roadmap](#5-implementation-roadmap)
6. [Testing Strategy](#6-testing-strategy)
7. [Rollout Plan](#7-rollout-plan)

---

## 1. Remove EntityManager from Services

### Problem

**Current Anti-Pattern:**
```php
// src/Service/Dashboard/WeatherAnalysisService.php
class WeatherAnalysisService
{
    public function __construct(
        private RoutePerformanceDailyRepository $performanceRepo,
        // ... other dependencies
    ) {}

    private function buildWinterOperationsChart(): array
    {
        // ❌ Query logic in service
        $qb = $this->performanceRepo->createQueryBuilder('p');
        $clearResults = $qb->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf')
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition = :clear')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('clear', 'clear')
            ->groupBy('r.id', 'r.shortName', 'r.longName')
            ->having('COUNT(p.id) >= 3')
            ->getQuery()
            ->getResult();

        // ... more query logic
    }
}
```

**Issues:**
1. Service has direct database query knowledge
2. Hard to test (requires database)
3. Violates Single Responsibility Principle
4. Query logic duplicated across services
5. Can't easily switch persistence layer

### Solution

**Move all queries to repositories:**

```php
// src/Repository/RoutePerformanceDailyRepository.php
class RoutePerformanceDailyRepository extends ServiceEntityRepository
{
    /**
     * Get average performance by weather condition.
     *
     * @return list<array{id: int, shortName: string, longName: string, avgPerf: float, days: int}>
     */
    public function findAveragePerformanceByWeatherCondition(
        string $weatherCondition,
        int $minDays = 3
    ): array {
        return $this->createQueryBuilder('p')
            ->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
            ->join('p.route', 'r')
            ->leftJoin('p.weatherObservation', 'w')
            ->where('w.weatherCondition = :condition')
            ->andWhere('p.onTimePercentage IS NOT NULL')
            ->setParameter('condition', $weatherCondition)
            ->groupBy('r.id', 'r.shortName', 'r.longName')
            ->having('COUNT(p.id) >= :minDays')
            ->setParameter('minDays', $minDays)
            ->getQuery()
            ->getResult();
    }
}
```

**Simplified service:**

```php
// src/Service/Dashboard/WeatherAnalysisService.php
class WeatherAnalysisService
{
    public function __construct(
        private RoutePerformanceDailyRepository $performanceRepo,
        // ... other dependencies
    ) {}

    private function buildWinterOperationsChart(): array
    {
        // ✅ Clean service logic - just calls repository methods
        $clearResults = $this->performanceRepo->findAveragePerformanceByWeatherCondition('clear', minDays: 3);
        $snowResults  = $this->performanceRepo->findAveragePerformanceByWeatherCondition('snow', minDays: 3);

        // ... chart building logic
    }
}
```

### Files to Refactor

#### High Priority (Complex Queries in Services)

1. **WeatherAnalysisService.php**
   - `buildWinterOperationsChart()` → Move to `RoutePerformanceDailyRepository`
   - `buildTemperatureThresholdChart()` → Move to `RoutePerformanceDailyRepository`
   - `buildWeatherImpactMatrixChart()` → Move to `RoutePerformanceDailyRepository`

2. **OverviewService.php**
   - `calculateTrendVsYesterday()` → Move to `RoutePerformanceDailyRepository`
   - `getHistoricalTopPerformers()` → Move to `RoutePerformanceDailyRepository`
   - `getHistoricalWorstPerformers()` → Move to `RoutePerformanceDailyRepository`
   - `calculateWinterImpactStats()` → Move to `RoutePerformanceDailyRepository`

3. **RouteController.php**
   - Any inline queries → Move to respective repositories

#### Medium Priority

4. **PerformanceAggregator.php**
   - Review for any inline queries

5. **Other Services**
   - Scan for `createQueryBuilder()` calls outside repositories

### Implementation Checklist

- [ ] Identify all services with direct query logic
- [ ] For each query:
  - [ ] Create new repository method with descriptive name
  - [ ] Add PHPDoc with return type
  - [ ] Write unit test for repository method
  - [ ] Update service to call repository method
  - [ ] Update service unit tests
- [ ] Run full test suite
- [ ] Deploy incrementally per service

---

## 2. Repository Methods Return DTOs

### Problem

**Current Anti-Pattern:**
```php
// Repository returns raw array
public function findAveragePerformanceByWeatherCondition(string $condition): array
{
    return $this->createQueryBuilder('p')
        ->select('r.id', 'r.shortName', 'AVG(p.onTimePercentage) as avgPerf')
        // ...
        ->getResult(); // Returns list<array<string, mixed>>
}

// Service uses array access (error-prone, no IDE autocomplete)
$results = $repo->findAveragePerformanceByWeatherCondition('clear');
foreach ($results as $row) {
    $routeId = $row['id'];              // ❌ Typo-prone
    $shortName = $row['shortName'];     // ❌ No type safety
    $avgPerf = (float) $row['avgPerf']; // ❌ Manual casting
}
```

**Issues:**
1. No type safety (array keys can be mistyped)
2. No IDE autocomplete
3. No static analysis
4. Manual type casting required
5. Hard to refactor (no type hints)

### Solution

**Create DTOs and return them from repositories:**

```php
// src/Dto/RoutePerformanceSummaryDto.php
readonly class RoutePerformanceSummaryDto
{
    public function __construct(
        public int $routeId,
        public string $shortName,
        public string $longName,
        public float $averageOnTimePercentage,
        public int $daysCount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routeId: (int) $data['id'],
            shortName: (string) $data['shortName'],
            longName: (string) $data['longName'],
            averageOnTimePercentage: (float) $data['avgPerf'],
            daysCount: (int) $data['days'],
        );
    }
}
```

**Repository returns DTOs:**

```php
// src/Repository/RoutePerformanceDailyRepository.php
/**
 * @return list<RoutePerformanceSummaryDto>
 */
public function findAveragePerformanceByWeatherCondition(
    string $weatherCondition,
    int $minDays = 3
): array {
    $results = $this->createQueryBuilder('p')
        ->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf', 'COUNT(p.id) as days')
        ->join('p.route', 'r')
        ->leftJoin('p.weatherObservation', 'w')
        ->where('w.weatherCondition = :condition')
        ->andWhere('p.onTimePercentage IS NOT NULL')
        ->setParameter('condition', $weatherCondition)
        ->groupBy('r.id', 'r.shortName', 'r.longName')
        ->having('COUNT(p.id) >= :minDays')
        ->setParameter('minDays', $minDays)
        ->getQuery()
        ->getResult();

    return array_map(
        fn(array $row) => RoutePerformanceSummaryDto::fromArray($row),
        $results
    );
}
```

**Service uses typed DTOs:**

```php
// src/Service/Dashboard/WeatherAnalysisService.php
private function buildWinterOperationsChart(): array
{
    $clearResults = $this->performanceRepo->findAveragePerformanceByWeatherCondition('clear', minDays: 3);

    foreach ($clearResults as $dto) {
        $routeId = $dto->routeId;                          // ✅ Type-safe
        $shortName = $dto->shortName;                      // ✅ IDE autocomplete
        $avgPerf = $dto->averageOnTimePercentage;          // ✅ Already typed
    }
}
```

### New DTOs to Create

#### Weather Analysis DTOs

```php
// src/Dto/RoutePerformanceSummaryDto.php
readonly class RoutePerformanceSummaryDto
{
    public function __construct(
        public int $routeId,
        public string $shortName,
        public string $longName,
        public float $averageOnTimePercentage,
        public int $daysCount,
    ) {}
}

// src/Dto/WeatherPerformanceComparisonDto.php
readonly class WeatherPerformanceComparisonDto
{
    public function __construct(
        public int $routeId,
        public string $shortName,
        public string $longName,
        public float $clearPerformance,
        public float $snowPerformance,
        public float $performanceDrop,
        public int $clearDaysCount,
        public int $snowDaysCount,
    ) {}
}

// src/Dto/TemperaturePerformanceDto.php
readonly class TemperaturePerformanceDto
{
    public function __construct(
        public int $temperatureCelsius,
        public float $averageOnTimePercentage,
        public int $observationCount,
    ) {}
}

// src/Dto/BunchingRateDto.php (replace BunchingCountDto)
readonly class BunchingRateDto
{
    public function __construct(
        public WeatherCondition $weatherCondition, // Use enum (see section 3)
        public int $incidentCount,
        public float $exposureHours,
        public float $incidentsPerHour,
    ) {}
}
```

#### Dashboard DTOs

```php
// src/Dto/HistoricalPerformerDto.php
readonly class HistoricalPerformerDto
{
    public function __construct(
        public int $routeId,
        public string $gtfsId,
        public string $shortName,
        public string $longName,
        public float $averageOnTimePercentage,
        public string $grade,
        public ?string $colour,
        public int $daysCount,
    ) {}
}
```

### Implementation Checklist

- [ ] Create DTO directory structure: `src/Dto/`
- [ ] For each repository method returning arrays:
  - [ ] Create corresponding DTO class
  - [ ] Add `fromArray()` static factory method
  - [ ] Update repository to return DTO
  - [ ] Update service to use DTO properties
  - [ ] Update tests
- [ ] Run PHPStan to catch type issues
- [ ] Run full test suite

---

## 3. Use Enums for Weather Conditions

### Problem

**Current Anti-Pattern:**
```php
// Magic strings scattered throughout codebase
$condition = 'snow';  // ❌ Typo-prone
$condition = 'Snow';  // ❌ Case inconsistency
$condition = 'SNOW';  // ❌ No validation

// Array access with magic strings
$conditionMap = [
    'snow'   => ['label' => 'Snow', 'color' => '#ede9fe'],   // ❌ Duplicated strings
    'rain'   => ['label' => 'Rain', 'color' => '#dbeafe'],
    'cloudy' => ['label' => 'Cloudy', 'color' => '#e5e7eb'],
    'clear'  => ['label' => 'Clear', 'color' => '#fef3c7'],
];

// String comparisons
match (strtolower($row['weather_condition'])) {  // ❌ Manual normalization
    'snow'  => $snowRate  = $row['incidents_per_hour'],
    'rain'  => $rainRate  = $row['incidents_per_hour'],
    'clear' => $clearRate = $row['incidents_per_hour'],
    default => null,
};
```

**Issues:**
1. Typos not caught at compile time
2. Case inconsistency
3. No exhaustive matching in `match` expressions
4. Duplicated label/color configuration
5. Hard to add new conditions

### Solution

**Create WeatherCondition Enum:**

```php
<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Weather conditions from Open-Meteo API.
 *
 * Maps WMO weather codes to human-readable conditions.
 */
enum WeatherCondition: string
{
    case CLEAR        = 'clear';
    case CLOUDY       = 'cloudy';
    case OVERCAST     = 'overcast';
    case FOG          = 'fog';
    case DRIZZLE      = 'drizzle';
    case RAIN         = 'rain';
    case SHOWERS      = 'showers';
    case SNOW         = 'snow';
    case SNOW_SHOWERS = 'snow_showers';
    case THUNDERSTORM = 'thunderstorm';
    case FREEZING_RAIN = 'freezing_rain';

    /**
     * Get display label for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::CLEAR        => 'Clear',
            self::CLOUDY       => 'Cloudy',
            self::OVERCAST     => 'Overcast',
            self::FOG          => 'Fog',
            self::DRIZZLE      => 'Drizzle',
            self::RAIN         => 'Rain',
            self::SHOWERS      => 'Showers',
            self::SNOW         => 'Snow',
            self::SNOW_SHOWERS => 'Snow Showers',
            self::THUNDERSTORM => 'Thunderstorm',
            self::FREEZING_RAIN => 'Freezing Rain',
        };
    }

    /**
     * Get chart color (Tailwind color).
     */
    public function chartColor(): string
    {
        return match ($this) {
            self::CLEAR        => '#fef3c7', // yellow-100
            self::CLOUDY       => '#e5e7eb', // gray-200
            self::OVERCAST     => '#d1d5db', // gray-300
            self::FOG          => '#9ca3af', // gray-400
            self::DRIZZLE      => '#cbd5e1', // slate-300
            self::RAIN         => '#dbeafe', // blue-100
            self::SHOWERS      => '#bfdbfe', // blue-200
            self::SNOW         => '#ede9fe', // purple-100
            self::SNOW_SHOWERS => '#ddd6fe', // purple-200
            self::THUNDERSTORM => '#1e293b', // slate-800
            self::FREEZING_RAIN => '#c7d2fe', // indigo-200
        };
    }

    /**
     * Get icon name for UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CLEAR        => 'sun',
            self::CLOUDY       => 'cloud',
            self::OVERCAST     => 'clouds',
            self::FOG          => 'smog',
            self::DRIZZLE      => 'cloud-rain',
            self::RAIN         => 'cloud-showers-heavy',
            self::SHOWERS      => 'cloud-rain',
            self::SNOW         => 'snowflake',
            self::SNOW_SHOWERS => 'snowflake',
            self::THUNDERSTORM => 'bolt',
            self::FREEZING_RAIN => 'icicles',
        };
    }

    /**
     * Parse from database string (case-insensitive).
     */
    public static function fromString(string $condition): ?self
    {
        return self::tryFrom(strtolower($condition));
    }

    /**
     * Get all conditions for chart rendering.
     *
     * @return array<self>
     */
    public static function chartConditions(): array
    {
        return [
            self::SNOW,
            self::RAIN,
            self::CLOUDY,
            self::CLEAR,
            self::SHOWERS,
            self::THUNDERSTORM,
        ];
    }
}
```

**Updated Service:**

```php
// src/Service/Dashboard/WeatherAnalysisService.php
private function buildBunchingByWeatherChart(): array
{
    $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

    // ✅ Use enum instead of magic strings
    $data = [];
    foreach (WeatherCondition::chartConditions() as $condition) {
        $incidentsPerHour = 0;
        $exposureHours    = 0;

        foreach ($results as $row) {
            if ($row->weatherCondition === $condition) {
                $incidentsPerHour = $row->incidentsPerHour;
                $exposureHours    = $row->exposureHours;
                break;
            }
        }

        $data[] = [
            'value'         => $incidentsPerHour,
            'itemStyle'     => ['color' => $condition->chartColor()], // ✅ No magic strings
            'exposureHours' => $exposureHours,
        ];
    }

    $conditions = array_map(fn($c) => $c->label(), WeatherCondition::chartConditions());

    // ... rest of chart config
}
```

**Updated Repository:**

```php
// src/Repository/BunchingIncidentRepository.php
/**
 * @return list<BunchingRateDto>
 */
public function countByWeatherConditionNormalized(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): array {
    // ... SQL query returns raw array

    return array_map(function ($row) {
        return new BunchingRateDto(
            weatherCondition: WeatherCondition::fromString($row['weather_condition']) ?? WeatherCondition::CLEAR,
            incidentCount: (int) $row['incident_count'],
            exposureHours: (float) $row['exposure_hours'],
            incidentsPerHour: round((float) $row['incidents_per_hour'], 2),
        );
    }, $results);
}
```

**Updated Stats Calculation:**

```php
// src/Service/Dashboard/WeatherAnalysisService.php
private function buildBunchingByWeatherStats(): array
{
    $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

    $snowRate   = 0;
    $rainRate   = 0;
    $clearRate  = 0;
    $snowHours  = 0;
    $rainHours  = 0;
    $clearHours = 0;

    foreach ($results as $dto) {
        // ✅ Type-safe enum comparison
        match ($dto->weatherCondition) {
            WeatherCondition::SNOW => [
                $snowRate  = $dto->incidentsPerHour,
                $snowHours = $dto->exposureHours,
            ],
            WeatherCondition::RAIN => [
                $rainRate  = $dto->incidentsPerHour,
                $rainHours = $dto->exposureHours,
            ],
            WeatherCondition::CLEAR => [
                $clearRate  = $dto->incidentsPerHour,
                $clearHours = $dto->exposureHours,
            ],
            default => null,
        };
    }

    // ... rest
}
```

### Database Migration

**Update WeatherObservation entity to use enum:**

```php
// src/Entity/WeatherObservation.php
#[ORM\Entity(repositoryClass: WeatherObservationRepository::class)]
class WeatherObservation
{
    // Before:
    // #[ORM\Column(length: 50, nullable: true)]
    // private ?string $weatherCondition = null;

    // After:
    #[ORM\Column(type: 'string', length: 50, nullable: true, enumType: WeatherCondition::class)]
    private ?WeatherCondition $weatherCondition = null;

    public function getWeatherCondition(): ?WeatherCondition
    {
        return $this->weatherCondition;
    }

    public function setWeatherCondition(?WeatherCondition $weatherCondition): self
    {
        $this->weatherCondition = $weatherCondition;
        return $this;
    }
}
```

**Note:** Doctrine automatically handles enum → string conversion for database storage.

### Implementation Checklist

- [ ] Create `WeatherCondition` enum with all cases
- [ ] Add helper methods: `label()`, `chartColor()`, `icon()`, `fromString()`
- [ ] Update `WeatherObservation` entity to use enum type
- [ ] Update all repository methods to return DTOs with enum
- [ ] Update all services to use enum instead of strings
- [ ] Update tests to use enum
- [ ] Run database migration (no schema change needed)
- [ ] Run full test suite

---

## 4. Chart Object and Service

### Problem

**Current Anti-Pattern:**
```php
// src/Service/Dashboard/WeatherAnalysisService.php
private function buildBunchingByWeatherChart(): array
{
    // ❌ 100+ line method returning massive array
    return [
        'title' => [
            'text'      => 'Bunching Rate by Weather Condition',
            'subtext'   => $hasData ? 'Incidents per hour (last 30 days)' : 'No data available yet',
            'left'      => 'center',
            'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
        ],
        'tooltip' => [
            'trigger'     => 'axis',
            'axisPointer' => ['type' => 'shadow'],
        ],
        'xAxis' => [
            'type' => 'category',
            'data' => $conditions,
        ],
        'yAxis' => [
            'type'          => 'value',
            'name'          => 'Incidents/Hour',
            'nameLocation'  => 'middle',
            'nameGap'       => 50,
            'nameTextStyle' => ['fontSize' => 11],
            'min'           => 0,
            'axisLabel'     => [
                'formatter' => '{value}',
            ],
        ],
        'series' => [
            [
                'name'  => 'Bunching Rate',
                'type'  => 'bar',
                'data'  => $data,
                'label' => [
                    'show'      => $hasData,
                    'position'  => 'top',
                    'formatter' => '{c}',
                    'fontSize'  => 11,
                ],
            ],
        ],
        'graphic' => $hasData ? [] : [/* ... */],
        'grid' => [/* ... */],
    ];
}
```

**Issues:**
1. Chart configuration mixed with business logic
2. Hard to test chart structure
3. Duplicated configuration across chart methods
4. No type safety for chart options
5. Hard to reuse chart components
6. 100+ line methods

### Solution

**Create Chart Value Objects:**

```php
<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * Immutable chart configuration.
 */
readonly class Chart
{
    /**
     * @param array<string, mixed> $options ECharts options
     */
    public function __construct(
        public ChartType $type,
        public array $options,
    ) {}

    /**
     * Convert to array for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Chart;

enum ChartType: string
{
    case BAR = 'bar';
    case LINE = 'line';
    case SCATTER = 'scatter';
    case HEATMAP = 'heatmap';
    case PIE = 'pie';
}
```

**Create Chart Builder:**

```php
<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * Fluent builder for ECharts configurations.
 */
class ChartBuilder
{
    private string $title;
    private ?string $subtitle = null;
    private ChartType $type;
    /** @var list<array<string, mixed>> */
    private array $series = [];
    /** @var array<string, mixed> */
    private array $xAxis = [];
    /** @var array<string, mixed> */
    private array $yAxis = [];
    /** @var array<string, mixed> */
    private array $tooltip = [];
    /** @var array<string, mixed> */
    private array $legend = [];
    /** @var array<string, mixed> */
    private array $grid = [];
    /** @var list<array<string, mixed>> */
    private array $graphic = [];

    public function __construct(ChartType $type)
    {
        $this->type = $type;
    }

    public static function bar(): self
    {
        return new self(ChartType::BAR);
    }

    public static function line(): self
    {
        return new self(ChartType::LINE);
    }

    public static function scatter(): self
    {
        return new self(ChartType::SCATTER);
    }

    public static function heatmap(): self
    {
        return new self(ChartType::HEATMAP);
    }

    public function title(string $title, ?string $subtitle = null): self
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        return $this;
    }

    /**
     * Add a data series to the chart.
     *
     * @param array<int, mixed> $data
     */
    public function addSeries(string $name, array $data, ?array $config = []): self
    {
        $this->series[] = array_merge([
            'name' => $name,
            'type' => $this->type->value,
            'data' => $data,
        ], $config);

        return $this;
    }

    /**
     * Configure category X-axis.
     *
     * @param list<string> $categories
     */
    public function categoryXAxis(array $categories): self
    {
        $this->xAxis = [
            'type' => 'category',
            'data' => $categories,
        ];
        return $this;
    }

    /**
     * Configure value Y-axis.
     */
    public function valueYAxis(string $name, ?int $min = null, ?int $max = null): self
    {
        $this->yAxis = [
            'type'          => 'value',
            'name'          => $name,
            'nameLocation'  => 'middle',
            'nameGap'       => 50,
            'nameTextStyle' => ['fontSize' => 11],
        ];

        if ($min !== null) {
            $this->yAxis['min'] = $min;
        }

        if ($max !== null) {
            $this->yAxis['max'] = $max;
        }

        return $this;
    }

    /**
     * Enable axis tooltip.
     */
    public function axisTooltip(): self
    {
        $this->tooltip = [
            'trigger'     => 'axis',
            'axisPointer' => ['type' => 'shadow'],
        ];
        return $this;
    }

    /**
     * Add legend.
     *
     * @param list<string> $items
     */
    public function legend(array $items): self
    {
        $this->legend = ['data' => $items];
        return $this;
    }

    /**
     * Configure grid spacing.
     */
    public function grid(string $left = '3%', string $right = '4%', string $bottom = '3%'): self
    {
        $this->grid = [
            'left'         => $left,
            'right'        => $right,
            'bottom'       => $bottom,
            'containLabel' => true,
        ];
        return $this;
    }

    /**
     * Add "no data" graphic overlay.
     */
    public function noDataGraphic(string $message): self
    {
        $this->graphic[] = [
            'type'  => 'text',
            'left'  => 'center',
            'top'   => 'middle',
            'style' => [
                'text'       => $message,
                'fontSize'   => 14,
                'fill'       => '#94a3b8',
                'textAlign'  => 'center',
                'fontWeight' => 'normal',
            ],
        ];
        return $this;
    }

    /**
     * Build the chart.
     */
    public function build(): Chart
    {
        $options = [
            'title' => [
                'text'      => $this->title,
                'left'      => 'center',
                'textStyle' => ['fontSize' => 18, 'fontWeight' => 'bold'],
            ],
            'series' => $this->series,
        ];

        if ($this->subtitle !== null) {
            $options['title']['subtext'] = $this->subtitle;
        }

        if (!empty($this->xAxis)) {
            $options['xAxis'] = $this->xAxis;
        }

        if (!empty($this->yAxis)) {
            $options['yAxis'] = $this->yAxis;
        }

        if (!empty($this->tooltip)) {
            $options['tooltip'] = $this->tooltip;
        }

        if (!empty($this->legend)) {
            $options['legend'] = $this->legend;
        }

        if (!empty($this->grid)) {
            $options['grid'] = $this->grid;
        }

        if (!empty($this->graphic)) {
            $options['graphic'] = $this->graphic;
        }

        return new Chart($this->type, $options);
    }
}
```

**Simplified Service:**

```php
// src/Service/Dashboard/WeatherAnalysisService.php
use App\Chart\Chart;
use App\Chart\ChartBuilder;

private function buildBunchingByWeatherChart(): Chart
{
    $endDate   = new \DateTimeImmutable('today');
    $startDate = $endDate->modify('-30 days');

    // Fetch data
    $results = $this->bunchingRepo->countByWeatherConditionNormalized($startDate, $endDate);

    // Build data arrays
    $data = [];
    $conditions = [];

    foreach (WeatherCondition::chartConditions() as $condition) {
        $incidentsPerHour = 0;
        $exposureHours    = 0;

        foreach ($results as $dto) {
            if ($dto->weatherCondition === $condition) {
                $incidentsPerHour = $dto->incidentsPerHour;
                $exposureHours    = $dto->exposureHours;
                break;
            }
        }

        $conditions[] = $condition->label();
        $data[] = [
            'value'         => $incidentsPerHour,
            'itemStyle'     => ['color' => $condition->chartColor()],
            'exposureHours' => $exposureHours,
        ];
    }

    $hasData = array_sum(array_column($results, 'incidentCount')) > 0;

    // ✅ Clean, fluent chart building
    $builder = ChartBuilder::bar()
        ->title('Bunching Rate by Weather Condition', $hasData ? 'Incidents per hour (last 30 days)' : 'No data available yet')
        ->categoryXAxis($conditions)
        ->valueYAxis('Incidents/Hour', min: 0)
        ->axisTooltip()
        ->addSeries('Bunching Rate', $data, [
            'label' => [
                'show'      => $hasData,
                'position'  => 'top',
                'formatter' => '{c}',
                'fontSize'  => 11,
            ],
        ])
        ->grid('50', '4%', '10%');

    if (!$hasData) {
        $builder->noDataGraphic("No bunching data yet\n\nRun 'app:detect:bunching' command\nto analyze arrival patterns");
    }

    return $builder->build();
}
```

**Even cleaner with Chart Presets:**

```php
// src/Chart/Preset/WeatherChartPreset.php
class WeatherChartPreset
{
    public static function bunchingRate(array $data, array $conditions, bool $hasData): Chart
    {
        $builder = ChartBuilder::bar()
            ->title('Bunching Rate by Weather Condition', $hasData ? 'Incidents per hour (last 30 days)' : 'No data available yet')
            ->categoryXAxis($conditions)
            ->valueYAxis('Incidents/Hour', min: 0)
            ->axisTooltip()
            ->addSeries('Bunching Rate', $data, [
                'label' => [
                    'show'      => $hasData,
                    'position'  => 'top',
                    'formatter' => '{c}',
                    'fontSize'  => 11,
                ],
            ])
            ->grid('50', '4%', '10%');

        if (!$hasData) {
            $builder->noDataGraphic("No bunching data yet\n\nRun 'app:detect:bunching' command\nto analyze arrival patterns");
        }

        return $builder->build();
    }
}
```

**Ultra-clean service:**

```php
private function buildBunchingByWeatherChart(): Chart
{
    [$data, $conditions, $hasData] = $this->prepareBunchingChartData();

    return WeatherChartPreset::bunchingRate($data, $conditions, $hasData);
}
```

### Chart Presets to Create

```php
// src/Chart/Preset/WeatherChartPreset.php
class WeatherChartPreset
{
    public static function bunchingRate(array $data, array $conditions, bool $hasData): Chart;
    public static function winterOperations(array $routeNames, array $clearData, array $snowData): Chart;
    public static function temperatureThreshold(array $scatterData, array $lineData): Chart;
    public static function weatherImpactMatrix(array $data, array $days, array $hours): Chart;
}

// src/Chart/Preset/PerformanceChartPreset.php
class PerformanceChartPreset
{
    public static function thirtyDayTrend(array $dates, array $onTimeData): Chart;
}

// src/Chart/Preset/OverviewChartPreset.php
class OverviewChartPreset
{
    public static function systemGrade(float $grade, string $trend): Chart;
}
```

### Implementation Checklist

- [ ] Create `Chart` value object
- [ ] Create `ChartType` enum
- [ ] Create `ChartBuilder` class with fluent interface
- [ ] Create chart preset classes for common patterns
- [ ] Update `WeatherAnalysisService` to use chart builder
- [ ] Update `OverviewService` to use chart builder
- [ ] Update `RouteController` to use chart builder
- [ ] Update Twig templates to call `chart.toArray()`
- [ ] Update tests to assert on Chart objects
- [ ] Remove old array-based chart methods

---

## 5. Implementation Roadmap

### Phase 1: Foundation (Week 1)

**Goal:** Establish core infrastructure

- [ ] Create enum: `WeatherCondition`
- [ ] Create chart infrastructure:
  - [ ] `Chart` value object
  - [ ] `ChartType` enum
  - [ ] `ChartBuilder` class
- [ ] Create base DTOs:
  - [ ] `RoutePerformanceSummaryDto`
  - [ ] `BunchingRateDto`
  - [ ] `TemperaturePerformanceDto`

### Phase 2: Repository Refactoring (Week 2)

**Goal:** Move all queries to repositories

#### Day 1-2: RoutePerformanceDailyRepository
- [ ] Create methods:
  - [ ] `findAveragePerformanceByWeatherCondition()`
  - [ ] `findHistoricalTopPerformers()`
  - [ ] `findHistoricalWorstPerformers()`
  - [ ] `findWinterImpactStatistics()`
  - [ ] `findPerformanceByTemperatureRange()`
- [ ] Update tests

#### Day 3: BunchingIncidentRepository
- [ ] Update `countByWeatherConditionNormalized()` to return `BunchingRateDto`
- [ ] Update tests

#### Day 4-5: WeatherObservationRepository
- [ ] Update entity to use `WeatherCondition` enum
- [ ] Test enum persistence
- [ ] Update all services using weather conditions

### Phase 3: Service Refactoring (Week 3)

**Goal:** Update services to use repositories and DTOs

#### Day 1-2: WeatherAnalysisService
- [ ] Update all chart methods to use `ChartBuilder`
- [ ] Remove inline queries
- [ ] Use DTOs from repositories
- [ ] Create `WeatherChartPreset`

#### Day 3: OverviewService
- [ ] Update methods to use repository DTOs
- [ ] Use chart builder
- [ ] Create `OverviewChartPreset`

#### Day 4: RouteController
- [ ] Update to use chart builder
- [ ] Create `PerformanceChartPreset`

#### Day 5: Testing and cleanup
- [ ] Run full test suite
- [ ] Fix any broken tests
- [ ] Update integration tests

### Phase 4: Polish and Deploy (Week 4)

#### Day 1-2: Code Quality
- [ ] Run PHPStan (level 8)
- [ ] Fix all type issues
- [ ] Run PHP-CS-Fixer
- [ ] Code review

#### Day 3: Documentation
- [ ] Update README with new patterns
- [ ] Add PHPDoc to all new classes
- [ ] Update CLAUDE.md with examples

#### Day 4: Testing
- [ ] Manual QA on staging
- [ ] Verify all charts render correctly
- [ ] Check performance (no regressions)

#### Day 5: Deploy
- [ ] Deploy to production
- [ ] Monitor logs
- [ ] Verify no errors

---

## 6. Testing Strategy

### Unit Tests

#### Repository Tests
```php
// tests/Repository/RoutePerformanceDailyRepositoryTest.php
class RoutePerformanceDailyRepositoryTest extends KernelTestCase
{
    public function testFindAveragePerformanceByWeatherConditionReturnsTypedDtos(): void
    {
        $repo = self::getContainer()->get(RoutePerformanceDailyRepository::class);

        $results = $repo->findAveragePerformanceByWeatherCondition(WeatherCondition::CLEAR, minDays: 1);

        $this->assertContainsOnlyInstancesOf(RoutePerformanceSummaryDto::class, $results);

        if (count($results) > 0) {
            $first = $results[0];
            $this->assertIsInt($first->routeId);
            $this->assertIsString($first->shortName);
            $this->assertIsFloat($first->averageOnTimePercentage);
        }
    }
}
```

#### Chart Builder Tests
```php
// tests/Chart/ChartBuilderTest.php
class ChartBuilderTest extends TestCase
{
    public function testBarChartBuildsCorrectStructure(): void
    {
        $chart = ChartBuilder::bar()
            ->title('Test Chart', 'Subtitle')
            ->categoryXAxis(['A', 'B', 'C'])
            ->valueYAxis('Count', min: 0, max: 100)
            ->addSeries('Series 1', [10, 20, 30])
            ->build();

        $this->assertInstanceOf(Chart::class, $chart);
        $this->assertSame(ChartType::BAR, $chart->type);

        $options = $chart->toArray();
        $this->assertEquals('Test Chart', $options['title']['text']);
        $this->assertEquals('Subtitle', $options['title']['subtext']);
        $this->assertCount(1, $options['series']);
        $this->assertEquals('bar', $options['series'][0]['type']);
    }
}
```

#### Enum Tests
```php
// tests/Enum/WeatherConditionTest.php
class WeatherConditionTest extends TestCase
{
    public function testFromStringIsCaseInsensitive(): void
    {
        $this->assertSame(WeatherCondition::SNOW, WeatherCondition::fromString('snow'));
        $this->assertSame(WeatherCondition::SNOW, WeatherCondition::fromString('SNOW'));
        $this->assertSame(WeatherCondition::SNOW, WeatherCondition::fromString('Snow'));
    }

    public function testLabelReturnsHumanReadable(): void
    {
        $this->assertEquals('Clear', WeatherCondition::CLEAR->label());
        $this->assertEquals('Snow Showers', WeatherCondition::SNOW_SHOWERS->label());
    }

    public function testChartColorReturnsHexColor(): void
    {
        $color = WeatherCondition::SNOW->chartColor();
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $color);
    }
}
```

### Integration Tests

```php
// tests/Service/Dashboard/WeatherAnalysisServiceIntegrationTest.php
class WeatherAnalysisServiceIntegrationTest extends KernelTestCase
{
    public function testBuildBunchingByWeatherChartReturnsChartObject(): void
    {
        $service = self::getContainer()->get(WeatherAnalysisService::class);

        $chart = $service->getWeatherImpact()->bunchingByWeatherChart;

        $this->assertInstanceOf(Chart::class, $chart);
        $this->assertSame(ChartType::BAR, $chart->type);

        $options = $chart->toArray();
        $this->assertArrayHasKey('title', $options);
        $this->assertArrayHasKey('series', $options);
        $this->assertArrayHasKey('xAxis', $options);
        $this->assertArrayHasKey('yAxis', $options);
    }
}
```

---

## 7. Rollout Plan

### Pre-Deployment Checklist

- [ ] All unit tests passing
- [ ] All integration tests passing
- [ ] PHPStan level 8 passing
- [ ] PHP-CS-Fixer passing
- [ ] Code reviewed by team
- [ ] Staging environment tested manually

### Deployment Steps

#### Step 1: Deploy to Staging
```bash
git checkout -b refactor/code-quality
# ... make all changes
git push origin refactor/code-quality
# Deploy to staging
```

#### Step 2: Smoke Test on Staging
- [ ] Visit dashboard page (`/`)
- [ ] Visit weather impact page (`/weather-impact`)
- [ ] Visit route detail pages (`/routes/{id}`)
- [ ] Verify all charts render correctly
- [ ] Check browser console for errors
- [ ] Check server logs for errors

#### Step 3: Performance Testing
```bash
# Run performance benchmarks
ab -n 100 -c 10 https://staging.mindthewait.ca/
ab -n 100 -c 10 https://staging.mindthewait.ca/weather-impact
```

Expected: No performance regression (within 5% of baseline)

#### Step 4: Deploy to Production
```bash
git checkout main
git merge refactor/code-quality
git push origin main
# Auto-deploy via CI/CD
```

#### Step 5: Post-Deployment Monitoring
- [ ] Monitor error logs for 24 hours
- [ ] Check Sentry for new errors
- [ ] Verify charts still render correctly
- [ ] Check database query performance

### Rollback Plan

If issues are detected:

```bash
# Revert the merge commit
git revert HEAD
git push origin main
```

**Expected downtime:** 0 minutes (backward compatible changes)

---

## Appendix A: File Structure

```
src/
├── Chart/
│   ├── Chart.php                    # Value object
│   ├── ChartBuilder.php             # Fluent builder
│   ├── ChartType.php                # Enum
│   └── Preset/
│       ├── WeatherChartPreset.php
│       ├── PerformanceChartPreset.php
│       └── OverviewChartPreset.php
├── Dto/
│   ├── BunchingRateDto.php
│   ├── HistoricalPerformerDto.php
│   ├── RoutePerformanceSummaryDto.php
│   ├── TemperaturePerformanceDto.php
│   └── WeatherPerformanceComparisonDto.php
├── Enum/
│   ├── WeatherCondition.php         # New
│   ├── DirectionEnum.php            # Existing
│   ├── RouteTypeEnum.php            # Existing
│   └── ScoreGradeEnum.php           # Existing
├── Repository/
│   ├── RoutePerformanceDailyRepository.php  # Enhanced
│   ├── BunchingIncidentRepository.php       # Enhanced
│   └── WeatherObservationRepository.php     # Enhanced
└── Service/
    └── Dashboard/
        ├── WeatherAnalysisService.php      # Simplified
        └── OverviewService.php             # Simplified

tests/
├── Chart/
│   ├── ChartBuilderTest.php
│   └── Preset/
│       └── WeatherChartPresetTest.php
├── Dto/
│   └── (DTOs don't need tests - they're data classes)
├── Enum/
│   └── WeatherConditionTest.php
└── Repository/
    └── RoutePerformanceDailyRepositoryTest.php
```

---

## Appendix B: Before/After Comparison

### Before: Inline Queries + Array Access

```php
// ❌ 150 lines, hard to test, no type safety
private function buildWinterOperationsChart(): array
{
    // Inline query #1
    $qb = $this->performanceRepo->createQueryBuilder('p');
    $clearResults = $qb->select('r.id', 'r.shortName', 'r.longName', 'AVG(p.onTimePercentage) as avgPerf')
        ->join('p.route', 'r')
        ->leftJoin('p.weatherObservation', 'w')
        ->where('w.weatherCondition = :clear')
        ->andWhere('p.onTimePercentage IS NOT NULL')
        ->setParameter('clear', 'clear')
        ->groupBy('r.id', 'r.shortName', 'r.longName')
        ->having('COUNT(p.id) >= 3')
        ->getQuery()
        ->getResult();

    // Inline query #2
    $qb2 = $this->performanceRepo->createQueryBuilder('p');
    $snowResults = $qb2->select('r.id', 'AVG(p.onTimePercentage) as avgPerf')
        ->join('p.route', 'r')
        ->leftJoin('p.weatherObservation', 'w')
        ->where('w.weatherCondition = :snow')
        // ... 20 more lines

    // Array access (typo-prone, no IDE help)
    foreach ($clearResults as $row) {
        $routeId = $row['id'];
        $shortName = $row['shortName'];
        $avgPerf = (float) $row['avgPerf'];
    }

    // 80+ lines of array building
    return [
        'title' => ['text' => 'Winter Operations', /* ... */],
        'xAxis' => [/* ... */],
        'yAxis' => [/* ... */],
        'series' => [/* ... */],
        'grid' => [/* ... */],
    ];
}
```

### After: Repository + DTOs + Chart Builder

```php
// ✅ 15 lines, testable, type-safe
private function buildWinterOperationsChart(): Chart
{
    // Repository method (tested separately)
    $comparison = $this->performanceRepo->findWinterPerformanceComparison(minDays: 3);

    // Extract typed data from DTOs
    $routeNames = array_map(fn($dto) => $dto->shortName, $comparison);
    $clearData = array_map(fn($dto) => $dto->clearPerformance, $comparison);
    $snowData = array_map(fn($dto) => $dto->snowPerformance, $comparison);

    // Chart preset (tested separately)
    return WeatherChartPreset::winterOperations($routeNames, $clearData, $snowData);
}
```

**Line count:** 150 → 15 (90% reduction)
**Type safety:** None → Full
**Testability:** Hard → Easy

---

## Appendix C: Success Metrics

### Code Quality Metrics

| Metric | Before | After | Target |
|--------|--------|-------|--------|
| PHPStan Level | 5 | 8 | 8 |
| Lines of Code (Services) | ~2,000 | ~800 | <1,000 |
| Cyclomatic Complexity (avg) | 12 | 4 | <5 |
| Test Coverage | 65% | 85% | >80% |
| Array Access (magic strings) | 200+ | 0 | 0 |
| Type Hints | 60% | 100% | 100% |

### Developer Experience Metrics

| Metric | Before | After |
|--------|--------|-------|
| IDE Autocomplete | Partial | Full |
| Compile-time Type Errors | No | Yes |
| Refactoring Safety | Low | High |
| New Developer Onboarding | 2 days | 1 day |

### Performance Metrics

| Metric | Before | After | Acceptable |
|--------|--------|-------|------------|
| Dashboard Load Time | 250ms | <260ms | <300ms |
| Memory Usage | 32MB | <35MB | <40MB |
| Database Queries | 15 | 12-15 | <20 |

---

## Conclusion

This refactoring improves:
1. **Type Safety:** DTOs + Enums replace magic strings and arrays
2. **Separation of Concerns:** Queries in repositories, not services
3. **Maintainability:** Chart builder simplifies complex array construction
4. **Testability:** Small, focused methods with clear responsibilities
5. **Developer Experience:** IDE autocomplete, static analysis, refactoring safety

**Total estimated time:** 3-4 weeks (can be done incrementally)

**Risk level:** Low (backward compatible, incremental deployment)

**ROI:** High (improved code quality pays dividends over time)

---

**Document Version:** 1.0
**Last Updated:** 2025-10-18
**Author:** Implementation Plan
**Status:** Ready for Review
