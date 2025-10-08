# Testing Guide

Comprehensive guide to writing and running tests in mind-the-wait.

## Test Philosophy

- **Comprehensive coverage** - Test all business logic, edge cases, and failure modes
- **Fast feedback** - Tests run in <1 second, no external dependencies
- **Clear intent** - Test names describe behavior, not implementation
- **Maintainable** - Stubs/mocks are simple and focused

## Running Tests

### Full Test Suite

```bash
make test-phpunit
```

Or directly:

```bash
docker compose exec php vendor/bin/phpunit --configuration phpunit.dist.xml
```

### Specific Test File

```bash
docker compose exec php vendor/bin/phpunit tests/Service/Realtime/VehicleStatusServiceTest.php
```

### Test Documentation Format

```bash
docker compose exec php vendor/bin/phpunit --testdox
```

Output:
```
Vehicle Status Service (App\Tests\Service\Realtime\VehicleStatusService)
 ✔ Enrich snapshot adds status and feedback
 ✔ Green status for way early
 ✔ Yellow status for on time
 ...
```

## Test Structure

### Directory Layout

```
tests/
├── Service/
│   ├── Headway/
│   │   └── HeadwayCalculatorTest.php
│   └── Realtime/
│       ├── VehicleStatusServiceTest.php
│       └── HeuristicTrafficReasonProviderTest.php
└── ... (future: Controller/, Repository/, etc.)
```

### Naming Conventions

**Test Files:** `{ClassName}Test.php`
- `VehicleStatusServiceTest.php`
- `HeadwayCalculatorTest.php`

**Test Methods:** `test{BehaviorDescription}`
- `testEnrichSnapshotAddsStatusAndFeedback()`
- `testGreenStatusForWayEarly()`
- `testReturnsNullForMinorDelays()`

**Stub Classes:** `Stub{Interface}`
- `StubStopTimeProvider`
- `StubTrafficReasonProvider`

## Writing Tests

### Basic Structure

```php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MyService::class)]
final class MyServiceTest extends TestCase
{
    public function testSomeBehavior(): void
    {
        // Arrange: Set up dependencies and inputs
        $service = new MyService(/* dependencies */);
        $input = /* test data */;

        // Act: Execute the method under test
        $result = $service->doSomething($input);

        // Assert: Verify expected outcome
        self::assertSame($expected, $result);
    }
}
```

### Using Stubs

Instead of mocking frameworks, we prefer simple stub classes:

```php
final class StubStopTimeProvider implements StopTimeProviderInterface
{
    public function __construct(private array $data) {}

    public function getStopTimesForTrip(string $tripId): ?array
    {
        return $this->data[$tripId] ?? null;
    }
}
```

Benefits:
- Type-safe
- Easy to understand
- No mock framework magic
- Reusable across tests

### Testing Vehicle Status

Example from `VehicleStatusServiceTest.php`:

```php
public function testOrangeStatusForSlightlyLate(): void
{
    $now = time();

    $service = new VehicleStatusService(
        new StubStopTimeProvider([
            'trip-orange' => [
                ['stop_id' => 'STOP5', 'seq' => 3, 'arr' => $now + 200, 'dep' => null, 'delay' => 120],
            ],
        ]),
        new StubTrafficReasonProvider(null),
        new StubFeedbackRepository([])
    );

    $snapshot = [
        'ts'       => $now,
        'vehicles' => [[
            'id'    => 'veh-5',
            'route' => '50',
            'trip'  => 'trip-orange',
            'ts'    => $now,
        ]],
    ];

    $result = $service->enrichSnapshot($snapshot);

    $status = $result['vehicles'][0]['status'];
    self::assertSame(VehicleStatusColor::ORANGE->value, $status['color']);
    self::assertSame(VehiclePunctualityLabel::LATE->value, $status['label']);
    self::assertSame('🐌 fashionably late', $status['severity']);
    self::assertSame(120, $status['deviation_sec']);
}
```

### Testing Probabilistic Behavior

For features with randomness (like dad jokes), run multiple iterations:

```php
public function testDadJokesCanBeReturned(): void
{
    $provider = new HeuristicTrafficReasonProvider();
    $vehicle  = new VehicleDto(/* ... */);

    $gotJoke = false;
    for ($i = 0; $i < 100; ++$i) {
        $reason = $provider->reasonFor($vehicle, 300);
        if ($reason !== null && str_contains($reason, 'pigeon')) {
            $gotJoke = true;
            break;
        }
    }

    self::assertTrue($gotJoke, 'Expected to receive at least one dad joke in 100 attempts');
}
```

## Test Coverage

### Current Coverage

As of latest commit:

