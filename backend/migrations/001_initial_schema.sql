-- Support ticketing schema (MySQL 5.7+ / MariaDB 10.3+, InnoDB, utf8mb4).
-- All DATETIME values are UTC. Safe to re-run: every statement uses IF NOT EXISTS.
-- Apply with `npm run support -- migrate` or import this file in phpMyAdmin.

CREATE TABLE IF NOT EXISTS support_projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    slack_channel_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(3) NOT NULL,
    updated_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_support_projects_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every normalized code, display name and alias. The primary key makes each
-- lookup key globally unique, so an entered value can never match two projects.
CREATE TABLE IF NOT EXISTS support_project_keys (
    lookup_key VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    key_type ENUM('code', 'name', 'alias') NOT NULL,
    PRIMARY KEY (lookup_key),
    KEY idx_support_project_keys_project (project_id),
    CONSTRAINT fk_support_project_keys_project FOREIGN KEY (project_id)
        REFERENCES support_projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL,
    project_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(254) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    request_type ENUM('BUG', 'CHANGE_REQUEST', 'OTHER') NOT NULL,
    priority ENUM('NORMAL', 'HIGH') NOT NULL DEFAULT 'NORMAL',
    locale ENUM('cs', 'en') NOT NULL DEFAULT 'cs',
    status ENUM('NEW', 'IN_PROGRESS', 'RESOLVED') NOT NULL DEFAULT 'NEW',
    assignee_slack_user_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    -- Incremented on every staff change; compared with slack_synced_version.
    version INT UNSIGNED NOT NULL DEFAULT 1,
    idempotency_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    submission_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(3) NOT NULL,
    updated_at DATETIME(3) NOT NULL,
    resolved_at DATETIME(3) NULL,

    -- Destination captured when the ticket was created. Later project
    -- configuration changes never move an existing conversation.
    slack_channel_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    -- Channel and ts returned by chat.postMessage (the ticket's Slack thread).
    slack_message_channel_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    slack_message_ts VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,

    delivery_state ENUM('PENDING', 'SENDING', 'DELIVERED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    delivery_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    delivery_next_attempt_at DATETIME(3) NULL,
    delivery_lease_until DATETIME(3) NULL,
    delivery_last_attempt_at DATETIME(3) NULL,
    delivery_last_error VARCHAR(255) NULL,
    delivered_at DATETIME(3) NULL,

    -- chat.update synchronisation of the original message.
    slack_synced_version INT UNSIGNED NOT NULL DEFAULT 0,
    sync_state ENUM('IDLE', 'PENDING', 'FAILED') NOT NULL DEFAULT 'IDLE',
    sync_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sync_next_attempt_at DATETIME(3) NULL,
    sync_lease_until DATETIME(3) NULL,
    sync_last_error VARCHAR(255) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_support_tickets_reference (reference),
    UNIQUE KEY uq_support_tickets_idempotency (idempotency_key),
    UNIQUE KEY uq_support_tickets_slack_message (slack_message_channel_id, slack_message_ts),
    KEY idx_support_tickets_delivery_due (delivery_state, delivery_next_attempt_at),
    KEY idx_support_tickets_sync_due (sync_state, sync_next_attempt_at),
    KEY idx_support_tickets_project (project_id),
    CONSTRAINT fk_support_tickets_project FOREIGN KEY (project_id) REFERENCES support_projects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_type ENUM('CUSTOMER', 'SLACK_USER', 'SYSTEM') NOT NULL,
    actor_id VARCHAR(64) NULL,
    from_status ENUM('NEW', 'IN_PROGRESS', 'RESOLVED') NULL,
    to_status ENUM('NEW', 'IN_PROGRESS', 'RESOLVED') NULL,
    from_assignee VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    to_assignee VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    detail VARCHAR(255) NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_support_ticket_events_ticket (ticket_id, id),
    CONSTRAINT fk_support_ticket_events_ticket FOREIGN KEY (ticket_id)
        REFERENCES support_tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processed Slack interactions, so a replayed payload is applied at most once.
CREATE TABLE IF NOT EXISTS support_slack_interactions (
    dedupe_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ticket_id BIGINT UNSIGNED NULL,
    slack_user_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    action_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (dedupe_key),
    KEY idx_support_slack_interactions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window counters shared by every PHP worker. Keys are HMACs, so no raw
-- IP or email address is stored.
CREATE TABLE IF NOT EXISTS support_rate_limits (
    bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    hits INT UNSIGNED NOT NULL,
    expires_at DATETIME(3) NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY idx_support_rate_limits_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

-- Record this migration so `npm run support -- migrate` skips it after a phpMyAdmin import.
CREATE TABLE IF NOT EXISTS support_schema_migrations (
    version VARCHAR(100) NOT NULL,
    applied_at DATETIME(3) NOT NULL,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO support_schema_migrations (version, applied_at) VALUES ('001_initial_schema', UTC_TIMESTAMP(3));
