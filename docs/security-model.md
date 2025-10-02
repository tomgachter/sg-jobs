# Security Model

## Authentication flows

- **Disposition/Admin** authenticate with standard WordPress credentials and must have capability `sgjobs_manage`.
- **Installers** receive magic links encoded as JWT. Tokens contain claims `sub` (job or team scope), `exp`, `nonce` and optional `team_id`.
- Magic links are single URL endpoints served via HTTPS only and hashed server side (`job_token_hash`).

## Authorization

- Board endpoints require `sgjobs_manage`.
- Job sheet endpoints validate the JWT and restrict actions to the scoped job or team.
- Team scoped tokens can fetch `/my-jobs` for a given date; job scoped tokens can only load and complete one job.

## JWT settings

- Algorithm: HS256 with 32+ character secret stored server-side only.
- Default expiry: 14 days.
- Device binding optional: the first open stores a device token and subsequent requests ask for a 4-digit PIN (out of scope for initial release but interface ready).

## Data protection

- Phone numbers are never fetched from sales orders; they come directly from the delivery note or manual input.
- Secrets (bexio token, CalDAV credentials, JWT secret) live in `.env` / WP options and are never exposed in frontend bundles.
- Audit log tracks `actor`, `action` and payload metadata without persisting secrets or raw tokens.

## Transport security

- All endpoints must be served via HTTPS. Service worker refuses to install on insecure origins.
- CalDAV credentials belong to a service account with least privilege access only to team calendars.

## Logging

- Logger redacts tokens before writing to PHP error log.
- Debug logging is disabled unless `WP_DEBUG` is true. Production logs use Action Scheduler hooks for failure reporting.

## Threat considerations

- Replay of magic link tokens is mitigated by expiration and optional device binding.
- REST write endpoints use idempotency keys to avoid duplicate CalDAV events on retries.
- Rate limits from bexio are handled with exponential backoff to prevent bans.
