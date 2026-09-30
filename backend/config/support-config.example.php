<?php

/*
 * Production configuration for the support backend on Endora.
 *
 * 1. Copy this file and fill in every value marked REQUIRED.
 * 2. Upload it via FTP to ONE of these locations (the first found wins):
 *      a) one directory ABOVE the public web root, named
 *         berycode-support-config.php                 (preferred, not web-reachable)
 *      b) <web root>/api/support/_app/config.php      (if (a) is not possible)
 * 3. Never commit the filled-in file. `npm run build` never copies config.php
 *    into out/, so redeploying the site does not overwrite it — but a
 *    "mirror/delete remote files" FTP sync would delete location (b).
 *
 * Requesting this file over HTTP executes it and returns nothing.
 */

return [
    'SUPPORT_ENV' => 'production',

    // REQUIRED: Endora administration -> MySQL databases.
    'SUPPORT_DB_HOST' => '',
    'SUPPORT_DB_PORT' => '3306',
    'SUPPORT_DB_NAME' => '',
    'SUPPORT_DB_USER' => '',
    'SUPPORT_DB_PASSWORD' => '',

    // REQUIRED: 64 random hex characters each, different values.
    // php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
    'SUPPORT_HASH_SECRET' => '',
    'SUPPORT_CRON_SECRET' => '',

    // Recommended.
    'SUPPORT_ALLOWED_ORIGINS' => 'https://berycode.cz,https://www.berycode.cz',
    'SUPPORT_CRON_ALLOWED_IPS' => '',       // e.g. Endora cron: 62.109.128.59,212.57.32.9,62.109.150.10,212.57.32.162
    'SUPPORT_CLIENT_IP_HEADER' => '',       // only if cron.php reports a private request_ip (see docs)

    // Endora Free has a 10 s PHP limit: use 5 and 6. Paid plans: defaults are fine.
    'SUPPORT_SLACK_TIMEOUT_SECONDS' => '8',
    'SUPPORT_CRON_TIME_BUDGET_SECONDS' => '20',

    // REQUIRED: Slack app -> OAuth & Permissions -> Bot User OAuth Token (xoxb-...).
    'SLACK_BOT_TOKEN' => '',
    // REQUIRED: Slack app -> Basic Information -> Signing Secret.
    'SLACK_SIGNING_SECRET' => '',
    // REQUIRED: workspace ID (T...). Basic Information / `npm run support -- slack:check`.
    'SLACK_TEAM_ID' => '',
    // Recommended: App ID (A...) from Basic Information.
    'SLACK_APP_ID' => '',
    // REQUIRED: member IDs (U...) allowed to use the buttons, comma-separated.
    'SLACK_ALLOWED_STAFF_USER_IDS' => '',

    'SUPPORT_SLACK_LOCALE' => 'cs',
];
