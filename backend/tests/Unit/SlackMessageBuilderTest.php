<?php

declare(strict_types=1);

namespace BeryCode\Support\Tests\Unit;

use BeryCode\Support\Slack\SlackMessageBuilder;
use BeryCode\Support\Tests\Support\TestCase;
use BeryCode\Support\Tickets\Priority;
use BeryCode\Support\Tickets\RequestType;
use BeryCode\Support\Tickets\TicketIssue;
use BeryCode\Support\Tickets\TicketStatus;
use BeryCode\Support\Tickets\TicketView;

final class SlackMessageBuilderTest extends TestCase
{
    private const HOSTILE_DESCRIPTION = "Hello @here <!everyone> *bold* _it_ `code` <https://evil.example|click me>\nSecond line";

    private function issue(int $position, array $overrides = []): TicketIssue
    {
        $values = array_merge([
            'requestType' => RequestType::BUG,
            'priority' => Priority::HIGH,
            'subject' => '<!channel> urgent & <https://evil.example|click>',
            'description' => self::HOSTILE_DESCRIPTION,
        ], $overrides);

        return new TicketIssue($position * 10, $position, $values['requestType'], $values['priority'], $values['subject'], $values['description']);
    }

    /** @param list<TicketIssue>|null $issues */
    private function view(array $overrides = [], ?array $issues = null): TicketView
    {
        $issues ??= [$this->issue(1)];
        $values = array_merge([
            'customerName' => 'Jana <@U0ADMIN01> *Nováková*',
            'status' => TicketStatus::NEW,
            'assignee' => null,
            'priority' => Priority::HIGH,
        ], $overrides);

        return new TicketView(
            42,
            'BC-000042',
            'acme-web',
            'Acme Web Studio',
            $values['customerName'],
            'jana@example.com',
            $issues[0]->subject,
            $issues,
            $values['priority'],
            $values['status'],
            $values['assignee'],
            'cs',
            new \DateTimeImmutable('2026-01-15 10:00:00', new \DateTimeZone('UTC')),
            1,
            'C0ACME0001',
            'C0ACME0001',
            '1700000000.000001',
            'DELIVERED',
            1,
            0,
        );
    }

    /** @return list<array{type: string, text: string}> every text object in a message */
    private static function textObjects(array $node): array
    {
        $found = [];

        if (isset($node['type'], $node['text']) && is_string($node['text']) && in_array($node['type'], ['plain_text', 'mrkdwn'], true)) {
            $found[] = $node;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $found = array_merge($found, self::textObjects($child));
            }
        }

