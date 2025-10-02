# SG Jobs WordPress Plugin

SG Jobs bridges bexio delivery notes with a scheduling cockpit and mobile job execution. Disposition keeps working inside WordPress and Apple Calendar while installers receive magic links that open a mobile job sheet PWA. Every status update synchronises to CalDAV calendars so the office, the field team and Apple Calendar stay aligned.

## Key features

- **Dispo board** with React/FullCalendar week and day views, blocker overlay and live delivery note import.
- **Job Sheet PWA** for installers without WordPress accounts using short-lived JWT magic links.
- **CalDAV sync** that mirrors status emojis 🔴/✅/🧾/💰 in team calendars while keeping structured notes consistent.
- **Live bexio reads** with retry/backoff, Action Scheduler queues and audit logging for every state change.
- **Payment sync** that marks jobs 💰 when the linked bexio invoice has been paid.

## Getting started

### 1. Install dependencies

```bash
composer install
npm install
npm run build
```

### 2. Configure environment

Copy `config/example.env` to `.env` and provide production credentials. Keys must match the WordPress settings page.

### 3. Activate the plugin

Upload the plugin folder to `wp-content/plugins`, then activate **SG Jobs** in the WordPress admin. During activation database tables are created and required cron hooks are registered.

### 4. Configure settings

Navigate to **Settings → SG Jobs** and enter:

- bexio base URL and API token.
- CalDAV base URL, username and password for the service account.
- Team definitions with CalDAV principal, execution calendar path and blocker calendar path.
- JWT secret (32+ chars) and expiry days for magic links.

### 5. Place shortcodes

Add the shortcodes to appropriate WordPress pages:

- `[sg_jobs_board]` for the disposition board (requires the `sgjobs_manage` capability).
- `[sg_jobs_mine]` for the “My jobs today” PWA view (restricted to users with a team magic link).

### 6. Enable cron jobs

Ensure WordPress cron or a system cron triggers `wp cron event run sg_jobs_bexio_payment_sync` at least every 15 minutes. Optional: schedule the CalDAV watcher if external calendar edits must be reconciled.

### 7. Connect Apple Calendar

Subscribe to each team execution calendar and its blocker calendar in Apple Calendar. Jobs will show the emoji status in the title and the structured notes block in the description.

## Security notes

- Magic link JWTs are signed server-side only and scoped to job or team access.
- All REST endpoints validate capabilities or JWT scopes and enforce idempotency via the `Idempotency-Key` header.
- No API secrets are ever exposed to the browser bundle; the client communicates with WordPress REST endpoints exclusively.

## Acceptance checklist

- Installer can mark ✅ within ten seconds from opening the job sheet.
- Disposition sees updates immediately in the board and Apple Calendar.
- Phone numbers are taken strictly from the delivery note or must be supplied manually when missing.
- All positions of a delivery note are displayed on the job sheet and board preview without filtering.

## Screenshots & mockups

Mockups illustrating the primary workflows are available in [`docs/mockups`](docs/mockups):

- Dispo board week view with blocker overlay (`board-week.svg`).
- Apple Calendar event layout with emoji status (`apple-calendar.svg`).
- Mobile job sheet with phones, address, positions and ✅ action (`jobsheet-mobile.svg`).

## Roadmap

- Public online booking requests with calendar availability validation.
- File uploads and customer signatures via Nextcloud signed URLs.
- Optional CalDAV hard-lock plugin to prevent out-of-window edits.

## License

Released under the [MIT License](LICENSE).
