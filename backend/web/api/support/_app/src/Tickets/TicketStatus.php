<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

enum TicketStatus: string
{
    case NEW = 'NEW';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RESOLVED = 'RESOLVED';
}
