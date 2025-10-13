<?php

declare(strict_types=1);

namespace App\Tests\Service\Dashboard;

use App\Service\Dashboard\InsightGeneratorService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for InsightGeneratorService.
 *
 * Note: These tests mock the OpenAI API to avoid external dependencies.
 */
final class InsightGeneratorServiceTest extends TestCase
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private string $apiKey;

    protected function setUp(): void
    {
        $this->cache  = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->apiKey = 'test-api-key';
    }

    public function testGenerateWinterOperationsInsightReturnsCachedContent(): void
    {
        $stats = [
            'worstRoute'      => 'Route 27',
            'clearPerf'       => 78.0,
            'snowPerf'        => 45.0,
            'performanceDrop' => 33.0,
        ];

        $cachedContent = '<p>Test cached content</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('insight_winter_ops_'))
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateWinterOperationsInsight($stats);

        $this->assertSame($cachedContent, $result);
    }

    public function testGenerateTemperatureThresholdInsightReturnsCachedContent(): void
    {
        $stats = [
            'aboveThreshold'  => 79.2,
            'belowThreshold'  => 58.5,
            'performanceDrop' => 20.7,
            'daysAbove'       => 150,
            'daysBelow'       => 30,
        ];

        $cachedContent = '<p>Temperature insight</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('insight_temp_threshold_'))
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateTemperatureThresholdInsight($stats);

        $this->assertSame($cachedContent, $result);
    }

    public function testGenerateWeatherImpactMatrixInsightReturnsCachedContent(): void
    {
        $stats = [
            'worstCondition' => 'Snow',
            'avgPerformance' => 55.5,
            'dayCount'       => 45,
        ];

        $cachedContent = '<p>Matrix insight</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('insight_impact_matrix_'))
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateWeatherImpactMatrixInsight($stats);

        $this->assertSame($cachedContent, $result);
    }

    public function testGenerateBunchingByWeatherInsightReturnsCachedContent(): void
    {
        $stats = [
            'snowIncidents'  => 87,
            'rainIncidents'  => 32,
            'clearIncidents' => 24,
            'multiplier'     => 3.6,
        ];

        $cachedContent = '<p>Bunching insight</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('insight_bunching_'))
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateBunchingByWeatherInsight($stats);

        $this->assertSame($cachedContent, $result);
    }

    public function testGenerateWeatherImpactKeyTakeawayReturnsCachedContent(): void
    {
        $cachedContent = '<p>Key takeaway text</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('insight_key_takeaway')
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateWeatherImpactKeyTakeaway();

        $this->assertSame($cachedContent, $result);
    }

    public function testGenerateDashboardWinterImpactCardReturnsCachedContent(): void
    {
        $stats         = ['avgDrop' => 33.0];
        $cachedContent = '<p>Dashboard winter card</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('insight_dashboard_winter_'))
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateDashboardWinterImpactCard($stats);

        $this->assertSame($cachedContent, $result);
    }

    public function testGenerateDashboardTemperatureCardReturnsCachedContent(): void
    {
        $stats = [
            'threshold'     => '-20',
            'delayIncrease' => '5-7',
        ];
        $cachedContent = '<p>Dashboard temperature card</p>';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedContent);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('insight_dashboard_temp_'))
            ->willReturn($cacheItem);

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $result  = $service->generateDashboardTemperatureCard($stats);

        $this->assertSame($cachedContent, $result);
    }

    public function testCacheKeysAreDifferentForDifferentStats(): void
    {
        $stats1 = ['worstRoute' => 'Route 27', 'clearPerf' => 78.0, 'snowPerf' => 45.0, 'performanceDrop' => 33.0];
        $stats2 = ['worstRoute' => 'Route 14', 'clearPerf' => 92.0, 'snowPerf' => 84.0, 'performanceDrop' => 8.0];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn('<p>Test</p>');

        $calledKeys = [];
        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturnCallback(function ($key) use ($cacheItem, &$calledKeys) {
                $calledKeys[] = $key;

                return $cacheItem;
            });

        $service = new InsightGeneratorService($this->cache, $this->logger, $this->apiKey);
        $service->generateWinterOperationsInsight($stats1);
        $service->generateWinterOperationsInsight($stats2);

        $this->assertCount(2, $calledKeys);
        $this->assertNotEquals($calledKeys[0], $calledKeys[1], 'Cache keys should be different for different stats');
    }
}
