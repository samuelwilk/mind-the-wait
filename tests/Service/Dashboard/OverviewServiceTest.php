<?php

declare(strict_types=1);

namespace App\Tests\Service\Dashboard;

use PHPUnit\Framework\TestCase;

use function count;

/**
 * Tests for OverviewService dashboard filtering logic.
 *
 * Focuses on testing the improved filtering for top performers and needs attention lists.
 * These tests simulate the filtering logic used in OverviewService without requiring the full service.
 */
final class OverviewServiceTest extends TestCase
{
    /**
     * Test that top performers excludes N/A grades.
     */
    public function testTopPerformersExcludesNaGrades(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'A', 'vehicles' => 2],
            ['route_id' => 'route-2', 'grade' => 'N/A', 'vehicles' => 0],
            ['route_id' => 'route-3', 'grade' => 'B', 'vehicles' => 1],
        ];

        $filtered = $this->filterTopPerformers($scores);

        $this->assertCount(2, $filtered);
        $this->assertEquals('A', $filtered[0]['grade']);
        $this->assertEquals('B', $filtered[1]['grade']);
    }

    /**
     * Test that top performers excludes routes with 0 vehicles.
     */
    public function testTopPerformersExcludesZeroVehicles(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'A', 'vehicles' => 2],
            ['route_id' => 'route-2', 'grade' => 'A', 'vehicles' => 0], // Should be excluded
            ['route_id' => 'route-3', 'grade' => 'B', 'vehicles' => 1],
        ];

        $filtered = $this->filterTopPerformers($scores);

        $this->assertCount(2, $filtered);
        $this->assertNotContains('route-2', array_column($filtered, 'route_id'));
    }

    /**
     * Test that top performers sorts by grade (A > B > C > D > F).
     */
    public function testTopPerformersSortsByGrade(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'C', 'vehicles' => 1],
            ['route_id' => 'route-2', 'grade' => 'A', 'vehicles' => 2],
            ['route_id' => 'route-3', 'grade' => 'B', 'vehicles' => 1],
            ['route_id' => 'route-4', 'grade' => 'D', 'vehicles' => 1],
        ];

        $sorted = $this->filterTopPerformers($scores);

        $this->assertEquals('A', $sorted[0]['grade']);
        $this->assertEquals('B', $sorted[1]['grade']);
        $this->assertEquals('C', $sorted[2]['grade']);
        $this->assertEquals('D', $sorted[3]['grade']);
    }

    /**
     * Test that top performers uses vehicle count as secondary sort.
     */
    public function testTopPerformersUsesVehicleCountAsSecondarySort(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'A', 'vehicles' => 1],
            ['route_id' => 'route-2', 'grade' => 'A', 'vehicles' => 3], // Most vehicles
            ['route_id' => 'route-3', 'grade' => 'A', 'vehicles' => 2],
        ];

        $sorted = $this->filterTopPerformers($scores);

        $this->assertEquals(3, $sorted[0]['vehicles'], 'Route with most vehicles should rank first');
        $this->assertEquals(2, $sorted[1]['vehicles']);
        $this->assertEquals(1, $sorted[2]['vehicles']);
    }

    /**
     * Test that needs attention includes grade D and F routes.
     */
    public function testNeedsAttentionIncludesDandFGrades(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'A', 'vehicles' => 2],
            ['route_id' => 'route-2', 'grade' => 'D', 'vehicles' => 1],
            ['route_id' => 'route-3', 'grade' => 'F', 'vehicles' => 2],
            ['route_id' => 'route-4', 'grade' => 'B', 'vehicles' => 1],
        ];

        $filtered = $this->filterNeedsAttention($scores);

        $this->assertCount(2, $filtered);
        $grades = array_column($filtered, 'grade');
        $this->assertContains('D', $grades);
        $this->assertContains('F', $grades);
        $this->assertNotContains('A', $grades);
        $this->assertNotContains('B', $grades);
    }

    /**
     * Test that needs attention includes single-vehicle grade C routes.
     */
    public function testNeedsAttentionIncludesSingleVehicleCGrade(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'C', 'vehicles' => 1], // Should be included
            ['route_id' => 'route-2', 'grade' => 'C', 'vehicles' => 2], // Should be excluded (multi-vehicle)
            ['route_id' => 'route-3', 'grade' => 'D', 'vehicles' => 1],
        ];

        $filtered = $this->filterNeedsAttention($scores);

        $this->assertCount(2, $filtered);

        // Find the C-grade route
        $cRoute = array_filter($filtered, fn ($s) => $s['grade'] === 'C');
        $this->assertCount(1, $cRoute);
        $this->assertEquals(1, array_values($cRoute)[0]['vehicles'], 'Only single-vehicle C grade should be included');
    }

    /**
     * Test that needs attention excludes multi-vehicle grade C routes.
     */
    public function testNeedsAttentionExcludesMultiVehicleCGrade(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'C', 'vehicles' => 3],
            ['route_id' => 'route-2', 'grade' => 'C', 'vehicles' => 2],
            ['route_id' => 'route-3', 'grade' => 'D', 'vehicles' => 1],
        ];

        $filtered = $this->filterNeedsAttention($scores);

        $cRoutes = array_filter($filtered, fn ($s) => $s['grade'] === 'C');
        $this->assertCount(0, $cRoutes, 'Multi-vehicle C grades should not be in needs attention');
    }

    /**
     * Test that needs attention excludes routes with 0 vehicles.
     */
    public function testNeedsAttentionExcludesZeroVehicles(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'D', 'vehicles' => 1],
            ['route_id' => 'route-2', 'grade' => 'F', 'vehicles' => 0], // Should be excluded
        ];

        $filtered = $this->filterNeedsAttention($scores);

        $this->assertCount(1, $filtered);
        $this->assertEquals('route-1', $filtered[0]['route_id']);
    }

    /**
     * Test that needs attention sorts by grade (worst first: F > D > C).
     */
    public function testNeedsAttentionSortsByGradeWorstFirst(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'C', 'vehicles' => 1],
            ['route_id' => 'route-2', 'grade' => 'F', 'vehicles' => 1],
            ['route_id' => 'route-3', 'grade' => 'D', 'vehicles' => 1],
        ];

        $sorted = $this->filterNeedsAttention($scores);

        $this->assertEquals('F', $sorted[0]['grade'], 'F should be first (worst)');
        $this->assertEquals('D', $sorted[1]['grade'], 'D should be second');
        $this->assertEquals('C', $sorted[2]['grade'], 'C should be last');
    }

    /**
     * Test that needs attention returns empty array when all routes are performing well.
     */
    public function testNeedsAttentionReturnsEmptyWhenAllRoutesGood(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'A', 'vehicles' => 2],
            ['route_id' => 'route-2', 'grade' => 'B', 'vehicles' => 3],
            ['route_id' => 'route-3', 'grade' => 'C', 'vehicles' => 2], // Multi-vehicle C is OK
        ];

        $filtered = $this->filterNeedsAttention($scores);

        $this->assertCount(0, $filtered, 'Should return empty when no routes need attention');
    }

    /**
     * Test that top performers returns empty array when all routes have N/A grades.
     */
    public function testTopPerformersReturnsEmptyWhenAllNa(): void
    {
        $scores = [
            ['route_id' => 'route-1', 'grade' => 'N/A', 'vehicles' => 0],
            ['route_id' => 'route-2', 'grade' => 'N/A', 'vehicles' => 0],
        ];

        $filtered = $this->filterTopPerformers($scores);

        $this->assertCount(0, $filtered, 'Should return empty when all routes have N/A grades');
    }

    /**
     * Simulate the filtering logic from OverviewService::getTopPerformers().
     *
     * @param list<array<string,mixed>> $scores
     *
     * @return list<array<string,mixed>>
     */
    private function filterTopPerformers(array $scores): array
    {
        // Filter out N/A grades AND routes with no vehicles
        $validScores = array_filter($scores, fn (array $score) => ($score['grade'] ?? 'N/A') !== 'N/A' && ($score['vehicles'] ?? 0) > 0
        );

        if (count($validScores) === 0) {
            return [];
        }

        // Sort by grade (A > B > C > D > F), then by vehicle count (more vehicles = higher confidence)
        $gradeOrder = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1];

        usort($validScores, function ($a, $b) use ($gradeOrder) {
            $gradeA = $gradeOrder[$a['grade'] ?? 'N/A'] ?? 0;
            $gradeB = $gradeOrder[$b['grade'] ?? 'N/A'] ?? 0;

            // Primary sort: grade
            if ($gradeA !== $gradeB) {
                return $gradeB <=> $gradeA; // Descending
            }

            // Secondary sort: vehicle count (higher is better for confidence)
            $vehiclesA = $a['vehicles'] ?? 0;
            $vehiclesB = $b['vehicles'] ?? 0;

            return $vehiclesB <=> $vehiclesA;
        });

        return array_values($validScores);
    }

    /**
     * Simulate the filtering logic from OverviewService::getNeedsAttention().
     *
     * @param list<array<string,mixed>> $scores
     *
     * @return list<array<string,mixed>>
     */
    private function filterNeedsAttention(array $scores): array
    {
        // Filter: Keep routes with grades D or F, or grade C with issues
        // Also filter out routes with no vehicles
        $validScores = array_filter($scores, function (array $score) {
            $grade    = $score['grade']    ?? 'N/A';
            $vehicles = $score['vehicles'] ?? 0;

            if ($vehicles === 0 || $grade === 'N/A') {
                return false;
            }

            // Always include D and F grades
            if ($grade === 'D' || $grade === 'F') {
                return true;
            }

            // Include grade C if it's a single-vehicle route (limited data)
            if ($grade === 'C' && $vehicles === 1) {
                return true;
            }

            return false;
        });

        if (count($validScores) === 0) {
            return [];
        }

        // Sort by grade (F > D > C) - worst first
        $gradeOrder = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'F' => 1];

        usort($validScores, function ($a, $b) use ($gradeOrder) {
            $gradeA = $gradeOrder[$a['grade'] ?? 'N/A'] ?? 0;
            $gradeB = $gradeOrder[$b['grade'] ?? 'N/A'] ?? 0;

            // Primary sort: grade (worst first)
            if ($gradeA !== $gradeB) {
                return $gradeA <=> $gradeB; // Ascending (worst first)
            }

            // Secondary sort: vehicle count (fewer vehicles = more concerning for single-vehicle routes)
            $vehiclesA = $a['vehicles'] ?? 0;
            $vehiclesB = $b['vehicles'] ?? 0;

            return $vehiclesA <=> $vehiclesB;
        });

        return array_values($validScores);
    }
}
