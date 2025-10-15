<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\DetectBunchingCommand;
use App\Entity\ArrivalLog;
use App\Entity\Route;
use App\Entity\Stop;
use App\Enum\PredictionConfidence;
use App\Enum\RouteTypeEnum;
use App\Tests\InjectableHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for DetectBunchingCommand using real database.
 */
#[CoversClass(DetectBunchingCommand::class)]
final class DetectBunchingCommandTest extends KernelTestCase
{
    use InjectableHelperTrait;

    private EntityManagerInterface $em;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        $this->em = $this->getInjectable(EntityManagerInterface::class);

        $application         = new Application(self::$kernel);
        $command             = $application->find('app:detect:bunching');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithDetectedIncidents(): void
    {
        $route = $this->createRoute('route-cmd-1');
        $stop  = $this->createStop('stop-cmd-1');
        $date  = new \DateTimeImmutable('yesterday');

        // Create bunching incident
        $this->createArrivalLog($route, $stop, $date->setTime(10, 0, 0), 'veh-1');
        $this->createArrivalLog($route, $stop, $date->setTime(10, 1, 0), 'veh-2');
        $this->em->flush();

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Successfully detected', $this->commandTester->getDisplay());
    }

    public function testExecuteWithNoData(): void
    {
        // No arrival logs, should report no incidents
        $exitCode = $this->commandTester->execute([
            '--date' => '2025-12-31', // Future date with no data
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No bunching incidents detected', $this->commandTester->getDisplay());
    }

    public function testExecuteWithCustomDate(): void
    {
        $route = $this->createRoute('route-cmd-2');
        $stop  = $this->createStop('stop-cmd-2');
        $date  = new \DateTimeImmutable('2025-02-15');

        // Create bunching for specific date
        $this->createArrivalLog($route, $stop, $date->setTime(10, 0, 0), 'veh-1');
        $this->createArrivalLog($route, $stop, $date->setTime(10, 1, 0), 'veh-2');
        $this->em->flush();

        $exitCode = $this->commandTester->execute([
            '--date' => '2025-02-15',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('2025-02-15', $this->commandTester->getDisplay());
    }

    public function testExecuteWithInvalidDateFormat(): void
    {
        $exitCode = $this->commandTester->execute([
            '--date' => 'invalid-date',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid date format', $this->commandTester->getDisplay());
    }

    public function testExecuteWithCustomTimeWindow(): void
    {
        $route = $this->createRoute('route-cmd-3');
        $stop  = $this->createStop('stop-cmd-3');
        $date  = new \DateTimeImmutable('2025-02-16');

        // Create vehicles 90 seconds apart
        $this->createArrivalLog($route, $stop, $date->setTime(10, 0, 0), 'veh-1');
        $this->createArrivalLog($route, $stop, $date->setTime(10, 1, 30), 'veh-2');
        $this->em->flush();

        // With 60s window, should not detect
        $exitCode = $this->commandTester->execute([
            '--date'        => '2025-02-16',
            '--time-window' => '60',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('60 seconds', $this->commandTester->getDisplay());
    }

    public function testExecuteWithTimeWindowTooSmall(): void
    {
        $exitCode = $this->commandTester->execute([
            '--time-window' => '0',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Time window must be between 1 and 600 seconds', $this->commandTester->getDisplay());
    }

    public function testExecuteWithTimeWindowTooLarge(): void
    {
        $exitCode = $this->commandTester->execute([
            '--time-window' => '601',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Time window must be between 1 and 600 seconds', $this->commandTester->getDisplay());
    }

    public function testExecuteWithShortOptions(): void
    {
        $route = $this->createRoute('route-cmd-4');
        $stop  = $this->createStop('stop-cmd-4');
        $date  = new \DateTimeImmutable('2025-02-17');

        $this->createArrivalLog($route, $stop, $date->setTime(10, 0, 0), 'veh-1');
        $this->createArrivalLog($route, $stop, $date->setTime(10, 1, 0), 'veh-2');
        $this->em->flush();

        $exitCode = $this->commandTester->execute([
            '-d' => '2025-02-17',
            '-t' => '120',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('2025-02-17', $this->commandTester->getDisplay());
    }

    private function createRoute(string $gtfsId): Route
    {
        $route = new Route();
        $route->setGtfsId($gtfsId);
        $route->setShortName($gtfsId);
        $route->setLongName('Test Route '.$gtfsId);
        $route->setColour('FF0000');
        $route->setRouteType(RouteTypeEnum::Bus);
        $this->em->persist($route);

        return $route;
    }

    private function createStop(string $gtfsId): Stop
    {
        $stop = new Stop();
        $stop->setGtfsId($gtfsId);
        $stop->setName('Test Stop '.$gtfsId);
        $stop->setLat(52.1332);
        $stop->setLong(-106.6700);
        $this->em->persist($stop);

        return $stop;
    }

    private function createArrivalLog(
        Route $route,
        Stop $stop,
        \DateTimeImmutable $arrivalTime,
        string $vehicleId,
    ): ArrivalLog {
        $log = new ArrivalLog();
        $log->setRoute($route);
        $log->setStop($stop);
        $log->setVehicleId($vehicleId);
        $log->setTripId('trip-'.random_int(1000, 9999));
        $log->setPredictedArrivalAt($arrivalTime);
        $log->setPredictedAt($arrivalTime->modify('-5 minutes'));
        $log->setConfidence(PredictionConfidence::HIGH);
        $log->setDelaySec(0);
        $this->em->persist($log);

        return $log;
    }
}
