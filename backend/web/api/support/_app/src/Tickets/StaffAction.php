<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/** Slack button action_ids. Anything else in a payload is rejected. */
enum StaffAction: string
{
    case ASSIGN_TO_ME = 'support_assign_me';
    case START = 'support_start';
    case RESOLVE = 'support_resolve';
    case REOPEN = 'support_reopen';
}
