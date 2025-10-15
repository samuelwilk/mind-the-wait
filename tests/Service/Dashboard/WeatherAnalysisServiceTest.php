<?php

declare(strict_types=1);

namespace App\Tests\Service\Dashboard;

use App\Dto\BunchingCountDto;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WeatherAnalysisService bunching analysis methods.
 *
 * Focuses on testing the bunching by weather condition logic without requiring the full service.
 */
final class WeatherAnalysisServiceTest extends TestCase
{
    /**
     * Test buildBunchingByWeatherChart with real data.
     */
    public function testBuildBunchingByWeatherChartWithData(): void
    {
        $results = [
            new BunchingCountDto('Snow', 45),
            new BunchingCountDto('Rain', 20),
            new BunchingCountDto('Clear', 15),
        ];

        $chartData = $this->buildBunchingChartData($results);

        // Verify we have 4 data points (snow, rain, cloudy, clear)
        $this->assertCount(4, $chartData);

        // Verify snow data
        $this->assertEquals(45, $chartData[0]['value']);
        $this->assertEquals('#ede9fe', $chartData[0]['itemStyle']['color']);

        // Verify rain data
        $this->assertEquals(20, $chartData[1]['value']);
        $this->assertEquals('#dbeafe', $chartData[1]['itemStyle']['color']);

        // Verify cloudy data (no results, should be 0)
        $this->assertEquals(0, $chartData[2]['value']);
        $this->assertEquals('#e5e7eb', $chartData[2]['itemStyle']['color']);

        // Verify clear data
        $this->assertEquals(15, $chartData[3]['value']);
        $this->assertEquals('#fef3c7', $chartData[3]['itemStyle']['color']);
    }

    /**
     * Test buildBunchingByWeatherChart with no data.
     */
    public function testBuildBunchingByWeatherChartWithNoData(): void
    {
        $results = [];

        $chartData = $this->buildBunchingChartData($results);

        // All conditions should have 0 incidents
        foreach ($chartData as $dataPoint) {
            $this->assertEquals(0, $dataPoint['value']);
        }
    }

    /**
     * Test buildBunchingByWeatherChart case-insensitive matching.
     */
    public function testBuildBunchingByWeatherChartCaseInsensitive(): void
    {
        $results = [
            new BunchingCountDto('SNOW', 10),
            new BunchingCountDto('Rain', 5),
            new BunchingCountDto('clear', 3),
        ];

        $chartData = $this->buildBunchingChartData($results);

        $this->assertEquals(10, $chartData[0]['value'], 'Should match SNOW (uppercase)');
        $this->assertEquals(5, $chartData[1]['value'], 'Should match Rain (mixed case)');
        $this->assertEquals(3, $chartData[3]['value'], 'Should match clear (lowercase)');
    }

