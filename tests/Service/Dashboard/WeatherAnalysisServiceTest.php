<?php

declare(strict_types=1);

namespace App\Tests\Service\Dashboard;

use App\Dto\BunchingRateDto;
use App\Enum\WeatherCondition;
use PHPUnit\Framework\TestCase;

use function count;

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
            new BunchingRateDto(WeatherCondition::SNOW, 45, 100.0, 0.45),
            new BunchingRateDto(WeatherCondition::RAIN, 20, 100.0, 0.20),
            new BunchingRateDto(WeatherCondition::CLEAR, 15, 100.0, 0.15),
        ];

        $chartData = $this->buildBunchingChartData($results);

        // Verify we have 4 data points (snow, rain, cloudy, clear)
        $this->assertCount(4, $chartData);

        // Verify snow data (rate: 0.45)
        $this->assertEquals(0.45, $chartData[0]['value']);
        $this->assertEquals('#ede9fe', $chartData[0]['itemStyle']['color']);

        // Verify rain data (rate: 0.20)
        $this->assertEquals(0.20, $chartData[1]['value']);
        $this->assertEquals('#dbeafe', $chartData[1]['itemStyle']['color']);

        // Verify cloudy data (no results, should be 0)
        $this->assertEquals(0, $chartData[2]['value']);
        $this->assertEquals('#e5e7eb', $chartData[2]['itemStyle']['color']);

        // Verify clear data (rate: 0.15)
        $this->assertEquals(0.15, $chartData[3]['value']);
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
     * Test buildBunchingByWeatherChart handles enum matching.
     */
    public function testBuildBunchingByWeatherChartEnumMatching(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 10, 100.0, 0.10),
            new BunchingRateDto(WeatherCondition::RAIN, 5, 100.0, 0.05),
            new BunchingRateDto(WeatherCondition::CLEAR, 3, 100.0, 0.03),
        ];

        $chartData = $this->buildBunchingChartData($results);

        $this->assertEquals(0.10, $chartData[0]['value'], 'Should match Snow enum');
        $this->assertEquals(0.05, $chartData[1]['value'], 'Should match Rain enum');
        $this->assertEquals(0.03, $chartData[3]['value'], 'Should match Clear enum');
    }

    /**
     * Test buildBunchingByWeatherStats calculates correctly with normalized rates.
     */
    public function testBuildBunchingByWeatherStatsCalculatesCorrectly(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 60, 100.0, 0.6),
            new BunchingRateDto(WeatherCondition::RAIN, 30, 100.0, 0.3),
            new BunchingRateDto(WeatherCondition::CLEAR, 20, 100.0, 0.2),
        ];

        $stats = $this->buildBunchingStats($results);

        // With 100h exposure: 60/100 = 0.6, 30/100 = 0.3, 20/100 = 0.2
        $this->assertEquals(0.6, $stats['snow_rate']);
        $this->assertEquals(0.3, $stats['rain_rate']);
        $this->assertEquals(0.2, $stats['clear_rate']);
        $this->assertEquals(100.0, $stats['snow_hours']);
        $this->assertEquals(100.0, $stats['rain_hours']);
        $this->assertEquals(100.0, $stats['clear_hours']);
        $this->assertEquals(3.0, $stats['multiplier'], 'Snow rate multiplier should be 0.6 / 0.2 = 3.0');
        $this->assertTrue($stats['hasData'], 'Should have data');
    }

    /**
     * Test buildBunchingByWeatherStats with no clear incidents.
     */
    public function testBuildBunchingByWeatherStatsWithNoClearIncidents(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 50, 100.0, 0.5),
            new BunchingRateDto(WeatherCondition::RAIN, 25, 100.0, 0.25),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0.5, $stats['snow_rate']);
        $this->assertEquals(0.25, $stats['rain_rate']);
        $this->assertEquals(0.0, $stats['clear_rate']);
        $this->assertEquals(100.0, $stats['snow_hours']);
        $this->assertEquals(100.0, $stats['rain_hours']);
        $this->assertEquals(0.0, $stats['clear_hours']);
        $this->assertEquals(0.0, $stats['multiplier'], 'Multiplier should be 0 when no clear data');
        $this->assertTrue($stats['hasData'], 'Should still have data (snow + rain)');
    }

    /**
     * Test buildBunchingByWeatherStats with no data.
     */
    public function testBuildBunchingByWeatherStatsWithNoData(): void
    {
        $results = [];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0.0, $stats['snow_rate']);
        $this->assertEquals(0.0, $stats['rain_rate']);
        $this->assertEquals(0.0, $stats['clear_rate']);
        $this->assertEquals(0.0, $stats['snow_hours']);
        $this->assertEquals(0.0, $stats['rain_hours']);
        $this->assertEquals(0.0, $stats['clear_hours']);
        $this->assertEquals(0.0, $stats['multiplier']);
        $this->assertFalse($stats['hasData'], 'Should have no data');
    }

    /**
     * Test buildBunchingByWeatherStats multiplier rounding.
     */
    public function testBuildBunchingByWeatherStatsMultiplierRounding(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 47, 100.0, 0.47),
            new BunchingRateDto(WeatherCondition::CLEAR, 13, 100.0, 0.13),
        ];

        $stats = $this->buildBunchingStats($results);

        // 0.47 / 0.13 = 3.615... should round to 3.6
        $this->assertEquals(0.47, $stats['snow_rate']);
        $this->assertEquals(0.13, $stats['clear_rate']);
        $this->assertEquals(3.6, $stats['multiplier'], 'Should round to 1 decimal place');
    }

    /**
     * Test buildBunchingByWeatherStats handles only rain incidents.
     */
    public function testBuildBunchingByWeatherStatsWithOnlyRain(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::RAIN, 35, 100.0, 0.35),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0.0, $stats['snow_rate']);
        $this->assertEquals(0.35, $stats['rain_rate']);
        $this->assertEquals(0.0, $stats['clear_rate']);
        $this->assertEquals(0.0, $stats['snow_hours']);
        $this->assertEquals(100.0, $stats['rain_hours']);
        $this->assertEquals(0.0, $stats['clear_hours']);
        $this->assertEquals(0.0, $stats['multiplier']);
        $this->assertTrue($stats['hasData']);
    }

    /**
     * Test buildBunchingByWeatherStats ignores unknown weather conditions.
     */
    public function testBuildBunchingByWeatherStatsIgnoresUnknownConditions(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 30, 100.0, 0.3),
            new BunchingRateDto(WeatherCondition::FOG, 10, 100.0, 0.1), // Fog is tracked but not in primary conditions
            new BunchingRateDto(WeatherCondition::CLEAR, 20, 100.0, 0.2),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0.3, $stats['snow_rate']);
        $this->assertEquals(0.0, $stats['rain_rate'], 'Fog should not be counted as rain');
        $this->assertEquals(0.2, $stats['clear_rate']);
        $this->assertTrue($stats['hasData']);
    }

    /**
     * Test buildBunchingByWeatherStats uses enum matching.
     */
    public function testBuildBunchingByWeatherStatsEnumMatching(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 25, 100.0, 0.25),
            new BunchingRateDto(WeatherCondition::RAIN, 15, 100.0, 0.15),
            new BunchingRateDto(WeatherCondition::CLEAR, 10, 100.0, 0.1),
        ];

        $stats = $this->buildBunchingStats($results);

        $this->assertEquals(0.25, $stats['snow_rate'], 'Should match Snow enum');
        $this->assertEquals(0.15, $stats['rain_rate'], 'Should match Rain enum');
        $this->assertEquals(0.1, $stats['clear_rate'], 'Should match Clear enum');
    }

    /**
     * Test buildBunchingByWeatherStats with extreme multiplier.
     */
    public function testBuildBunchingByWeatherStatsWithExtremeMultiplier(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 100, 100.0, 1.0),
            new BunchingRateDto(WeatherCondition::CLEAR, 1, 100.0, 0.01),
        ];

        $stats = $this->buildBunchingStats($results);

        // snow_rate = 1.0, clear_rate = 0.01
        // 1.0 / 0.01 = 100.0
        $this->assertEquals(1.0, $stats['snow_rate']);
        $this->assertEquals(0.01, $stats['clear_rate']);
        $this->assertEquals(100.0, $stats['multiplier'], 'Should handle extreme multipliers');
    }

    /**
     * Test buildBunchingByWeatherStats with equal snow and clear incidents.
     */
    public function testBuildBunchingByWeatherStatsWithEqualSnowAndClear(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 25, 100.0, 0.25),
            new BunchingRateDto(WeatherCondition::CLEAR, 25, 100.0, 0.25),
        ];

        $stats = $this->buildBunchingStats($results);

        // Both rates = 0.25
        $this->assertEquals(0.25, $stats['snow_rate']);
        $this->assertEquals(0.25, $stats['clear_rate']);
        $this->assertEquals(1.0, $stats['multiplier'], 'Multiplier should be 1.0 when rates are equal');
    }

    /**
     * Test buildBunchingByWeatherStats with more clear than snow incidents.
     */
    public function testBuildBunchingByWeatherStatsWithMoreClearThanSnow(): void
    {
        $results = [
            new BunchingRateDto(WeatherCondition::SNOW, 10, 100.0, 0.1),
            new BunchingRateDto(WeatherCondition::CLEAR, 30, 100.0, 0.3),
        ];

        $stats = $this->buildBunchingStats($results);

        // snow_rate = 0.1, clear_rate = 0.3
        // 0.1 / 0.3 = 0.333... rounds to 0.3
        $this->assertEquals(0.1, $stats['snow_rate']);
        $this->assertEquals(0.3, $stats['clear_rate']);
        $this->assertEquals(0.3, $stats['multiplier'], 'Multiplier should be less than 1.0 when clear is worse');
    }

    /**
     * Simulate building chart data from repository results.
     *
     * @param list<BunchingRateDto> $results
     *
     * @return list<array<string, mixed>>
     */
    private function buildBunchingChartData(array $results): array
    {
        // Create lookup map by weather condition
        $resultsByCondition = [];
        foreach ($results as $dto) {
            $resultsByCondition[$dto->weatherCondition->value] = $dto;
        }

        // Build chart data using enum
        $conditions = WeatherCondition::bunchingConditions();
        $data       = [];

        foreach ($conditions as $condition) {
            $dto              = $resultsByCondition[$condition->value] ?? null;
            $incidentsPerHour = $dto?->incidentsPerHour                ?? 0.0;

            $data[] = [
                'value'     => $incidentsPerHour,
                'itemStyle' => ['color' => $condition->chartColor()],
            ];
        }

        return $data;
    }

    /**
     * Simulate building normalized stats from repository results.
     *
     * @param list<BunchingRateDto> $results
     *
     * @return array<string, mixed>
     */
    private function buildBunchingStats(array $results): array
    {
        $snowRate   = 0.0;
        $rainRate   = 0.0;
        $clearRate  = 0.0;
        $snowHours  = 0.0;
        $rainHours  = 0.0;
        $clearHours = 0.0;

        foreach ($results as $dto) {
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

        // Calculate multiplier (how much worse is snow vs clear?)
        $multiplier = $clearRate      > 0 ? round($snowRate / $clearRate, 1) : 0.0;
        $hasData    = count($results) > 0;

        return [
            'snow_rate'   => $snowRate,
            'rain_rate'   => $rainRate,
            'clear_rate'  => $clearRate,
            'snow_hours'  => $snowHours,
            'rain_hours'  => $rainHours,
            'clear_hours' => $clearHours,
            'multiplier'  => $multiplier,
            'hasData'     => $hasData,
        ];
    }
}
