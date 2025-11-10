<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CollectArrivalLogsCommand;
use App\Entity\City;
use App\Entity\Route;
use App\Entity\Stop;
use App\Entity\StopTime;
use App\Entity\Trip;
use App\Enum\DirectionEnum;
use App\Enum\RouteTypeEnum;
use App\Tests\InjectableHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Predis\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Integration tests for CollectArrivalLogsCommand.
 *
 * Note: These tests verify the command runs successfully with different Redis states.
 * The actual prediction logging behavior is tested in ArrivalPredictorTest and ArrivalLoggerTest.
 */
#[CoversClass(CollectArrivalLogsCommand::class)]
final class CollectArrivalLogsCommandTest extends KernelTestCase
{
    use InjectableHelperTrait;

    private EntityManagerInterface $em;
    private ClientInterface $redis;
    private CacheInterface $cache;
    private CommandTester $commandTester;
    private City $testCity;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        $this->em    = $this->getInjectable(EntityManagerInterface::class);
        $this->redis = $this->getInjectable(ClientInterface::class);
        $this->cache = $this->getInjectable(CacheInterface::class);

        // Create test city for multi-city support
        $this->testCity = $this->createTestCity();

        $application         = new Application(self::$kernel);
        $command             = $application->find('app:collect:arrival-logs');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        // Clean up Redis keys after each test (use saskatoon cityslug for compatibility)
        $this->redis->del('mtw:saskatoon:vehicles');
        $this->redis->del('mtw:saskatoon:trips');

        // Clear cache to prevent stale data between tests
        $this->cache->clear();

        parent::tearDown();
    }

    public function testExecuteWithNoVehicles(): void
    {
        // Arrange: Empty Redis (no vehicles) - use saskatoon city key
        $this->redis->hset('mtw:saskatoon:vehicles', 'ts', time());
        $this->redis->hset('mtw:saskatoon:vehicles', 'json', json_encode([]));

        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No active vehicles found', $this->commandTester->getDisplay());
    }

    public function testExecuteWithVehiclesMissingRequiredData(): void
    {
        // Arrange: Vehicles with missing required fields - use saskatoon city key
        $vehicles = [
            ['id' => 'veh-1'], // Missing trip and route
            ['trip'  => 'trip-1'], // Missing id and route
            ['route' => 'route-1'], // Missing id and trip
        ];
        $this->redis->hset('mtw:saskatoon:vehicles', 'ts', time());
        $this->redis->hset('mtw:saskatoon:vehicles', 'json', json_encode($vehicles));

        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 3 active vehicles', $display);
        $this->assertStringContainsString('No arrival predictions were generated', $display);
    }

    public function testExecuteWithValidVehicleButNoTripData(): void
    {
        // Arrange: Valid vehicle but no trip/stop data in database - use saskatoon city key
        $vehicles = [
            [
                'id'    => 'veh-test-1',
                'trip'  => 'trip-nonexistent',
                'route' => 'route-test-1',
                'lat'   => 52.1332,
                'lon'   => -106.6700,
                'ts'    => time(),
            ],
        ];
        $this->redis->hset('mtw:saskatoon:vehicles', 'ts', time());
        $this->redis->hset('mtw:saskatoon:vehicles', 'json', json_encode($vehicles));

        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 active vehicles', $display);
        // Should show 0 predictions since trip doesn't exist in database
        $this->assertStringContainsString('Total Predictions   0', $display);
    }

    public function testExecuteWithValidSetup(): void
    {
        // Arrange: Create route, trip, stops in database
        $route = $this->createRoute('route-test-valid');
        $trip  = $this->createTrip($route, 'trip-test-valid');
        $stop1 = $this->createStop('stop-test-1');
        $stop2 = $this->createStop('stop-test-2');
        $stop3 = $this->createStop('stop-test-3');

        $this->createStopTime($trip, $stop1, 1, 28800); // 8:00 AM
        $this->createStopTime($trip, $stop2, 2, 29100); // 8:05 AM
        $this->createStopTime($trip, $stop3, 3, 29400); // 8:10 AM

        $this->em->flush();

        // Add vehicle to Redis - use saskatoon city key
        $vehicles = [
            [
                'id'    => 'veh-test-valid',
                'trip'  => 'trip-test-valid',
                'route' => 'route-test-valid',
                'lat'   => 52.1332,
                'lon'   => -106.6700,
                'ts'    => time(),
            ],
        ];
        $this->redis->hset('mtw:saskatoon:vehicles', 'ts', time());
        $this->redis->hset('mtw:saskatoon:vehicles', 'json', json_encode($vehicles));

        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 1 active vehicles', $display);
        // Should attempt to create predictions (may fail gracefully if other services not available)
        $this->assertStringContainsString('Total Predictions', $display);
    }

    public function testCommandIsProperlyRegistered(): void
    {
        // Arrange
        $application = new Application(self::$kernel);

        // Act
        $command = $application->find('app:collect:arrival-logs');

        // Assert: Verify command is registered with correct name and description
        $this->assertEquals('app:collect:arrival-logs', $command->getName());
        $this->assertEquals(
            'Generate arrival predictions for active vehicles to populate arrival logs',
            $command->getDescription()
        );
    }

    private function createTestCity(): City
    {
        // Check if test city already exists
        $city = $this->em->getRepository(City::class)->findOneBy(['slug' => 'test-city']);

        if ($city === null) {
            $city = new City();
            $city->setName('Test City');
            $city->setSlug('test-city');
            $city->setCountry('CA');
            $city->setCenterLat('52.1324');
            $city->setCenterLon('-106.6689');
            $city->setActive(true);
            $this->em->persist($city);
            $this->em->flush();
        }

        return $city;
    }

    private function createRoute(string $gtfsId): Route
    {
        $route = new Route();
        $route->setGtfsId($gtfsId);
        $route->setShortName(substr($gtfsId, -1));
        $route->setLongName('Test Route '.$gtfsId);
        $route->setRouteType(RouteTypeEnum::Bus);
        $route->setColour('FF0000');
        $route->setCity($this->testCity);
        $this->em->persist($route);

        return $route;
    }

    private function createTrip(Route $route, string $gtfsId): Trip
    {
        $trip = new Trip();
        $trip->setGtfsId($gtfsId);
        $trip->setRoute($route);
        $trip->setHeadsign('Test Headsign');
        $trip->setDirection(DirectionEnum::Zero);
        $trip->setCity($this->testCity);
        $this->em->persist($trip);

        return $trip;
    }

    private function createStop(string $gtfsId): Stop
    {
        $stop = new Stop();
        $stop->setGtfsId($gtfsId);
        $stop->setName('Test Stop '.$gtfsId);
        $stop->setLat(52.1332 + random_int(-100, 100) / 10000);
        $stop->setLong(-106.6700 + random_int(-100, 100) / 10000);
        $stop->setCity($this->testCity);
        $this->em->persist($stop);

        return $stop;
    }

    private function createStopTime(Trip $trip, Stop $stop, int $sequence, int $arrivalSec): StopTime
    {
        $stopTime = new StopTime();
        $stopTime->setTrip($trip);
        $stopTime->setStop($stop);
        $stopTime->setStopSequence($sequence);
        $stopTime->setArrivalTime($arrivalSec);
        $stopTime->setDepartureTime($arrivalSec + 30); // 30 sec dwell time
        $this->em->persist($stopTime);

        return $stopTime;
    }
}