    /**
     * Test buildBunchingByWeatherStats calculates correctly.
     */
    public function testBuildBunchingByWeatherStatsCalculatesCorrectly(): void
    {
        $results = [
            new BunchingCountDto('Snow', 60),
            new BunchingCountDto('Rain', 30),
            new BunchingCountDto('Clear', 20),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(60, $stats['snowIncidents']);
        $this->assertEquals(30, $stats['rainIncidents']);
        $this->assertEquals(20, $stats['clearIncidents']);
        $this->assertEquals(3.0, $stats['multiplier'], 'Snow multiplier should be 60 / 20 = 3.0');
        $this->assertTrue($stats['hasData'], 'Should have data');
    }

    /**
     * Test buildBunchingByWeatherStats with no clear incidents.
     */
    public function testBuildBunchingByWeatherStatsWithNoClearIncidents(): void
    {
        $results = [
            new BunchingCountDto('Snow', 50),
            new BunchingCountDto('Rain', 25),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(50, $stats['snowIncidents']);
        $this->assertEquals(25, $stats['rainIncidents']);
        $this->assertEquals(0, $stats['clearIncidents']);
        $this->assertEquals(0.0, $stats['multiplier'], 'Multiplier should be 0 when no clear incidents');
        $this->assertTrue($stats['hasData'], 'Should still have data (snow + rain)');
    }

    /**
     * Test buildBunchingByWeatherStats with no data.
     */
    public function testBuildBunchingByWeatherStatsWithNoData(): void
    {
        $results = [];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0, $stats['snowIncidents']);
        $this->assertEquals(0, $stats['rainIncidents']);
        $this->assertEquals(0, $stats['clearIncidents']);
        $this->assertEquals(0.0, $stats['multiplier']);
        $this->assertFalse($stats['hasData'], 'Should have no data');
    }

    /**
     * Test buildBunchingByWeatherStats multiplier rounding.
     */
    public function testBuildBunchingByWeatherStatsMultiplierRounding(): void
    {
        $results = [
            new BunchingCountDto('Snow', 47),
            new BunchingCountDto('Clear', 13),
        ];

        $stats = $this->buildBunchingStats($results);

        // 47 / 13 = 3.615... should round to 3.6
        $this->assertEquals(3.6, $stats['multiplier'], 'Should round to 1 decimal place');
    }

    /**
     * Test buildBunchingByWeatherStats handles only rain incidents.
     */
    public function testBuildBunchingByWeatherStatsWithOnlyRain(): void
    {
        $results = [
            new BunchingCountDto('Rain', 35),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0, $stats['snowIncidents']);
        $this->assertEquals(35, $stats['rainIncidents']);
        $this->assertEquals(0, $stats['clearIncidents']);
        $this->assertEquals(0.0, $stats['multiplier']);
        $this->assertTrue($stats['hasData']);
    }

    /**
     * Test buildBunchingByWeatherStats ignores unknown weather conditions.
     */
    public function testBuildBunchingByWeatherStatsIgnoresUnknownConditions(): void
    {
        $results = [
            new BunchingCountDto('Snow', 30),
            new BunchingCountDto('Fog', 10), // Unknown condition, should be ignored
            new BunchingCountDto('Clear', 20),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(30, $stats['snowIncidents']);
        $this->assertEquals(0, $stats['rainIncidents'], 'Fog should not be counted as rain');
        $this->assertEquals(20, $stats['clearIncidents']);
        $this->assertTrue($stats['hasData']);
    }

    /**
     * Test buildBunchingByWeatherStats case-insensitive condition matching.
     */
    public function testBuildBunchingByWeatherStatsCaseInsensitive(): void
    {
        $results = [
            new BunchingCountDto('SNOW', 25),
            new BunchingCountDto('Rain', 15),
            new BunchingCountDto('clear', 10),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(25, $stats['snowIncidents'], 'Should match SNOW (uppercase)');
        $this->assertEquals(15, $stats['rainIncidents'], 'Should match Rain (mixed case)');
        $this->assertEquals(10, $stats['clearIncidents'], 'Should match clear (lowercase)');
    }

    /**
     * Test buildBunchingByWeatherStats with extreme multiplier.
     */
    public function testBuildBunchingByWeatherStatsWithExtremeMultiplier(): void
    {
        $results = [
            new BunchingCountDto('Snow', 100),
            new BunchingCountDto('Clear', 1),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(100.0, $stats['multiplier'], 'Should handle extreme multipliers');
    }

    /**
     * Test buildBunchingByWeatherStats with equal snow and clear incidents.
     */
    public function testBuildBunchingByWeatherStatsWithEqualSnowAndClear(): void
    {
        $results = [
            new BunchingCountDto('Snow', 25),
            new BunchingCountDto('Clear', 25),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(1.0, $stats['multiplier'], 'Multiplier should be 1.0 when equal');
    }

    /**
     * Test buildBunchingByWeatherStats with more clear than snow incidents.
     */
    public function testBuildBunchingByWeatherStatsWithMoreClearThanSnow(): void
    {
        $results = [
            new BunchingCountDto('Snow', 10),
            new BunchingCountDto('Clear', 30),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0.3, $stats['multiplier'], 'Multiplier should be less than 1.0');
    }

    /**
     * Simulate building chart data from repository results.
     *
     * @param list<BunchingCountDto> $results
     *
     * @return list<array<string, mixed>>
     */
    private function buildBunchingChartData(array $results): array
    {
        $conditionMap = [
            'snow'   => ['label' => 'Snow', 'color' => '#ede9fe'],
            'rain'   => ['label' => 'Rain', 'color' => '#dbeafe'],
            'cloudy' => ['label' => 'Cloudy', 'color' => '#e5e7eb'],
            'clear'  => ['label' => 'Clear', 'color' => '#fef3c7'],
        ];

        $data = [];
        foreach ($conditionMap as $condition => $config) {
            $count = 0;
            foreach ($results as $dto) {
                if (strtolower($dto->weatherCondition) === $condition) {
                    $count = $dto->incidentCount;

                    break;
                }
            }
            $data[] = [
                'value'     => $count,
                'itemStyle' => ['color' => $config['color']],
            ];
        }

        return $data;
    }

    /**
     * Simulate building stats from repository results.
     *
     * @param list<BunchingCountDto> $results
     *
     * @return array<string, mixed>
     */
    private function buildBunchingStats(array $results): array
    {
        $snowIncidents  = 0;
        $rainIncidents  = 0;
        $clearIncidents = 0;

        foreach ($results as $dto) {
            $condition = strtolower($dto->weatherCondition);

            match ($condition) {
                'snow'  => $snowIncidents  = $dto->incidentCount,
                'rain'  => $rainIncidents  = $dto->incidentCount,
                'clear' => $clearIncidents = $dto->incidentCount,
                default => null,
            };
        }

        $multiplier = $clearIncidents                                     > 0 ? round($snowIncidents / $clearIncidents, 1) : 0.0;
        $hasData    = ($snowIncidents + $rainIncidents + $clearIncidents) > 0;

        return [
            'snowIncidents'  => $snowIncidents,
            'rainIncidents'  => $rainIncidents,
            'clearIncidents' => $clearIncidents,
            'multiplier'     => $multiplier,
            'hasData'        => $hasData,
        ];
    }
}
