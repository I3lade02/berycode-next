<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

final class CreatedTicket
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        /** True when an earlier submission with the same idempotency key was returned. */
        public readonly bool $duplicate,
    ) {
    }
}