| Component | Tests | Assertions | Coverage |
|-----------|-------|------------|----------|
| VehicleStatusService | 8 | 33+ | All color/severity combinations |
| HeuristicTrafficReasonProvider | 5 | 12+ | All reason types + dad jokes |
| HeadwayCalculator | 3 | 5+ | All fallback tiers |
| **Total** | **16** | **50+** | **Core business logic** |

### Coverage Goals

- ✅ All color classifications (green, blue, yellow, orange, red, purple)
- ✅ All severity labels (8 emoji variants)
- ✅ All traffic reason types (severe, moderate, light, low demand)
- ✅ Dad joke probability verification
- ✅ Headway calculation fallbacks (predicted → interpolated → timestamp)
- ⏳ Controller integration tests (future)
- ⏳ Repository tests with real Redis (future)

### Missing Coverage (Acceptable)

These are intentionally skipped for pragmatism:

- **Database integration**: Doctrine repositories use battle-tested ORM
- **Redis operations**: Predis is well-tested upstream
- **GTFS parsing**: CSV/JSON parsing is straightforward
- **Python sidecar**: Separate language, separate test suite

## Assertions

### Common Assertions

```php
// Equality
self::assertSame($expected, $actual);     // Strict equality (===)
self::assertEquals($expected, $actual);   // Loose equality (==)

// Nullability
self::assertNull($value);
self::assertNotNull($value);

// Collections
self::assertCount(3, $array);
self::assertEmpty($array);
self::assertContains('item', $array);

// Strings
self::assertStringContainsString('needle', $haystack);
self::assertStringStartsWith('prefix', $string);
self::assertMatchesRegularExpression('/pattern/', $string);

// Arrays
self::assertArrayHasKey('key', $array);
self::assertArrayNotHasKey('key', $array);

// Booleans
self::assertTrue($condition);
self::assertFalse($condition);

// Types
self::assertInstanceOf(MyClass::class, $object);
```

### Custom Failure Messages

```php
self::assertTrue(
    $gotJoke,
    'Expected to receive at least one dad joke in 100 attempts'
);
```

## Debugging Tests

### Run Single Test Method

```bash
docker compose exec php vendor/bin/phpunit \
  --filter testGreenStatusForWayEarly \
  tests/Service/Realtime/VehicleStatusServiceTest.php
```

### Print Debug Output

```php
public function testSomething(): void
{
    $result = $service->doThing();
    var_dump($result);  // Will show in test output
    fwrite(STDERR, print_r($result, true));  // Alternative

    self::assertTrue(true);
}
```

### Stop on Failure

```bash
docker compose exec php vendor/bin/phpunit --stop-on-failure
```

## Test Data Patterns

### Time-Based Tests

Always use `time()` for "now":

```php
$now = time();
$futureTime = $now + 300;  // 5 minutes from now
$pastTime = $now - 300;    // 5 minutes ago
```

### Vehicle Fixtures

```php
$vehicle = [
    'id'    => 'veh-123',
    'route' => '10',
    'trip'  => 'trip-abc',
    'ts'    => time(),
];
```

### Stop Time Fixtures

```php
$stopTimes = [
    'trip-1' => [
        [
            'stop_id' => 'STOP1',
            'seq'     => 10,
            'arr'     => time() + 180,  // Arriving in 3 minutes
            'dep'     => null,
            'delay'   => 120,           // 2 minutes late
        ],
    ],
];
```

## Continuous Integration

### Pre-Commit Hook

Git hook runs tests before commit:

```bash
#!/bin/sh
make test-phpunit || exit 1
```

Located in `.githooks/pre-commit`.

### GitHub Actions (Future)

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - run: make docker-build
      - run: make test-phpunit
```

## Best Practices

### ✅ DO

- Test one behavior per test method
- Use descriptive test names
- Keep tests fast (<100ms each)
- Use stubs over mocks
- Test edge cases (null, empty, boundary values)
- Assert on specific values, not just "not null"

### ❌ DON'T

- Test implementation details (private methods)
- Use production databases/Redis in tests
- Have tests depend on execution order
- Sleep or wait for time to pass
- Test framework code (trust Symfony/Doctrine)
- Duplicate coverage (one test per behavior)

## Troubleshooting

### Tests Fail Locally But Pass in CI

- Check for time zone differences (`date_default_timezone_set`)
- Verify Docker volumes are fresh (`docker compose down -v`)
- Ensure `.env.test` is loaded

### Random Test Failures

- Probabilistic tests might fail occasionally (increase iterations)
- Use `time()` instead of hardcoded timestamps
- Avoid file system or network dependencies

### Slow Tests

- Check for database queries (use stubs instead)
- Avoid sleep/usleep
- Profile with `--log-junit` flag

## Future Test Enhancements

- [ ] Integration tests with test database
- [ ] API tests with HTTP client
- [ ] Load testing for scoring performance
- [ ] Contract tests for GTFS-RT parsing
- [ ] Mutation testing with Infection
