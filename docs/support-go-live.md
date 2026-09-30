# Go-live guide: customer support on berycode.cz

A start-to-finish checklist for the first deployment of `/support`. Follow the steps in order; plan for about 1–2 hours. Background and details for every setting are in [support-setup.md](support-setup.md).

**You need:**

- access to the Endora administration (webadmin) and FTP for berycode.cz
- a Slack workspace where you can install apps
- this repository on your machine, with Node.js and PHP 8.1+ (`php -v`)
- Docker, only if you want to run the automated tests (step 0)

**Values you will collect** (store them in a password manager):

| Value                                | Where you get it                                  | Used for                                     |
| ------------------------------------ | ------------------------------------------------- | -------------------------------------------- |
| MySQL host, database, user, password | Endora webadmin → Databáze MySQL                  | `SUPPORT_DB_*`                               |
| Two random secrets                   | generated in step 3                               | `SUPPORT_HASH_SECRET`, `SUPPORT_CRON_SECRET` |
| Bot token (`xoxb-…`)                 | Slack app → OAuth & Permissions                   | `SLACK_BOT_TOKEN`                            |
| Signing secret                       | Slack app → Basic Information                     | `SLACK_SIGNING_SECRET`                       |
| App ID (`A…`)                        | Slack app → Basic Information                     | `SLACK_APP_ID`                               |
| Workspace ID (`T…`)                  | Slack in the browser: `app.slack.com/client/T…/…` | `SLACK_TEAM_ID`                              |
| Member IDs of staff (`U…`)           | Slack profile → ⋮ → Copy member ID                | `SLACK_ALLOWED_STAFF_USER_IDS`               |
| Channel ID per project (`C…`)        | channel name → About → Channel ID                 | `backend/config/projects.json`               |

---

## Step 0 — Save the work and run the tests (15 min)

1. Commit everything on a branch:
   ```bash
   git checkout -b feature/support
   git add -A
   git status          # berycode-support-config.php and backend/config/projects.json must NOT appear later
   git commit -m "Add customer support ticketing (/support)"
   ```
2. Run the test suite (needs Docker running):
   ```bash
   npm install
   npm run support:test-db     # starts MariaDB; give it ~10 seconds
   npm test                    # expect: OK: 90 passed, 0 failed
   docker stop berycode-support-test-db
   ```
