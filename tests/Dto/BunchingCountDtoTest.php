<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\BunchingCountDto;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Dto\BunchingCountDto
 */
final class BunchingCountDtoTest extends TestCase
{
    public function testConstructorAndProperties(): void
    {
        $dto = new BunchingCountDto(
            weatherCondition: 'Snow',
            incidentCount: 42,
        );

        $this->assertSame('Snow', $dto->weatherCondition);
        $this->assertSame(42, $dto->incidentCount);
    }

    public function testConstructorWithDifferentWeatherConditions(): void
    {
        $conditions = [
            ['Clear', 10],
            ['Rain', 25],
            ['Snow', 50],
            ['Fog', 5],
            ['Overcast', 15],
        ];

        foreach ($conditions as [$condition, $count]) {
            $dto = new BunchingCountDto(
                weatherCondition: $condition,
                incidentCount: $count,
            );

            $this->assertSame($condition, $dto->weatherCondition);
            $this->assertSame($count, $dto->incidentCount);
        }
    }

    public function testConstructorWithZeroIncidents(): void
    {
        $dto = new BunchingCountDto(
            weatherCondition: 'Clear',
            incidentCount: 0,
        );

        $this->assertSame('Clear', $dto->weatherCondition);
        $this->assertSame(0, $dto->incidentCount);
    }

    public function testImmutability(): void
    {
        $dto = new BunchingCountDto(
            weatherCondition: 'Snow',
            incidentCount: 10,
        );

        // Verify properties are readonly (this test documents the behavior)
        $this->assertTrue((new \ReflectionClass($dto))->isReadOnly());
    }
}
