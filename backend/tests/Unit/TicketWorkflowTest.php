<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Unit;

use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Tickets\StaffAction;
use BeryCode\Support\Tickets\TicketStatus;
use BeryCode\Support\Tickets\TicketWorkflow;
use BeryCode\Support\Tickets\TransitionResult;

final class TicketWorkflowTest extends TestCase
{
    private const ME = 'U0ME000001';
    private const OTHER = 'U0OTHER001';

    public function testAssignToMe(): void
    {
        $result = TicketWorkflow::apply(TicketStatus::NEW, null, StaffAction::ASSIGN_TO_ME, self::ME);
        $this->assertSame(TransitionResult::CHANGED, $result->outcome);
        $this->assertSame(self::ME, $result->assignee);
        $this->assertSame(TicketStatus::NEW, $result->status);

        $result = TicketWorkflow::apply(TicketStatus::IN_PROGRESS, self::OTHER, StaffAction::ASSIGN_TO_ME, self::ME);
        $this->assertSame(TransitionResult::CHANGED, $result->outcome, 'takes over from someone else');
        $this->assertSame(self::ME, $result->assignee);

        $result = TicketWorkflow::apply(TicketStatus::NEW, self::ME, StaffAction::ASSIGN_TO_ME, self::ME);
        $this->assertSame(TransitionResult::NOOP, $result->outcome);
        $this->assertSame('already_assigned_to_you', $result->reason);
    }

    public function testStartWork(): void
    {
        $result = TicketWorkflow::apply(TicketStatus::NEW, null, StaffAction::START, self::ME);
        $this->assertSame(TransitionResult::CHANGED, $result->outcome);
        $this->assertSame(TicketStatus::IN_PROGRESS, $result->status);
        $this->assertSame(self::ME, $result->assignee, 'unassigned ticket is assigned to the actor');

        $result = TicketWorkflow::apply(TicketStatus::NEW, self::OTHER, StaffAction::START, self::ME);
        $this->assertSame(self::OTHER, $result->assignee, 'existing assignee is kept');

        $this->assertSame(TransitionResult::NOOP, TicketWorkflow::apply(TicketStatus::IN_PROGRESS, self::ME, StaffAction::START, self::ME)->outcome);

        $result = TicketWorkflow::apply(TicketStatus::RESOLVED, self::ME, StaffAction::START, self::ME);
        $this->assertSame(TransitionResult::INVALID, $result->outcome);
        $this->assertSame('resolved_reopen_first', $result->reason);
    }

    public function testResolveAndReopen(): void
    {
        foreach ([TicketStatus::NEW, TicketStatus::IN_PROGRESS] as $status) {
            $result = TicketWorkflow::apply($status, null, StaffAction::RESOLVE, self::ME);
            $this->assertSame(TransitionResult::CHANGED, $result->outcome);
            $this->assertSame(TicketStatus::RESOLVED, $result->status);
        }

        $this->assertSame(TransitionResult::NOOP, TicketWorkflow::apply(TicketStatus::RESOLVED, null, StaffAction::RESOLVE, self::ME)->outcome);

        $result = TicketWorkflow::apply(TicketStatus::RESOLVED, self::OTHER, StaffAction::REOPEN, self::ME);
        $this->assertSame(TransitionResult::CHANGED, $result->outcome);
        $this->assertSame(TicketStatus::NEW, $result->status);
        $this->assertSame(self::OTHER, $result->assignee);

        foreach ([TicketStatus::NEW, TicketStatus::IN_PROGRESS] as $status) {
            $this->assertSame(TransitionResult::NOOP, TicketWorkflow::apply($status, null, StaffAction::REOPEN, self::ME)->outcome);
        }
    }
}
