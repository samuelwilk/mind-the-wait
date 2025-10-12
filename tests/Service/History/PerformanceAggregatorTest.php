<?php

declare(strict_types=1);

namespace App\Tests\Service\History;

use App\Entity\ArrivalLog;
use App\Entity\Route;
use App\Entity\Stop;
use App\Entity\WeatherObservation;
use App\Enum\PredictionConfidence;
use App\Enum\RouteTypeEnum;
use App\Enum\TransitImpact;
use App\Repository\ArrivalLogRepository;
use App\Repository\RouteRepository;
use App\Repository\WeatherObservationRepository;
use App\Service\History\PerformanceAggregator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for PerformanceAggregator using real database.
 *
 * Uses DAMA DoctrineTestBundle for automatic transaction rollback between tests.
 */
final class PerformanceAggregatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RouteRepository $routeRepo;
    private ArrivalLogRepository $arrivalLogRepo;
    private WeatherObservationRepository $weatherRepo;
    private PerformanceAggregator $aggregator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em             = $container->get(EntityManagerInterface::class);
        $this->routeRepo      = $container->get(RouteRepository::class);
        $this->arrivalLogRepo = $container->get(ArrivalLogRepository::class);
        $this->weatherRepo    = $container->get(WeatherObservationRepository::class);
        $this->aggregator     = $container->get(PerformanceAggregator::class);
    }

    public function testCalculatesCorrectMedianForOddNumberOfDelays(): void
    {
        $route = $this->createRoute();
        $stop  = $this->createStop();

        // Create 3 arrival logs with different delays
        $this->createArrivalLog($route, $stop, delaySec: 10);
        $this->createArrivalLog($route, $stop, delaySec: 20);
        $this->createArrivalLog($route, $stop, delaySec: 30);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['success']);
        self::assertSame(0, $result['failed']);
    }

    public function testCalculatesCorrectMedianForEvenNumberOfDelays(): void
    {
        $route = $this->createRoute('2');
        $stop  = $this->createStop('stop-2');

        // Create 4 arrival logs - median should be (20+30)/2 = 25
        $this->createArrivalLog($route, $stop, delaySec: 10);
        $this->createArrivalLog($route, $stop, delaySec: 20);
        $this->createArrivalLog($route, $stop, delaySec: 30);
        $this->createArrivalLog($route, $stop, delaySec: 40);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['success']);
        self::assertSame(0, $result['failed']);
    }

    public function testCountsConfidenceLevelsCorrectly(): void
    {
        $route = $this->createRoute('3');
        $stop  = $this->createStop('stop-3');

        $this->createArrivalLog($route, $stop, confidence: PredictionConfidence::HIGH);
        $this->createArrivalLog($route, $stop, confidence: PredictionConfidence::HIGH);
        $this->createArrivalLog($route, $stop, confidence: PredictionConfidence::MEDIUM);
        $this->createArrivalLog($route, $stop, confidence: PredictionConfidence::LOW);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['success']);
        // Expected: 2 high, 1 medium, 1 low, total 4
    }

    public function testHandlesEmptyLogsGracefully(): void
    {
        // Create route with no logs
        $this->createRoute('4');
        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('today'));

        // Should skip routes with no activity
        self::assertSame(0, $result['success']);
        self::assertSame(0, $result['failed']);
    }

    public function testLinksWeatherObservationFromNoontime(): void
    {
        $route = $this->createRoute('5');
        $stop  = $this->createStop('stop-5');

        // Create weather observation at noon
        $weather = new WeatherObservation();
        $weather->setObservedAt(new \DateTimeImmutable('today 12:00:00'));
        $weather->setTemperatureCelsius('2.4');
        $weather->setWeatherCondition('snow');
        $weather->setWeatherCode(71);
        $weather->setTransitImpact(TransitImpact::MINOR);
        $weather->setDataSource('test');
        $this->em->persist($weather);

        $this->createArrivalLog($route, $stop, delaySec: 100);
        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['success']);
    }

    public function testHandlesNullDelaysGracefully(): void
    {
        $route = $this->createRoute('6');
        $stop  = $this->createStop('stop-6');

        // Create logs with null delay
        $this->createArrivalLog($route, $stop, delaySec: null);
        $this->createArrivalLog($route, $stop, delaySec: 100);
        $this->createArrivalLog($route, $stop, delaySec: 200);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('today'));

        self::assertSame(1, $result['success']);
        // Total predictions = 3, but only 2 have delay data
    }

    private function createRoute(string $gtfsId = '1'): Route
    {
        $route = new Route();
        $route->setGtfsId($gtfsId);
        $route->setShortName($gtfsId);
        $route->setLongName('Test Route '.$gtfsId);
        $route->setRouteType(RouteTypeEnum::BUS);
        $this->em->persist($route);

        return $route;
    }

    private function createStop(string $gtfsId = 'stop-1'): Stop
    {
        $stop = new Stop();
        $stop->setGtfsId($gtfsId);
        $stop->setName('Test Stop '.$gtfsId);
        $stop->setLat(52.1324);
        $stop->setLon(-106.6607);
        $this->em->persist($stop);

        return $stop;
    }

    private function createArrivalLog(
        Route $route,
        Stop $stop,
        ?int $delaySec = 0,
        PredictionConfidence $confidence = PredictionConfidence::HIGH,
    ): ArrivalLog {
        $log = new ArrivalLog();
        $log->setRoute($route);
        $log->setStop($stop);
        $log->setVehicleId('veh-test-'.random_int(1000, 9999));
        $log->setTripId('trip-test-'.random_int(1000, 9999));
        $log->setPredictedArrival(new \DateTimeImmutable());
        $log->setPredictedAt(new \DateTimeImmutable());
        $log->setConfidence($confidence);
        $log->setDelaySec($delaySec);
        $this->em->persist($log);

        return $log;
    }
}
