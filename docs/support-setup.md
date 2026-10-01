# Customer support (/support) — setup and operations

> Deploying for the first time? Follow the step-by-step [go-live guide](support-go-live.md); this page is the reference behind it.

Customers submit a request at `/support` (Czech and English). The request is saved as a ticket in MySQL, then posted to the Slack channel configured for its project. Staff assign it and change its status with buttons on that Slack message. Replies to the customer go by email, to the address they entered.

One ticket can contain **up to 10 issues**. Each issue has its own type, priority, subject and description; the contact details and project are entered once. Status and assignee belong to the whole ticket, and its priority is High if any issue is High. In Slack:

- A **single-issue** ticket is one message with the full description.
- A **multi-issue** ticket posts a compact overview (every issue's subject, type, priority and a short preview) with the buttons. Each issue's full details follow as a **reply in that message's thread**. This keeps every message well within Slack's limits (50 blocks, 3,000 characters per section) even with 10 long issues.

## How it fits the hosting

berycode.cz is a Next.js **static export** (`output: "export"`) served by Endora, which has PHP and MySQL but no Node.js. The backend is therefore plain PHP 8.1+ with no Composer dependencies:

```
backend/web/.htaccess             copied into out/ (web root): HTTP → HTTPS redirect, except cron.php
backend/web/api/support/          copied into out/api/support/ by `npm run build` (postbuild)
  tickets.php                     POST  public form intake
  projects.php                    GET   active projects for the form's project list
  slack-actions.php               POST  Slack interactivity Request URL
  cron.php                        GET   retry job (secret-protected)
  _app/                           code; no secrets; every file is inert over HTTP (+ .htaccess deny)
backend/migrations/*.sql          schema (run from your machine, or import in phpMyAdmin)
backend/bin/support.php           operator CLI: npm run support -- help
backend/config/                   example project config, production config template, Slack manifest
```

The database is the queue. A ticket and its pending Slack delivery are committed in one transaction, and the customer sees "received" only after that commit. The first Slack post runs right after the response. If it fails, `cron.php` retries with backoff. The ticket status in the database is authoritative; Slack messages are re-rendered from it.

## 1. Database and migrations

1. In Endora administration create a MySQL/MariaDB database and user. Note the host, database name, user and password. **(you supply)**
2. Create the tables. Choose one:
   - **From your machine (recommended):** enable remote MySQL access for your IP in Endora. Then fill in the production config file (section 6); keep your local copy as `berycode-support-config.php` in the repo root, which is git-ignored. Run:
     ```bash
     npm run support -- migrate --config=berycode-support-config.php
     ```
   - **phpMyAdmin:** import each file in `backend/migrations/` once, in order (`001_…`, then `002_…`). Each file records its version, so `migrate` skips it later. `002` converts any existing tickets to single-issue tickets.
3. Check the result: `npm run support -- status --config=berycode-support-config.php`.

With `--config`, the CLI uses exactly the file you upload and ignores `.env.local`. Real environment variables still win: if Endora shows a different hostname for remote access, run e.g. `SUPPORT_DB_HOST=<remote host> npm run support -- … --config=berycode-support-config.php`.

Collation note: lookup keys use `utf8mb4_bin` and are normalized in PHP (trimmed, collapsed whitespace, lower case), so matching is exact and predictable. Tested against MariaDB 10.11 and MySQL 8.0.

## 2. Create and install the Slack app

1. Go to <https://api.slack.com/apps> → **Create New App** → **From a manifest** → choose your workspace. Paste `backend/config/slack-app-manifest.yml`. Change `request_url` if the domain differs.
2. **Install to Workspace**.
3. Copy these values into the production config **(you supply)**:
   - _OAuth & Permissions_ → **Bot User OAuth Token** (`xoxb-…`) → `SLACK_BOT_TOKEN`
   - _Basic Information_ → **Signing Secret** → `SLACK_SIGNING_SECRET`
   - _Basic Information_ → **App ID** (`A…`) → `SLACK_APP_ID`
   - Workspace ID (`T…`) → `SLACK_TEAM_ID`. `npm run support -- slack:check` prints it and verifies it; it sends no message.
4. Staff allowlist: each person's Slack profile → ⋯ → **Copy member ID** (`U…`) → `SLACK_ALLOWED_STAFF_USER_IDS` (comma-separated). A valid Slack signature alone does not grant access: only listed members can use the buttons. Everyone else gets a private "not allowed" reply.

## 3. Scopes and channel invitations

- The only bot scope is **`chat:write`**. It covers `chat.postMessage` and `chat.update` of the bot's own messages. Replies to `response_url` need no scope.
- The bot must be a **member of every destination channel**, including private ones. In each channel run `/invite @BeryCode Support`, or use channel settings → Integrations → Add apps. Without this, delivery fails with `not_in_channel` (public channels) or `channel_not_found` (private channels).

## 4. Project-to-channel configuration

Mappings live only in the database and are never sent to the browser.

1. `cp backend/config/projects.example.json backend/config/projects.json` (the copy is git-ignored because it holds real client data).
2. For each project set:
   - `code`: unique, 2–64 chars, `a-z 0-9 . _ -`. It is used in prefill links.
   - `name`: display name. Customers pick it from the form's project list; it is also accepted as typed input.
   - `aliases`: other names customers may type.
   - `slackChannelId`: the **ID**, not the name. Channel → name header → bottom of _About_ → Channel ID (`C…`).
   - `active`: every active project is listed in the support form, which anyone can open. Inactive projects are not listed and are treated as unknown.
   - `public`: only public projects are suggested when a typed name is misspelled. Customers only type when the project list can't be loaded and the form falls back to a text field. The default is `false`.
3. Apply it: `npm run support -- projects:sync backend/config/projects.json --config=berycode-support-config.php`. Add `--dry-run` to preview, or `--deactivate-missing` to deactivate projects not in the file. **Without remote MySQL:** add `--print-sql` instead. It prints the same change as SQL, with no database connection needed; paste it into phpMyAdmin's SQL tab.

The command rejects any configuration where one normalized code, name or alias would belong to two projects. The database also enforces this with a primary key. Changing a project's channel affects **new** tickets only: each ticket stores its destination when it is created.

`npm run support -- seed:demo` loads the fictional example projects for local development. It refuses to run when `SUPPORT_ENV=production`.

Prefilled links for customers: `https://berycode.cz/support/?project=<code>`. The link preselects that project in the list (a code or name works); the server still validates what is submitted.

## 5. Interactivity URL

It must be public HTTPS and reachable by Slack:

```
https://berycode.cz/api/support/slack-actions.php
```

It is already in the manifest. You can change it later under _Interactivity & Shortcuts_. Every request's `X-Slack-Signature` is verified over the raw body, with a 5-minute timestamp window, before anything is parsed. The workspace (`SLACK_TEAM_ID`), app (`SLACK_APP_ID`), staff allowlist, action ID and the ticket's recorded channel and message are then checked. Slack gets its 200 response as soon as the database change commits. `chat.update` runs after that.

## 6. Configuration (environment variables)

Endora cannot set environment variables, so production uses a PHP file with the same keys:

1. `cp backend/config/support-config.example.php berycode-support-config.php` (the repo-root copy is git-ignored) and fill in the values marked REQUIRED.
2. Upload it via FTP to **one directory above the web root** as `berycode-support-config.php`. If your FTP account can't write there, upload it to `<web root>/api/support/_app/config.php`. `npm run build` never copies a `config.php`. Be careful with "mirror/delete" FTP sync modes, which would delete the second location.
3. Verify from your machine: `npm run support -- config:check --config=berycode-support-config.php`. It prints key names only, never values.

| Key                                                                 | Required    | Purpose                                                                        |
| ------------------------------------------------------------------- | ----------- | ------------------------------------------------------------------------------ |
| `SUPPORT_ENV`                                                       | yes         | `production` (the default when missing)                                        |
| `SUPPORT_DB_HOST/PORT/NAME/USER/PASSWORD`                           | yes         | MySQL connection                                                               |
| `SUPPORT_HASH_SECRET`                                               | yes         | ≥32 chars; HMAC key for rate-limit buckets, so no raw IPs or emails are stored |
| `SUPPORT_CRON_SECRET`                                               | yes         | ≥32 chars; protects `cron.php`                                                 |
| `SLACK_BOT_TOKEN`                                                   | yes         | `xoxb-…` bot token                                                             |
| `SLACK_SIGNING_SECRET`                                              | yes         | request signature verification                                                 |
| `SLACK_TEAM_ID`                                                     | yes         | only this workspace is accepted                                                |
| `SLACK_ALLOWED_STAFF_USER_IDS`                                      | yes         | who may use the buttons; empty means nobody                                    |
| `SLACK_APP_ID`                                                      | recommended | reject payloads from other apps                                                |
| `SUPPORT_ALLOWED_ORIGINS`                                           | recommended | `https://berycode.cz,https://www.berycode.cz`                                  |
| `SUPPORT_CRON_ALLOWED_IPS`                                          | optional    | restrict `cron.php` to the scheduler's IPs                                     |
| `SUPPORT_CLIENT_IP_HEADER`                                          | optional    | trusted proxy header for client IPs (see below)                                |
| `SUPPORT_SLACK_LOCALE`                                              | optional    | `cs` (default) or `en` for Slack messages                                      |
| `SUPPORT_SLACK_TIMEOUT_SECONDS`, `SUPPORT_CRON_TIME_BUDGET_SECONDS` | optional    | on Endora Free (10 s PHP limit) use `5` and `6`                                |
| `SUPPORT_RATE_LIMIT_*`                                              | optional    | defaults: 10/10 min and 40/day per IP, 5/hour per email, 100/hour in total     |

Client IP check: open `https://berycode.cz/api/support/cron.php?key=<SUPPORT_CRON_SECRET>` in a browser. `request_ip` in the JSON should be **your** public IP. If it shows a private address (10.x, 172.16–31.x, 192.168.x, 127.x), Endora proxies the request. In that case set `SUPPORT_CLIENT_IP_HEADER` to the header its proxy sets (usually `X-Real-IP` or `X-Forwarded-For`); otherwise every visitor would share one rate-limit bucket.

## 7. Delivery retries (cron)

`cron.php` delivers pending tickets, retries failed message updates and purges expired rate-limit rows. Run it every **5 minutes**:

- **Endora Fun/Max:** administration → HOSTING → WEB → CRON → script URL `www.berycode.cz/api/support/cron.php?key=<SUPPORT_CRON_SECRET>`, with no `http://`. Endora calls cron over plain HTTP. When saving, it checks the URL (apparently with `HEAD`) and rejects anything but a 200 with "The script for cron job doesn't exist". So:
  - Endora's **Vynutit přesměrování http:// na https://** (domain → Zabezpečení SSL/TLS) must be off. `backend/web/.htaccess`, copied to the web root at build, does the HTTPS redirect instead and exempts only `/api/support/cron.php`.
  - `cron.php` answers `HEAD` with 200, without the key and without doing any work, and logs `cron_probe`.

  The [go-live guide](support-go-live.md#step-7--schedule-the-retry-job-5-min) gives a loop-safe order for switching over. Each run logs a `cron_run` line to the PHP error log. Optionally, set `SUPPORT_CRON_ALLOWED_IPS` to Endora's cron servers (62.109.128.59, 212.57.32.9, 62.109.150.10, 212.57.32.162) after the job is saved. First check the emailed `request_ip`.

- **Endora Free** (no cron): use an external scheduler such as cron-job.org. Prefer sending the secret as the header `Authorization: Bearer <secret>` over putting it in the query string. Also set `SUPPORT_SLACK_TIMEOUT_SECONDS=5` and `SUPPORT_CRON_TIME_BUDGET_SECONDS=6`.
- **Manually from your machine:** `npm run support -- deliveries:run --config=berycode-support-config.php`.

Retry behavior:

- Transient failures (network, HTTP 5xx, 429, `internal_error`, and so on) retry after 1, 2, 5, 15, 30 minutes, then 1, 2, 4 and 8 hours, for up to 10 attempts. A `Retry-After` header is honored.
- Configuration or permission errors (`not_in_channel`, `channel_not_found`, `invalid_auth`, and so on) stop at `FAILED` right away. After fixing the cause, run `npm run support -- deliveries:requeue --all-failed` (or `--ticket=BC-000123`).
- If `chat.update` fails, the status change is kept and the update is retried the same way.

## 8. Local testing

```bash
npm install
npm run support:test-db                  # MariaDB in Docker on 127.0.0.1:33306
npm test                                 # PHP suite: unit + database + real HTTP (90 tests)
npm run test:support -- --unit           # unit tests only, no database
npm run lint && npx tsc --noEmit && npm run build
```

Full local stack with a mock Slack that writes payloads to a file:

```bash
docker exec berycode-support-test-db mariadb -uroot -psupport-test -e "CREATE DATABASE support_dev"
cp .env.example .env.local   # then set SUPPORT_DB_* (root / support-test / support_dev, port 33306),
                             # SUPPORT_SLACK_MODE=mock and an absolute SUPPORT_MOCK_SLACK_LOG
npm run support -- migrate && npm run support -- seed:demo
npm run support:dev          # PHP API on :8080
npm run dev                  # Next on :3000, /api/support/* is proxied to :8080
# open http://localhost:3000/support/?project=demo-kavarna
```

To check the exact production layout, run `npm run build`, then `php -S 127.0.0.1:8090 -t out backend/dev/router.php`.

### Real end-to-end checklist (after deploying)

- [ ] `npm run support -- config:check` and `slack:check` pass (`--config=berycode-support-config.php`).
- [ ] The bot is invited to each project channel; `projects:list` shows the right channel IDs.
- [ ] Submitting `/support/?project=<code>` shows a `BC-…` number, and the message appears in that project's channel within seconds.
- [ ] A ticket with 3 issues arrives as one overview message with the 3 issues' details as thread replies, in order.
- [ ] The form's project list shows every active project and no inactive one.
- [ ] **Assign to me → Start work → Resolve → Reopen** each update the same Slack message. `status` shows sync `IDLE`.
- [ ] A Slack member **not** in the allowlist gets a private "not allowed" reply, and nothing changes.
- [ ] The cron URL returns `{"ok":true,…}`, `request_ip` looks right, and the Endora cron job is scheduled.
- [ ] Temporarily remove the bot from a channel and submit → `status` shows `FAILED slack:not_in_channel`. Re-invite, run `deliveries:requeue --all-failed`, and it is delivered on the next cron run.

## 9. Common delivery errors

Check with `npm run support -- status --config=berycode-support-config.php`. It shows tickets needing attention, with sanitized error codes and no customer content.

| Error                                                                   | Cause                                                                                                                               | Fix                                                                                               |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `slack:not_in_channel`                                                  | bot not in the public channel                                                                                                       | `/invite @BeryCode Support`, then requeue                                                         |
| `slack:channel_not_found`                                               | wrong channel ID, or private channel without the bot                                                                                | fix `slackChannelId` / invite the bot, then requeue. Existing tickets keep their stored channel.  |
| `slack:is_archived`                                                     | channel archived                                                                                                                    | unarchive or point the project at a new channel, then requeue                                     |
| `slack:invalid_auth`, `not_authed`, `token_revoked`, `account_inactive` | bad or revoked token                                                                                                                | reinstall the app, update `SLACK_BOT_TOKEN`, then requeue                                         |
| `slack:missing_scope`                                                   | `chat:write` missing                                                                                                                | add the scope, reinstall, then requeue                                                            |
| `slack:config_missing_bot_token`                                        | `SLACK_BOT_TOKEN` not configured                                                                                                    | fix the config file, then requeue                                                                 |
| `slack:ratelimited`                                                     | Slack rate limit                                                                                                                    | nothing; retried after `Retry-After`                                                              |
| `slack:network:*`, `slack:http_5xx`                                     | Slack or network outage, or outbound HTTPS blocked by the host                                                                      | retried automatically. If it persists on Endora, ask them to allow outbound HTTPS to `slack.com`. |
| `… (ambiguous: request may have reached Slack)`                         | timeout after the request was sent                                                                                                  | retried; see the duplicate note below                                                             |
| sync `slack:message_not_found`                                          | the Slack message was deleted                                                                                                       | the status is still correct in the database; there is no message to update                        |
| Slack says "This app responded with an error" on a click                | `slack-actions.php` returned non-200: bad signing secret (401), wrong `SLACK_TEAM_ID`/`SLACK_APP_ID` (403), or missing config (503) | check the config, PHP error log, and `config:check`                                               |

## Known limitations (V1)

- **Possible duplicate Slack message after an ambiguous failure.** `chat.postMessage` has no idempotency key. If Slack received a post but the response was lost (a timeout after sending, or a worker killed mid-request), the retry posts a second message. The ticket then points at the newest message. Buttons on the older message are refused ("not the current message"), so the state can't diverge. Posts carry message metadata (`berycode_support_ticket`, ticket reference) to allow automated reconciliation later.
- **A thread reply can also be duplicated** in the same way. Replies are posted one at a time, and each is recorded as soon as Slack confirms it. A retry therefore resends only the unconfirmed reply: never the ticket message, and never replies that were already confirmed.
- The first delivery attempt runs after the response inside the same PHP process (`fastcgi_finish_request` when available). Anything interrupted there is picked up by cron, so delivery latency without cron can be up to the cron interval.
- Slack thread replies are not relayed to customers; follow-up is by email. The design leaves room for later additions: tickets store the customer's language, `support_ticket_events` holds the full history, and the lease-based outbox pattern in `DeliveryService` can be reused for email notifications or a customer portal.
- There is no web admin. Projects are managed with `projects:sync`, and tickets in Slack.
