<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\WarmInsightCacheCommand;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WarmInsightCacheCommand.
 *
 * Note: Integration tests verify the full command execution.
 * Unit tests verify command metadata.
 */
final class WarmInsightCacheCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $this->assertSame('app:warm-insight-cache', WarmInsightCacheCommand::getDefaultName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $this->assertStringContainsString(
            'Pre-warm AI-generated insight cache',
            WarmInsightCacheCommand::getDefaultDescription() ?? ''
        );
    }
}