3. Optional: try the form locally with a mock Slack. See [support-setup.md §8](support-setup.md#8-local-testing).

## Step 1 — Prepare Endora (10 min)

1. **PHP version:** webadmin → your hosting → **PHP nastavení** → choose **PHP 8.1 or newer** (the newest offered is best) → save.
2. **Database:** webadmin → **Databáze MySQL** → create a database and user (the quick-create option is fine). Write down the **server hostname, database name, user and password**, and note the **phpMyAdmin** link shown there.
3. **Choose how you will reach the database from your machine:**
   - **Path A — remote MySQL (recommended):** in the database settings, allow your current public IP (`curl ifconfig.me` shows it). Endora allows only a few IPs, depending on the plan. With this path, all setup commands run from your terminal.
   - **Path B — phpMyAdmin only:** if remote access isn't available on your plan, or you don't want it. The steps below give the phpMyAdmin alternative wherever it matters.
4. **Check your plan:** **Fun/Max** includes CRON (step 7). **Free** has no CRON (you'll use cron-job.org instead) and a 10-second PHP time limit (step 3 sets matching timeouts).

## Step 2 — Create the Slack app (15 min)

1. **Channels:** create one channel per client project, e.g. `#support-acme` (private is recommended). Also create **`#support-test`** for your own testing.
2. Go to <https://api.slack.com/apps> → **Create New App** → **From a manifest** → pick your workspace. Paste the contents of `backend/config/slack-app-manifest.yml`, then **Next** → **Create**. The manifest already contains the button URL (`https://berycode.cz/api/support/slack-actions.php`) and the single permission the app needs (`chat:write`).
3. **Install to Workspace** → **Allow**.
4. Copy the **Bot User OAuth Token** (_OAuth & Permissions_), then the **Signing Secret** and **App ID** (_Basic Information_).
5. **Workspace ID:** open Slack in a web browser. The address is `app.slack.com/client/T…/…`, and the `T…` part is the ID.
6. **Staff member IDs:** click your profile picture → **Profile** → **⋮** → **Copy member ID** (`U…`). Do the same for anyone else who should manage tickets. Only these people can use the ticket buttons.
7. **In every support channel**, including `#support-test`:
   - Type `/invite @BeryCode Support`. The bot can't post into a channel it isn't in; this matters especially for private channels.
   - Click the channel name → **About** → copy the **Channel ID** (`C…`) at the bottom.

## Step 3 — Write the production config file (10 min)

This one PHP file holds every secret. You upload it to the server, and the CLI on your machine uses the same file via `--config`.

1. Create your local copy in the repository root. It is git-ignored, so it won't be committed:
   ```bash
   cp backend/config/support-config.example.php berycode-support-config.php
   ```
2. Generate two different random secrets by running this twice:
   ```bash
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```
3. Open `berycode-support-config.php` and fill in:
   - `SUPPORT_DB_HOST`, `SUPPORT_DB_NAME`, `SUPPORT_DB_USER`, `SUPPORT_DB_PASSWORD`: from step 1
   - `SUPPORT_HASH_SECRET`: the first secret
   - `SUPPORT_CRON_SECRET`: the second secret
   - `SLACK_BOT_TOKEN`, `SLACK_SIGNING_SECRET`, `SLACK_TEAM_ID`, `SLACK_APP_ID`, `SLACK_ALLOWED_STAFF_USER_IDS` (comma-separated `U…` IDs): from step 2
   - Leave `SUPPORT_ENV` as `'production'` and keep `SUPPORT_ALLOWED_ORIGINS`.
   - **Endora Free only:** set `SUPPORT_SLACK_TIMEOUT_SECONDS` to `'5'` and `SUPPORT_CRON_TIME_BUDGET_SECONDS` to `'6'`.
4. Check the file:
   ```bash
   npm run support -- config:check --config=berycode-support-config.php
   # expect: Configuration looks complete for production.
   npm run support -- slack:check --config=berycode-support-config.php
   # expect: ... SLACK_TEAM_ID matches. No message was sent.
   ```
   `config:check` lists key names only, never values. Fix whatever it reports and run it again.

## Step 4 — Create the tables and your projects (10 min)

1. **Projects file** (git-ignored, because client names are private):

   ```bash
   cp backend/config/projects.example.json backend/config/projects.json
   ```

   Replace the demo entries with:
   - a test project: `"code": "berycode-test"`, the channel ID of `#support-test`, `"public": false`
   - your real client projects, each with its channel ID. Leave `"public": false` unless it's fine for that project's name to be suggested to anyone who mistypes.

   For example:

   ```json
   {
     "projects": [
       {
         "code": "berycode-test",
         "name": "BeryCode test",
         "aliases": [],
         "slackChannelId": "C0XXXXXXXXX",
         "active": true,
         "public": false
       },
       {
         "code": "acme-web",
         "name": "Acme web",
         "aliases": ["acme"],
         "slackChannelId": "C0YYYYYYYYY",
         "active": true,
         "public": false
       }
     ]
   }
   ```

2. **Path A (remote MySQL):**

   ```bash
   npm run support -- migrate --config=berycode-support-config.php
   npm run support -- projects:sync backend/config/projects.json --dry-run --config=berycode-support-config.php
   npm run support -- projects:sync backend/config/projects.json --config=berycode-support-config.php
   npm run support -- projects:list --config=berycode-support-config.php
   ```

   If Endora shows a different hostname for remote access than for scripts on the server, prefix each command with `SUPPORT_DB_HOST=<remote hostname>`. The file keeps the server's hostname.

   **Path B (phpMyAdmin):**
   - In phpMyAdmin, select the database → **Import**. Import `backend/migrations/001_initial_schema.sql`, then `002_ticket_issues.sql`. Each file once, in this order.
   - Generate the project SQL and copy it to the clipboard (this needs no database access):
     ```bash
     npm run support -- projects:sync backend/config/projects.json --print-sql | pbcopy
     ```
     In phpMyAdmin: **SQL** tab → paste → **Go**.

## Step 5 — Build and upload (10 min)

1. Build:
   ```bash
   npm run build
   # the last line says: copy-support-backend: copied … files from backend/web into out/
   ```
   `out/` now contains the website and `out/api/support/` (the backend). It never contains your config file.
2. Upload the **contents of `out/`** into Endora's **`web/`** folder over FTP, the same way you deploy the site today. Afterwards these must exist on the server:
   `web/api/support/tickets.php`, `web/api/support/slack-actions.php`, `web/api/support/cron.php`, `web/api/support/_app/…`
3. Upload **`berycode-support-config.php`** into the **FTP root, next to the `web/` folder** (not inside it). That location is not reachable from the web.

## Step 6 — Check the server (5 min)

1. Open <https://berycode.cz/support/>. The form loads, and CZ/EN switching works.
2. Open `https://berycode.cz/api/support/cron.php?key=<SUPPORT_CRON_SECRET>` in your browser:

   | You see                               | Meaning / what to do                                                                                                                                                                                                               |
   | ------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
   | `{"ok":true, … "request_ip":"…"}`     | Config and database work. Compare `request_ip` with your IP (`curl ifconfig.me`); see below.                                                                                                                                       |
   | `{"ok":false,"error":"unavailable"}`  | Config not found, or the database is unreachable. Move the config file to `web/api/support/_app/config.php` and try again. Check the DB values. Endora's `log` folder contains `[berycode-support]` lines naming any missing keys. |
   | `{"ok":false,"error":"unauthorized"}` | Wrong `key` in the URL.                                                                                                                                                                                                            |
   | 404 page                              | `out/api/` was not uploaded into `web/`.                                                                                                                                                                                           |
   | 500 or blank                          | PHP older than 8.1, or a missing extension (`pdo_mysql`, `curl`, `mbstring`). Check step 1.1 and the `log` folder.                                                                                                                 |

   **`request_ip`:** if it shows your public IP, you're done. If it's a private address (`10.…`, `172.16–31.…`, `192.168.…`, `127.…`), Endora's proxy is hiding visitor IPs, and all customers would share one rate limit. Add `'SUPPORT_CLIENT_IP_HEADER' => 'X-Forwarded-For'` to the config file (or `X-Real-IP`; Endora support can tell you which header they set), upload it again, and reload until your own IP appears.

3. Open `https://berycode.cz/api/support/_app/bootstrap.php`. It must show an empty page, 403 or 404, and never code.

## Step 7 — Schedule the retry job (5 min)

The cron job retries Slack deliveries that failed, and message updates. Run it every **5 minutes**.

- **Endora Fun/Max:** webadmin → **HOSTING → WEB → CRON** → new task with the URL
  `https://berycode.cz/api/support/cron.php?key=<SUPPORT_CRON_SECRET>`, every 5 minutes.
  Optional hardening: add `'SUPPORT_CRON_ALLOWED_IPS' => '62.109.128.59,212.57.32.9,62.109.150.10,212.57.32.162'` (Endora's cron servers) to the config file and upload it again.
- **Endora Free:** at <https://cron-job.org>, create a job for `https://berycode.cz/api/support/cron.php`, every 5 minutes. Under the advanced settings, add the request header `Authorization: Bearer <SUPPORT_CRON_SECRET>`, so the secret isn't in the URL.

After 5–10 minutes, the scheduler's history should show HTTP 200 responses.

## Step 8 — End-to-end test with the test project (10 min)

1. Open <https://berycode.cz/support/?project=berycode-test>. The project field is prefilled.
2. Submit a ticket with **one issue**. You see a `BC-000001` number, and within seconds a message appears in `#support-test`.
3. Submit a ticket with **three issues**. You get one overview message, plus three replies in its thread.
4. On one of the messages click **Přiřadit mně → Začít pracovat → Vyřešit → Znovu otevřít** (Assign → Start → Resolve → Reopen). The same message updates after each click.
5. If possible, have someone who is **not** in `SLACK_ALLOWED_STAFF_USER_IDS` click a button. Only they see a "not allowed" note, and nothing changes.
6. Type a non-existent project into the form. It is rejected with "project not found", and nothing is posted.
7. Check the status:
   - Path A: `npm run support -- status --config=berycode-support-config.php` shows `delivery DELIVERED=…` and `sync IDLE=…`.
   - Path B: in phpMyAdmin run
     `SELECT reference, delivery_state, delivery_last_error, sync_state FROM support_tickets ORDER BY id DESC LIMIT 20;`

If a Slack message doesn't arrive, see [Troubleshooting](#troubleshooting).

## Step 9 — Go live

1. Send each client their link: `https://berycode.cz/support/?project=<their code>`.
2. Keep `berycode-test` for future checks (it's private and not suggested), or set `"active": false` and sync again.
3. Merge and push the `feature/support` branch.

---

## Day-to-day operations

- **New client project:** add it to `backend/config/projects.json`, invite the bot to its channel, then run `projects:sync … --config=berycode-support-config.php` (Path A) or paste `--print-sql` output into phpMyAdmin (Path B).
- **Something stuck:** run `npm run support -- status --config=berycode-support-config.php`. After fixing the cause (e.g. inviting the bot), run `deliveries:requeue --all-failed` with the same `--config`.
- **Deploying code changes:** `npm run build`, then upload `out/` again. If a new file appears in `backend/migrations/`, run `migrate` (or import that file in phpMyAdmin) **before** uploading.
- **Changing a secret or the staff list:** edit `berycode-support-config.php` and upload it again. Slack secrets must also match the Slack app.

## Troubleshooting

| Symptom                                                                                             | Likely cause                                                                 | Fix                                                                                        |
| --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| Form says the request couldn't be saved                                                             | config not loaded or database unreachable                                    | step 6.2; check the `log` folder                                                           |
| Ticket saved but no Slack message; status shows `slack:not_in_channel` or `slack:channel_not_found` | bot not in the channel, or wrong channel ID                                  | `/invite @BeryCode Support` or fix the ID and sync; then `deliveries:requeue --all-failed` |
| `slack:invalid_auth`, `token_revoked`                                                               | wrong or revoked bot token                                                   | copy the token again (reinstall the app if needed), upload the config, requeue             |
| `slack:network:…` again and again                                                                   | the server can't reach slack.com                                             | ask Endora support to allow outbound HTTPS to `slack.com`                                  |
| Slack shows "This app responded with an error" on a click                                           | wrong signing secret, team ID or app ID, or the server can't load the config | recheck those values, upload the config again, test the cron URL                           |
| A colleague's clicks only produce "not allowed"                                                     | their member ID isn't in the allowlist                                       | add it to `SLACK_ALLOWED_STAFF_USER_IDS`, upload the config                                |
| Clicks are saved but the message doesn't change until later                                         | the immediate update failed; cron retries it                                 | check that the cron job runs (step 7)                                                      |
| Many customers get "too many requests"                                                              | `request_ip` is the proxy's address                                          | set `SUPPORT_CLIENT_IP_HEADER` (step 6.2)                                                  |

**Path B equivalents** of `status` and `requeue`, to run in phpMyAdmin:

```sql
-- tickets needing attention
SELECT reference, delivery_state, delivery_attempts, delivery_last_error, sync_state, sync_last_error
FROM support_tickets WHERE delivery_state <> 'DELIVERED' OR sync_state <> 'IDLE' ORDER BY id DESC;

-- retry everything that failed (after fixing the cause)
UPDATE support_tickets SET delivery_state = 'PENDING', delivery_attempts = 0,
  delivery_next_attempt_at = UTC_TIMESTAMP(3), delivery_last_error = NULL WHERE delivery_state = 'FAILED';
UPDATE support_tickets SET sync_state = 'PENDING', sync_attempts = 0,
  sync_next_attempt_at = UTC_TIMESTAMP(3), sync_last_error = NULL WHERE sync_state = 'FAILED';
```

More error codes are explained in [support-setup.md §9](support-setup.md#9-common-delivery-errors).
