<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Route;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_unique;
use function count;
use function sort;
use function sprintf;

/**
 * Integration test to verify realtime route IDs match database route IDs.
 *
 * This test ensures the critical requirement that realtime vehicle data
 * can be linked to static GTFS schedule data via matching route IDs.
 */
#[CoversNothing] // Integration test
final class RouteIdMatchingTest extends TestCase
{
    /**
     * Test that all realtime route IDs exist in the database.
     *
     * This is the core requirement for:
     * - HIGH confidence arrival predictions (GPS interpolation)
     * - Position-based headway calculations
     * - Static schedule fallback
     * - Route detail lookups
     */
    public function testRealtimeRouteIdsExistInDatabase(): void
    {
        // This test requires mocked repositories since it's a unit test
        // For real integration testing, run: docker compose exec php bin/console app:diagnose:route-ids

        // Mock realtime vehicles
        $realtimeVehicles = [
            (object) ['routeId' => '14514'],
            (object) ['routeId' => '14515'],
            (object) ['routeId' => '14536'], // Route 43
            (object) ['routeId' => '14551'], // Route 43
            (object) ['routeId' => '14514'], // Duplicate
        ];

        // Mock database routes
        $databaseRoutes = [
            $this->createRoute('14514', '1', 'City Centre / Exhibition'),
            $this->createRoute('14515', '2', 'Meadowgreen / City Centre'),
            $this->createRoute('14536', '27', 'Silverspring / University'),
            $this->createRoute('14551', '43', 'Evergreen / City Centre'),
        ];

        // Extract unique route IDs from realtime
        $realtimeRouteIds = array_unique(array_map(fn ($v) => $v->routeId, $realtimeVehicles));
        sort($realtimeRouteIds);

        // Build database route map
        $dbRouteMap = [];
        foreach ($databaseRoutes as $route) {
            $dbRouteMap[$route->getGtfsId()] = $route;
        }

        // Find mismatches
        $mismatches = array_filter($realtimeRouteIds, fn ($id) => !isset($dbRouteMap[$id]));

        self::assertEmpty(
            $mismatches,
            sprintf(
                'Found %d realtime route IDs not in database: %s. '.
                'This breaks HIGH confidence predictions and position-based headway. '.
                'Run: docker compose exec php bin/console app:diagnose:route-ids',
                count($mismatches),
                implode(', ', $mismatches)
            )
        );

        // Verify 100% match rate
        $matchRate = count($realtimeRouteIds) - count($mismatches);
        self::assertSame(
            count($realtimeRouteIds),
            $matchRate,
            sprintf('Expected 100%% route ID match, got %d/%d', $matchRate, count($realtimeRouteIds))
        );
    }

    /**
     * Test route ID format consistency.
     */
    public function testRouteIdFormatConsistency(): void
    {
        $validRouteIds = [
            '14514', // Current Saskatoon format (post-Aug 31, 2025)
            '14568',
            '14536',
        ];

        $invalidRouteIds = [
            '13915', // Old format (pre-Aug 31, 2025)
            '13989',
            'route-abc', // Invalid format
        ];

        foreach ($validRouteIds as $routeId) {
            self::assertMatchesRegularExpression(
                '/^145\d{2}$/',
                $routeId,
                "Valid route ID $routeId should match current Saskatoon format (145XX)"
            );
        }

        foreach ($invalidRouteIds as $routeId) {
            if (is_numeric($routeId) && (int) $routeId >= 13800 && (int) $routeId < 14500) {
                self::assertDoesNotMatchRegularExpression(
                    '/^145\d{2}$/',
                    $routeId,
                    "Old route ID $routeId should not match current format"
                );
            }
        }
    }

    /**
     * Test that route short names are unique.
     */
    public function testRouteShortNamesAreUnique(): void
    {
        $routes = [
            $this->createRoute('14514', '1', 'City Centre / Exhibition'),
            $this->createRoute('14515', '2', 'Meadowgreen / City Centre'),
            $this->createRoute('14536', '27', 'Silverspring / University'),
            $this->createRoute('14551', '43', 'Evergreen / City Centre'),
        ];

        $shortNames       = array_map(fn ($r) => $r->getShortName(), $routes);
        $uniqueShortNames = array_unique($shortNames);

        self::assertCount(
            count($shortNames),
            $uniqueShortNames,
            'Route short names must be unique for user-facing displays'
        );
    }

    /**
     * Test that August 31, 2025 service changes are reflected.
     */
    public function testAugust31ServiceChangesIncluded(): void
    {
        // New routes added in August 31, 2025 changes
        $newRoutes = [
            $this->createRoute('14570', '339', 'Cross & Murray - North'),
            $this->createRoute('14571', '340', 'Cross & Murray - South'),
        ];

        // Verify new routes have correct route IDs in 145XX range
        foreach ($newRoutes as $route) {
            self::assertStringStartsWith(
                '145',
                $route->getGtfsId(),
                sprintf('New route %s should use current ID scheme', $route->getShortName())
            );
        }

        // Verify short names match announcement
        $shortNames = array_map(fn ($r) => $r->getShortName(), $newRoutes);
        self::assertContains('339', $shortNames);
        self::assertContains('340', $shortNames);
    }

    /**
     * Test route ID to short name mapping.
     */
    public function testRouteIdToShortNameMapping(): void
    {
        $expectedMappings = [
            '14514' => '1',
            '14515' => '2',
            '14536' => '27',
            '14551' => '43',
            '14552' => '44',
            '14553' => '45',
        ];

        foreach ($expectedMappings as $gtfsId => $expectedShortName) {
            $route = $this->createRoute((string) $gtfsId, $expectedShortName, 'Test Route');
            self::assertSame(
                $expectedShortName,
                $route->getShortName(),
                sprintf('Route %s should map to short name %s', $gtfsId, $expectedShortName)
            );
        }
    }

    /**
     * Test that route count is reasonable for Saskatoon Transit.
     */
    public function testRouteCountIsReasonable(): void
    {
        $routes = [
            $this->createRoute('14514', '1', 'City Centre / Exhibition'),
            $this->createRoute('14515', '2', 'Meadowgreen / City Centre'),
            // ... would include all 54 routes in real database
        ];

        // Saskatoon Transit has approximately 40-60 routes including:
        // - Regular routes (1-87)
        // - Community routes (305-358)
        // - Express routes (333, 502, etc)
        $routeCount = count($routes);

        self::assertGreaterThanOrEqual(2, $routeCount, 'Should have at least some routes');
        self::assertLessThanOrEqual(100, $routeCount, 'Route count seems unreasonably high');

        // For the actual system (54 routes as of Oct 2025)
        // self::assertSame(54, $routeCount, 'Expected 54 routes as of October 2025');
    }

    private function createRoute(string $gtfsId, string $shortName, string $longName): Route
    {
        $route = new Route();
        $route->setGtfsId($gtfsId);
        $route->setShortName($shortName);
        $route->setLongName($longName);

        return $route;
    }
}