        return $found;
    }

    private function assertCustomerTextIsPlain(array $message): void
    {
        $customerFragments = ['<@U0ADMIN01>', '<!channel>', '<!everyone>', '@here', 'evil.example', 'jana@example.com', 'Second line'];

        foreach (self::textObjects($message['blocks']) as $text) {
            foreach ($customerFragments as $fragment) {
                if (str_contains($text['text'], $fragment)) {
                    $this->assertSame('plain_text', $text['type'], 'customer text "' . $fragment . '" must be plain_text');
                }
            }

            if ($text['type'] === 'plain_text') {
                $this->assertFalse($text['emoji'] ?? true, 'emoji shortcodes from customers are not rendered');
            }
        }

        $this->assertStringNotContainsString('<!channel>', $message['text'], 'fallback text is escaped');
    }

    /** @param array{blocks: list<array<string, mixed>>, text: string} $message */
    private function assertWithinBlockKitLimits(array $message): void
    {
        $this->assertLessThanOrEqual(50, count($message['blocks']));
        $this->assertLessThanOrEqual(4000, mb_strlen($message['text']));

        foreach ($message['blocks'] as $block) {
            if ($block['type'] === 'header') {
                $this->assertLessThanOrEqual(150, mb_strlen($block['text']['text']));
            }

            if ($block['type'] === 'section' && isset($block['text'])) {
                $this->assertLessThanOrEqual(3000, mb_strlen($block['text']['text']));
            }

            if (isset($block['fields'])) {
                $this->assertLessThanOrEqual(10, count($block['fields']));

                foreach ($block['fields'] as $field) {
                    $this->assertLessThanOrEqual(2000, mb_strlen($field['text']));
                }
            }
        }
    }

    private static function plainTextOf(array $message): string
    {
        $text = '';

        foreach ($message['blocks'] as $block) {
            if ($block['type'] === 'section' && ($block['text']['type'] ?? null) === 'plain_text') {
                $text .= $block['text']['text'];
            }
        }

        return $text;
    }

    public function testCustomerInputOnlyAppearsInPlainTextObjects(): void
    {
        $builder = new SlackMessageBuilder('en');
        $single = $builder->build($this->view());
        $this->assertCustomerTextIsPlain($single);
        $this->assertStringContainsString('&lt;!channel&gt; urgent &amp; &lt;https://evil.example|click&gt;', $single['text']);

        $multi = $this->view([], [$this->issue(1), $this->issue(2), $this->issue(3)]);
        $this->assertCustomerTextIsPlain($builder->build($multi));

        foreach ($multi->issues as $issue) {
            $this->assertCustomerTextIsPlain($builder->buildIssueReply($multi, $issue));
        }
    }

    public function testSingleIssueShowsFullDescriptionInTheTicketMessage(): void
    {
        $description = mb_substr(trim(str_repeat("Lorem ipsum dolor sit amet, consectetur adipiscing elit. \n", 90)), 0, 5000);
        $message = (new SlackMessageBuilder('cs'))->build($this->view(
            ['customerName' => str_repeat('N', 100)],
            [$this->issue(1, ['description' => $description, 'subject' => str_repeat('S', 150)])],
        ));

        $this->assertWithinBlockKitLimits($message);
        $this->assertSame(preg_replace('/\s+/', '', $description), preg_replace('/\s+/', '', self::plainTextOf($message)), 'whole description delivered');
        $this->assertStringContainsString('Chyba', (string) json_encode($message['blocks'], JSON_UNESCAPED_UNICODE), 'type field shown');
    }

    public function testMultiIssueTicketShowsAnOverviewAndPutsDetailsInThreadReplies(): void
    {
        $issues = [];

        for ($i = 1; $i <= 10; $i++) {
            $issues[] = $this->issue($i, [
                'subject' => 'Issue number ' . $i,
                'description' => $i . ':' . str_repeat('word ' . $i . ' ', 900),
                'priority' => $i === 4 ? Priority::HIGH : Priority::NORMAL,
                'requestType' => $i % 2 === 0 ? RequestType::CHANGE_REQUEST : RequestType::BUG,
            ]);
        }

        $builder = new SlackMessageBuilder('en');
        $ticket = $this->view([], $issues);
        $parent = $builder->build($ticket);
        $json = (string) json_encode($parent['blocks'], JSON_UNESCAPED_UNICODE);

        $this->assertWithinBlockKitLimits($parent);
        $this->assertSame('BC-000042 · Issue number 1 (+9 more)', $parent['blocks'][0]['text']['text']);
        $this->assertStringContainsString('*Issues*\n10', $json);
        $this->assertStringContainsString('full details of each are in the thread', $json);
        $this->assertLessThanOrEqual(10 * 320, mb_strlen(self::plainTextOf($parent)), 'parent carries previews only');

        $previous = -1;

        foreach ($issues as $issue) {
            $position = strpos($json, $issue->position . '. Issue number ' . $issue->position . '\n');
            $this->assertTrue($position !== false && $position > $previous, 'issue ' . $issue->position . ' listed in order');
            $previous = (int) $position;
        }

        $this->assertStringContainsString(':red_circle: *High priority*', $json, 'per-issue priority visible');
        $this->assertStringContainsString('Change request · Normal priority', $json);

        foreach ($issues as $issue) {
            $reply = $builder->buildIssueReply($ticket, $issue);
            $this->assertWithinBlockKitLimits($reply);
            $this->assertSame($issue->position . '/10 · Issue number ' . $issue->position, $reply['blocks'][0]['text']['text']);
            $this->assertSame(preg_replace('/\s+/', '', $issue->description), preg_replace('/\s+/', '', self::plainTextOf($reply)), 'full description in the reply');
            $this->assertStringContainsString('BC-000042', $reply['text']);
            $this->assertStringNotContainsString('support_', (string) json_encode($reply['blocks']), 'no buttons on replies');
        }
    }

    public function testButtonsFollowStatusAndCarryOnlyTheTicketId(): void
    {
        $builder = new SlackMessageBuilder('en');
        $actionIds = static function (array $message): array {
            $actions = array_values(array_filter($message['blocks'], static fn ($block) => $block['type'] === 'actions'));

            return array_map(static fn ($element) => $element['action_id'], $actions[0]['elements'] ?? []);
        };

        $this->assertSame(['support_assign_me', 'support_start', 'support_resolve'], $actionIds($builder->build($this->view())));
        $this->assertSame(['support_assign_me', 'support_resolve'], $actionIds($builder->build($this->view(['status' => TicketStatus::IN_PROGRESS]))));
        $this->assertSame(['support_reopen'], $actionIds($builder->build($this->view(['status' => TicketStatus::RESOLVED]))));

        $message = $builder->build($this->view(['assignee' => 'U0STAFF001']));
        $json = (string) json_encode($message['blocks']);
        $this->assertStringContainsString('<@U0STAFF001>', $json, 'assignee is mentioned');
        $this->assertStringContainsString('"value":"42"', $json);
        $this->assertStringNotContainsString('C0ACME0001', $json, 'channel IDs are not embedded in buttons');
        $this->assertSame('berycode_support_ticket', $message['metadata']['event_type']);
    }

    public function testLocalizedLabels(): void
    {
        $cs = (string) json_encode((new SlackMessageBuilder('cs'))->build($this->view())['blocks'], JSON_UNESCAPED_UNICODE);
        $en = (string) json_encode((new SlackMessageBuilder('en'))->build($this->view())['blocks'], JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Začít pracovat', $cs);
        $this->assertStringContainsString('Priorita', $cs);
        $this->assertStringContainsString('Start work', $en);
        $this->assertStringContainsString('Customer language', $en);

        $multi = $this->view([], [$this->issue(1), $this->issue(2), $this->issue(3), $this->issue(4), $this->issue(5), $this->issue(6)]);
        $header = (new SlackMessageBuilder('cs'))->build($multi)['blocks'][0]['text']['text'];
        $this->assertStringContainsString('(+5 dalších)', $header);
        $header = (new SlackMessageBuilder('cs'))->build($this->view([], [$this->issue(1), $this->issue(2)]))['blocks'][0]['text']['text'];
        $this->assertStringContainsString('(+1 další)', $header);
    }
}
