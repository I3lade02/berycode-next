-- Multiple issues per ticket. Each issue has its own type, priority, subject
-- and description; contact details, project, status and assignee stay on the
-- ticket. Existing tickets become single-issue tickets.

CREATE TABLE IF NOT EXISTS support_ticket_issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    request_type ENUM('BUG', 'CHANGE_REQUEST', 'OTHER') NOT NULL,
    priority ENUM('NORMAL', 'HIGH') NOT NULL DEFAULT 'NORMAL',
    subject VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    created_at DATETIME(3) NOT NULL,
    -- ts of the Slack thread reply holding this issue's details (multi-issue tickets only).
    slack_reply_ts VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_ticket_issues_position (ticket_id, position),
    CONSTRAINT fk_support_ticket_issues_ticket FOREIGN KEY (ticket_id)
        REFERENCES support_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO support_ticket_issues (ticket_id, position, request_type, priority, subject, description, created_at)
SELECT t.id, 1, t.request_type, t.priority, t.subject, t.description, t.created_at
FROM support_tickets t
WHERE NOT EXISTS (SELECT 1 FROM support_ticket_issues i WHERE i.ticket_id = t.id);

-- The ticket keeps `subject` (first issue, used as the ticket title) and
-- `priority` (HIGH when any issue is HIGH).
ALTER TABLE support_tickets DROP COLUMN description, DROP COLUMN request_type;

-- Record this migration so `npm run support -- migrate` skips it after a phpMyAdmin import.
CREATE TABLE IF NOT EXISTS support_schema_migrations (
    version VARCHAR(100) NOT NULL,
    applied_at DATETIME(3) NOT NULL,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO support_schema_migrations (version, applied_at) VALUES ('002_ticket_issues', UTC_TIMESTAMP(3));
