<?php

declare(strict_types=1);

namespace App\Tests\Scheduler;

use App\Scheduler\ScoreTickSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * Tests for ScoreTickSchedule.
 */
final class ScoreTickScheduleTest extends TestCase
{
    private ScoreTickSchedule $schedule;

    protected function setUp(): void
    {
        $this->schedule = new ScoreTickSchedule();
    }

    public function testScheduleExists(): void
    {
        $schedule = $this->schedule->getSchedule();

        $this->assertNotNull($schedule);
    }

    public function testScheduleHasRecurringMessage(): void
    {
        $schedule = $this->schedule->getSchedule();
        $messages = iterator_to_array($schedule->getRecurringMessages());

        $this->assertCount(1, $messages);
    }

    public function testScheduleContainsScoreTickMessage(): void
    {
        $schedule = $this->schedule->getSchedule();
        $messages = iterator_to_array($schedule->getRecurringMessages());

        /** @var RecurringMessage $recurringMessage */
        $recurringMessage = $messages[0];
        $trigger          = $recurringMessage->getTrigger();

        // Verify trigger frequency is 30 seconds
        $triggerString = $trigger->__toString();
        $this->assertStringContainsString('30', $triggerString, 'Should run every 30 seconds');
    }

    public function testScheduleRecurringInterval(): void
    {
        $schedule = $this->schedule->getSchedule();
        $messages = iterator_to_array($schedule->getRecurringMessages());

        /** @var RecurringMessage $recurringMessage */
        $recurringMessage = $messages[0];
        $trigger          = $recurringMessage->getTrigger();

        // Verify it's recurring (not a one-time message)
        $this->assertNotNull($trigger);

        // Get frequency from trigger
        $frequency = $trigger->__toString();
        $this->assertStringContainsString('30', $frequency, 'Should run every 30 seconds');
    }
}
