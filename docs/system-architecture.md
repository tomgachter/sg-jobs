# System Architecture

SG Jobs orchestrates bexio, WordPress and Nextcloud to provide a unified installation workflow.

## Components

- **WordPress plugin**: Hosts REST API, queues and UI shortcodes.
- **bexio API**: Provides delivery notes and invoice payment status.
- **Nextcloud CalDAV**: Stores team and blocker calendars that sync to Apple Calendar.
- **Magic link PWA**: Served from WordPress and authenticated using JWT.

## Sequence: Delivery note → Job

1. Dispo selects “Import from bexio” in the board.
2. WordPress calls `BexioService.getDeliveryNoteByNumber` and loads all positions.
3. Job data is stored in the `sg_jobs` tables and a magic link JWT is generated.
4. `CalendarService.createOrUpdateEvent` writes a VEVENT via CalDAV with the structured notes block.
5. Apple Calendar receives the change and renders the 🔴 job.

## Sequence: Installer marks ✅

1. Installer opens the magic link, the JWT is validated server side.
2. `JobsService.markDone` updates the job status and stores the installer comment.
3. `CalendarService.createOrUpdateEvent` rewrites the VEVENT with a ✅ title and updated description block.
4. Action Scheduler queue retries CalDAV writes if the server responds with a transient failure.
5. Apple Calendar shows the updated emoji immediately.

## Sequence: Payment sync → 💰

1. Cron fires `sg_jobs_bexio_payment_sync` every 15 minutes.
2. `BexioPaymentSync` checks billable jobs against bexio invoices.
3. Paid invoices trigger `JobsService.markPaid` which updates the job and CalDAV event.
4. Apple Calendar reflects 💰 and the audit log stores the automated action.

## Data stores

- WordPress database tables `sg_jobs*` for job metadata and audit trail.
- CalDAV collections for team execution and blocker calendars.
- Action Scheduler tables for asynchronous tasks.

## Integration resilience

- Exponential backoff and respect for `Retry-After` headers on bexio requests.
- CalDAV writes are idempotent; the VEVENT UID is stored in the job table.
- REST API write endpoints support an `Idempotency-Key` header to deduplicate retries from the board and PWA.
