<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/** The idempotency key was already used for a request with different content. */
final class IdempotencyConflict extends \RuntimeException
{
}
