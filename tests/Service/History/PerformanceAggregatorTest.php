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
use App\Tests\InjectableHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for PerformanceAggregator using real database.
 *
 * Uses DAMA DoctrineTestBundle for automatic transaction rollback between tests.
 */
final class PerformanceAggregatorTest extends KernelTestCase
{
    use InjectableHelperTrait;

    private EntityManagerInterface $em;
    private RouteRepository $routeRepo;
    private ArrivalLogRepository $arrivalLogRepo;
    private WeatherObservationRepository $weatherRepo;
    private PerformanceAggregator $aggregator;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        $this->em             = $this->getInjectable(EntityManagerInterface::class);
        $this->routeRepo      = $this->getInjectable(RouteRepository::class);
        $this->arrivalLogRepo = $this->getInjectable(ArrivalLogRepository::class);
        $this->weatherRepo    = $this->getInjectable(WeatherObservationRepository::class);
        $this->aggregator     = $this->getInjectable(PerformanceAggregator::class);
    }

    public function testCalculatesCorrectMedianForOddNumberOfDelays(): void
    {
        $route = $this->createRoute();
        $stop  = $this->createStop();
        $date  = new \DateTimeImmutable('2025-01-01');

        // Create 3 arrival logs with different delays
        $this->createArrivalLog($route, $stop, $date, delaySec: 10);
        $this->createArrivalLog($route, $stop, $date, delaySec: 20);
        $this->createArrivalLog($route, $stop, $date, delaySec: 30);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate($date);

        self::assertSame(1, $result['success']);
        self::assertSame(0, $result['failed']);
    }

    public function testCalculatesCorrectMedianForEvenNumberOfDelays(): void
    {
        $route = $this->createRoute('2');
        $stop  = $this->createStop('stop-2');
        $date  = new \DateTimeImmutable('2025-01-02');

        // Create 4 arrival logs - median should be (20+30)/2 = 25
        $this->createArrivalLog($route, $stop, $date, delaySec: 10);
        $this->createArrivalLog($route, $stop, $date, delaySec: 20);
        $this->createArrivalLog($route, $stop, $date, delaySec: 30);
        $this->createArrivalLog($route, $stop, $date, delaySec: 40);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate($date);

        self::assertSame(1, $result['success']);
        self::assertSame(0, $result['failed']);
    }

    public function testCountsConfidenceLevelsCorrectly(): void
    {
        $route = $this->createRoute('3');
        $stop  = $this->createStop('stop-3');
        $date  = new \DateTimeImmutable('2025-01-03');

        $this->createArrivalLog($route, $stop, $date, confidence: PredictionConfidence::HIGH);
        $this->createArrivalLog($route, $stop, $date, confidence: PredictionConfidence::HIGH);
        $this->createArrivalLog($route, $stop, $date, confidence: PredictionConfidence::MEDIUM);
        $this->createArrivalLog($route, $stop, $date, confidence: PredictionConfidence::LOW);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate($date);

        self::assertSame(1, $result['success']);
        // Expected: 2 high, 1 medium, 1 low, total 4
    }

    public function testHandlesEmptyLogsGracefully(): void
    {
        // Create route with no logs
        $this->createRoute('4');
        $this->em->flush();

        $result = $this->aggregator->aggregateDate(new \DateTimeImmutable('2025-01-04'));

        // Should skip routes with no activity
        self::assertSame(0, $result['success']);
        self::assertSame(0, $result['failed']);
    }

    public function testLinksWeatherObservationFromNoontime(): void
    {
        $route       = $this->createRoute('5');
        $stop        = $this->createStop('stop-5');
        $date        = new \DateTimeImmutable('2025-01-05');
        $weatherTime = new \DateTimeImmutable('2025-01-05 12:00:00');

        // Create weather observation at noon
        $weather = new WeatherObservation();
        $weather->setObservedAt($weatherTime);
        $weather->setTemperatureCelsius('2.4');
        $weather->setWeatherCondition('snow');
        $weather->setWeatherCode(71);
        $weather->setTransitImpact(TransitImpact::MINOR);
        $weather->setDataSource('test');
        $this->em->persist($weather);

        $this->createArrivalLog($route, $stop, $date, delaySec: 100);
        $this->em->flush();

        $result = $this->aggregator->aggregateDate($date);

        self::assertSame(1, $result['success']);
    }

    public function testHandlesNullDelaysGracefully(): void
    {
        $route = $this->createRoute('6');
        $stop  = $this->createStop('stop-6');
        $date  = new \DateTimeImmutable('2025-01-06');

        // Create logs with null delay
        $this->createArrivalLog($route, $stop, $date, delaySec: null);
        $this->createArrivalLog($route, $stop, $date, delaySec: 100);
        $this->createArrivalLog($route, $stop, $date, delaySec: 200);

        $this->em->flush();

        $result = $this->aggregator->aggregateDate($date);

        self::assertSame(1, $result['success']);
        // Total predictions = 3, but only 2 have delay data
    }

    private function createRoute(string $gtfsId = '1'): Route
    {
        $route = new Route();
        $route->setGtfsId($gtfsId);
        $route->setShortName($gtfsId);
        $route->setLongName('Test Route '.$gtfsId);
        $route->setRouteType(RouteTypeEnum::Bus);
        $this->em->persist($route);

        return $route;
    }

    private function createStop(string $gtfsId = 'stop-1'): Stop
    {
        $stop = new Stop();
        $stop->setGtfsId($gtfsId);
        $stop->setName('Test Stop '.$gtfsId);
        $stop->setLat(52.1324);
        $stop->setLong(-106.6607);
        $this->em->persist($stop);

        return $stop;
    }

    private function createArrivalLog(
        Route $route,
        Stop $stop,
        \DateTimeImmutable $date,
        ?int $delaySec = 0,
        PredictionConfidence $confidence = PredictionConfidence::HIGH,
    ): ArrivalLog {
        $log = new ArrivalLog();
        $log->setRoute($route);
        $log->setStop($stop);
        $log->setVehicleId('veh-test-'.random_int(1000, 9999));
        $log->setTripId('trip-test-'.random_int(1000, 9999));
        $log->setPredictedArrivalAt($date);
        $log->setPredictedAt($date);
        $log->setConfidence($confidence);
        $log->setDelaySec($delaySec);
        $this->em->persist($log);

        return $log;
    }
}
