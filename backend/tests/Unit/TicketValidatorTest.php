<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Unit;

use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Tickets\Priority;
use BeryCode\Support\Tickets\RequestType;
use BeryCode\Support\Tickets\TicketValidator;

final class TicketValidatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function issue(array $overrides = []): array
    {
        return array_merge([
            'requestType' => 'bug',
            'priority' => 'normal',
            'subject' => 'Broken form',
            'description' => str_repeat('x', 20),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jan Novák',
            'email' => 'jan@example.com',
            'project' => 'acme-web',
            'issues' => [$this->issue()],
            'locale' => 'en',
            'idempotencyKey' => '0f8fad5b-d9cb-469f-a165-70867728950e',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function errors(array $overrides): array
    {
        return TicketValidator::validate($this->valid($overrides))[1];
    }

    /** Error for one field of a single-issue ticket. */
    private function issueError(array $issueOverrides, string $field): ?string
    {
        return $this->errors(['issues' => [$this->issue($issueOverrides)]])['issues.0.' . $field] ?? null;
    }

    public function testAcceptsValidInputAndNormalizes(): void
    {
        [$submission, $errors] = TicketValidator::validate($this->valid([
            'name' => "  Jan \t  Novák ",
            'issues' => [$this->issue([
                'subject' => "Broken\nform",
                'description' => "Line one is here\r\nline two is here\r\n",
                'requestType' => 'change_request',
                'priority' => 'high',
            ])],
        ]));

        $this->assertSame([], $errors);
        $this->assertSame('Jan Novák', $submission->customerName);
        $issue = $submission->issues[0];
        $this->assertSame('Broken form', $issue->subject);
        $this->assertSame("Line one is here\nline two is here", $issue->description);
        $this->assertSame(RequestType::CHANGE_REQUEST, $issue->requestType);
        $this->assertSame(Priority::HIGH, $issue->priority);
    }

    public function testMultipleIssuesKeepOrderAndDeriveTicketTitleAndPriority(): void
    {
        [$submission, $errors] = TicketValidator::validate($this->valid(['issues' => [
            $this->issue(['subject' => 'First issue', 'priority' => 'normal', 'requestType' => 'bug']),
            $this->issue(['subject' => 'Second issue', 'priority' => 'high', 'requestType' => 'change_request']),
            $this->issue(['subject' => 'Third issue', 'priority' => null, 'requestType' => 'other']),
        ]]));

        $this->assertSame([], $errors);
        $this->assertCount(3, $submission->issues);
        $this->assertSame(['First issue', 'Second issue', 'Third issue'], array_map(static fn ($issue) => $issue->subject, $submission->issues));
        $this->assertSame('First issue', $submission->subject());
        $this->assertSame(Priority::HIGH, $submission->priority(), 'ticket is high when any issue is');
        $this->assertSame(Priority::NORMAL, $submission->issues[2]->priority, 'priority defaults to normal');
    }

    public function testIssueListBounds(): void
    {
        $this->assertSame('required', $this->errors(['issues' => []])['issues'] ?? null);
        $this->assertSame('invalid', $this->errors(['issues' => null])['issues'] ?? null);
        $this->assertSame('invalid', $this->errors(['issues' => ['a' => $this->issue()]])['issues'] ?? null);
        $this->assertSame('invalid', $this->errors(['issues' => 'text'])['issues'] ?? null);
        $this->assertSame([], $this->errors(['issues' => array_fill(0, 10, $this->issue())]));
        $this->assertSame('too_many', $this->errors(['issues' => array_fill(0, 11, $this->issue())])['issues'] ?? null);
        $this->assertSame('required', $this->errors(['issues' => ['not an object']])['issues.0.subject'] ?? null);
    }

    public function testErrorsAreKeyedPerIssue(): void
    {
        $errors = $this->errors(['issues' => [
            $this->issue(),
            $this->issue(['subject' => 'Hi', 'requestType' => 'feature']),
            $this->issue(['description' => 'short', 'priority' => 'urgent']),
        ]]);

        $this->assertSame([
            'issues.1.subject' => 'too_short',
            'issues.1.requestType' => 'invalid',
            'issues.2.description' => 'too_short',
            'issues.2.priority' => 'invalid',
        ], $errors);
    }

    public function testNameBounds(): void
    {
        $this->assertSame('too_short', $this->errors(['name' => 'J'])['name'] ?? null);
        $this->assertSame('too_long', $this->errors(['name' => str_repeat('a', 101)])['name'] ?? null);
        $this->assertSame([], $this->errors(['name' => 'Jo']));
        $this->assertSame([], $this->errors(['name' => str_repeat('ř', 100)]), 'limit counts characters, not bytes');
        $this->assertSame('required', $this->errors(['name' => "   \t "])['name'] ?? null);
        $this->assertSame('required', $this->errors(['name' => null])['name'] ?? null);
        $this->assertSame('required', $this->errors(['name' => ['array']])['name'] ?? null);
    }

    public function testEmailValidation(): void
    {
        foreach (['not-an-email', 'a@', '@example.com', "jan@example.com\nBcc: x@y.z", 'jan doe@example.com'] as $email) {
            $this->assertSame('invalid', $this->errors(['email' => $email])['email'] ?? null, $email);
        }

        $this->assertSame('too_long', $this->errors(['email' => str_repeat('a', 64) . '@' . str_repeat('b', 190) . '.cz'])['email'] ?? null);
        $this->assertSame('required', $this->errors(['email' => ''])['email'] ?? null);
        $this->assertSame([], $this->errors(['email' => '  jan.novak+support@example.co.uk ']));
    }

    public function testSubjectAndDescriptionBounds(): void
    {
        $this->assertSame('too_short', $this->issueError(['subject' => 'Abcd'], 'subject'));
        $this->assertNull($this->issueError(['subject' => 'Abcde'], 'subject'));
        $this->assertNull($this->issueError(['subject' => str_repeat('s', 150)], 'subject'));
        $this->assertSame('too_long', $this->issueError(['subject' => str_repeat('s', 151)], 'subject'));

        $this->assertSame('too_short', $this->issueError(['description' => str_repeat('d', 19)], 'description'));
        $this->assertSame('too_short', $this->issueError(['description' => '   ' . str_repeat('d', 19) . '   '], 'description'), 'whitespace does not count');
        $this->assertNull($this->issueError(['description' => str_repeat('d', 5000)], 'description'));
        $this->assertSame('too_long', $this->issueError(['description' => str_repeat('d', 5001)], 'description'));
    }

    public function testProjectRequiredAndBounded(): void
    {
        $this->assertSame('required', $this->errors(['project' => ''])['project'] ?? null);
        $this->assertSame('too_long', $this->errors(['project' => str_repeat('p', 101)])['project'] ?? null);
    }

    public function testEnumsAndIdempotencyKey(): void
    {
        $this->assertSame('invalid', $this->issueError(['requestType' => 'feature'], 'requestType'));
        $this->assertSame('required', $this->issueError(['requestType' => ''], 'requestType'));
        $this->assertSame('invalid', $this->issueError(['priority' => 'urgent'], 'priority'));
        $this->assertSame('invalid', $this->issueError(['priority' => 5], 'priority'));
        $this->assertSame('invalid', $this->errors(['idempotencyKey' => 'short'])['idempotencyKey'] ?? null);
        $this->assertSame('invalid', $this->errors(['idempotencyKey' => str_repeat('a', 65)])['idempotencyKey'] ?? null);
        $this->assertSame('invalid', $this->errors(['idempotencyKey' => "abc'; DROP TABLE x;--aaaa"])['idempotencyKey'] ?? null);
    }

    public function testRejectsInvalidUtf8AndStripsControlCharacters(): void
    {
        $this->assertSame('invalid', $this->issueError(['subject' => "Bad \xC3\x28 bytes"], 'subject'));

        [$submission] = TicketValidator::validate($this->valid(['issues' => [$this->issue([
            'subject' => "Hello\u{202E}dlrow\x07 there",
            'description' => "Keep\ttabs and\nnewlines\x00\x1B[31m but not controls",
        ])]]));

        $this->assertSame('Hellodlrow there', $submission->issues[0]->subject);
        $this->assertSame("Keep\ttabs and\nnewlines[31m but not controls", $submission->issues[0]->description);
    }

    public function testFingerprintCoversEveryIssue(): void
    {
        [$a] = TicketValidator::validate($this->valid(['email' => 'Jan@Example.com']));
        [$b] = TicketValidator::validate($this->valid(['email' => 'jan@example.com']));
        [$c] = TicketValidator::validate($this->valid(['issues' => [$this->issue(), $this->issue(['subject' => 'Another one'])]]));
        [$d] = TicketValidator::validate($this->valid(['issues' => [$this->issue(), $this->issue(['subject' => 'Another two'])]]));

        $this->assertSame($a->fingerprint(), $b->fingerprint());
        $this->assertFalse($a->fingerprint() === $c->fingerprint());
        $this->assertFalse($c->fingerprint() === $d->fingerprint(), 'a change in any issue changes the fingerprint');
    }
}
