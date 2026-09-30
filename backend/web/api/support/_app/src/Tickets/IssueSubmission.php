<?php

declare(strict_types=1);

namespace BeryCode\Support\Tickets;

/** One validated issue (bug, change request, …) inside a ticket submission. */
final class IssueSubmission
{
    public function __construct(
        public readonly RequestType $requestType,
        public readonly Priority $priority,
        public readonly string $subject,
        public readonly string $description,
    ) {
    }
}
