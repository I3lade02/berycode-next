<?php

declare(strict_types=1);

namespace BeryCode\Support\Slack;

/** Staff-facing Slack copy in Czech and English (SUPPORT_SLACK_LOCALE). */
final class SlackLabels
{
    private const LABELS = [
        'cs' => [
            'project' => 'Projekt',
            'status' => 'Stav',
            'type' => 'Typ',
            'priority' => 'Priorita',
            'assignee' => 'Řešitel',
            'customer_language' => 'Jazyk zákazníka',
            'customer' => 'Zákazník',
            'email' => 'E-mail',
            'description' => 'Popis',
            'received' => 'Přijato',
            'unassigned' => 'nepřiřazeno',
            'reply_hint' => 'Zákazníkovi odpovídejte e-mailem na uvedenou adresu.',
            'type_BUG' => 'Chyba',
            'type_CHANGE_REQUEST' => 'Požadavek na změnu',
            'type_OTHER' => 'Jiné',
            'priority_NORMAL' => 'Normální',
            'priority_HIGH' => 'Vysoká',
            'status_NEW' => 'Nový',
            'status_IN_PROGRESS' => 'Rozpracováno',
            'status_RESOLVED' => 'Vyřešeno',
            'language_cs' => 'čeština',
            'language_en' => 'angličtina',
            'header_more' => '(+%s další)',
            'header_more_many' => '(+%s dalších)',
            'issue_count' => 'Počet požadavků',
            'issues_heading' => '*Požadavky (%s)* · celý popis každého najdete ve vlákně',
            'reply_issue_of' => 'požadavek %s/%s',
            'priority_inline' => '%s priorita',
            'button_assign' => 'Přiřadit mně',
            'button_start' => 'Začít pracovat',
            'button_resolve' => 'Vyřešit',
            'button_reopen' => 'Znovu otevřít',
            'reply_not_authorized' => 'Nemáte oprávnění spravovat tikety podpory.',
            'reply_message_mismatch' => 'Tato zpráva nepatří k aktuální zprávě tiketu. Použijte původní zprávu tiketu.',
            'reply_not_found' => 'Tiket nebyl nalezen.',
            'reply_unsupported' => 'Tuto akci nelze provést.',
            'reply_already_assigned_to_you' => 'Tiket %s už máte přiřazený.',
            'reply_already_in_progress' => 'Na tiketu %s se už pracuje.',
            'reply_already_resolved' => 'Tiket %s je už vyřešený.',
            'reply_already_open' => 'Tiket %s je už otevřený.',
            'reply_resolved_reopen_first' => 'Tiket %s je vyřešený. Nejdřív ho znovu otevřete.',
        ],
        'en' => [
            'project' => 'Project',
            'status' => 'Status',
            'type' => 'Type',
            'priority' => 'Priority',
            'assignee' => 'Assignee',
            'customer_language' => 'Customer language',
            'customer' => 'Customer',
            'email' => 'Email',
            'description' => 'Description',
            'received' => 'Received',
            'unassigned' => 'unassigned',
            'reply_hint' => 'Reply to the customer by email at the address above.',
            'type_BUG' => 'Bug',
            'type_CHANGE_REQUEST' => 'Change request',
            'type_OTHER' => 'Other',
            'priority_NORMAL' => 'Normal',
            'priority_HIGH' => 'High',
            'status_NEW' => 'New',
            'status_IN_PROGRESS' => 'In progress',
            'status_RESOLVED' => 'Resolved',
            'language_cs' => 'Czech',
            'language_en' => 'English',
            'header_more' => '(+%s more)',
            'header_more_many' => '(+%s more)',
            'issue_count' => 'Issues',
            'issues_heading' => '*Issues (%s)* · full details of each are in the thread',
            'reply_issue_of' => 'issue %s/%s',
            'priority_inline' => '%s priority',
            'button_assign' => 'Assign to me',
            'button_start' => 'Start work',
            'button_resolve' => 'Resolve',
            'button_reopen' => 'Reopen',
            'reply_not_authorized' => 'You are not allowed to manage support tickets.',
            'reply_message_mismatch' => 'This is not the current message for the ticket. Use the original ticket message.',
            'reply_not_found' => 'Ticket not found.',
            'reply_unsupported' => 'This action is not available.',
            'reply_already_assigned_to_you' => 'Ticket %s is already assigned to you.',
            'reply_already_in_progress' => 'Ticket %s is already in progress.',
            'reply_already_resolved' => 'Ticket %s is already resolved.',
            'reply_already_open' => 'Ticket %s is already open.',
            'reply_resolved_reopen_first' => 'Ticket %s is resolved. Reopen it first.',
        ],
    ];

    public function __construct(private string $locale)
    {
        $this->locale = $locale === 'en' ? 'en' : 'cs';
    }

    public function get(string $key, string ...$args): string
    {
        $text = self::LABELS[$this->locale][$key] ?? self::LABELS['en'][$key] ?? $key;

        return $args === [] ? $text : sprintf($text, ...$args);
    }

    public function has(string $key): bool
    {
        return isset(self::LABELS[$this->locale][$key]);
    }
}
