<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\DetectBunchingCommand;
use App\Factory\ArrivalLogFactory;
use App\Factory\RouteFactory;
use App\Factory\StopFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

/**
 * Integration tests for DetectBunchingCommand using real database.
 */
#[CoversClass(DetectBunchingCommand::class)]
final class DetectBunchingCommandTest extends KernelTestCase
{
    use Factories;
    // Note: Using DAMA\DoctrineTestBundle for transaction isolation (configured in phpunit.dist.xml)
    // Do NOT use ResetDatabase trait when using DAMA

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test', 'debug' => true]);

        $application         = new Application(self::$kernel);
        $command             = $application->find('app:detect:bunching');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithDetectedIncidents(): void
    {
        $route = RouteFactory::createOne(['gtfsId' => 'route-cmd-1']);
        $stop  = StopFactory::createOne(['gtfsId' => 'stop-cmd-1']);
        $date  = new \DateTimeImmutable('2025-02-20');

        // Create bunching incident (2 vehicles arriving within 1 minute)
        ArrivalLogFactory::createOne([
            'route'              => $route,
            'stop'               => $stop,
            'vehicleId'          => 'veh-1',
            'tripId'             => 'trip-1',
            'predictedArrivalAt' => $date->setTime(10, 0, 0),
            'predictedAt'        => $date->setTime(9, 55, 0),
        ]);
        ArrivalLogFactory::createOne([
            'route'              => $route,
            'stop'               => $stop,
            'vehicleId'          => 'veh-2',
            'tripId'             => 'trip-2',
            'predictedArrivalAt' => $date->setTime(10, 1, 0),
            'predictedAt'        => $date->setTime(9, 55, 0),
        ]);

        $exitCode = $this->commandTester->execute([
            '--date' => '2025-02-20',
        ]);

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
        $route = RouteFactory::createOne(['gtfsId' => 'route-cmd-2']);
        $stop  = StopFactory::createOne(['gtfsId' => 'stop-cmd-2']);
        $date  = new \DateTimeImmutable('2025-02-15');

        // Create bunching for specific date
        ArrivalLogFactory::createMany(2, [
            'route' => $route,
            'stop'  => $stop,
        ], function (int $i) use ($date) {
            return [
                'predictedArrivalAt' => $date->setTime(10, $i, 0),
                'predictedAt'        => $date->setTime(9, 55, 0),
            ];
        });

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
        $route = RouteFactory::createOne(['gtfsId' => 'route-cmd-3']);
        $stop  = StopFactory::createOne(['gtfsId' => 'stop-cmd-3']);
        $date  = new \DateTimeImmutable('2025-02-16');

        // Create vehicles 90 seconds apart
        ArrivalLogFactory::createOne([
            'route'              => $route,
            'stop'               => $stop,
            'predictedArrivalAt' => $date->setTime(10, 0, 0),
            'predictedAt'        => $date->setTime(9, 55, 0),
        ]);
        ArrivalLogFactory::createOne([
            'route'              => $route,
            'stop'               => $stop,
            'predictedArrivalAt' => $date->setTime(10, 1, 30),
            'predictedAt'        => $date->setTime(9, 55, 0),
        ]);

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
        $route = RouteFactory::createOne(['gtfsId' => 'route-cmd-4']);
        $stop  = StopFactory::createOne(['gtfsId' => 'stop-cmd-4']);
        $date  = new \DateTimeImmutable('2025-02-17');

        ArrivalLogFactory::createMany(2, [
            'route' => $route,
            'stop'  => $stop,
        ], function (int $i) use ($date) {
            return ['predictedArrivalAt' => $date->setTime(10, $i, 0)];
        });

        $exitCode = $this->commandTester->execute([
            '-d' => '2025-02-17',
            '-t' => '120',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('2025-02-17', $this->commandTester->getDisplay());
    }
}
