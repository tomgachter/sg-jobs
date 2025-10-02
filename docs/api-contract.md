# API Contract

Base URL: `/wp-json/sgjobs/v1`

## Authentication

- **Dispo/Admin**: WordPress login with capability `sgjobs_manage`.
- **Installer**: `Authorization: Bearer <MagicJWT>` created by `JwtService`.

## Endpoints

### `POST /jobs`

Creates a job by importing a bexio delivery note.

Request body:
```json
{
  "delivery_note_nr": "5009",
  "team_id": 1,
  "starts_at": "2025-10-07T08:00:00+02:00",
  "ends_at": "2025-10-07T10:00:00+02:00",
  "location_city": "Zürich",
  "notes": "Hintereingang, 3. OG",
  "phones": ["+41 79 123 45 67"]
}
```
Response 201:
```json
{
  "job_id": 42,
  "public_job_url": "https://example.com/jobs/<token>",
  "caldav_event_uid": "sgjobs-42-evt64f..."
}
```
Errors: `400` missing phone, `404` delivery note missing, `409` idempotency violation.

### `GET /jobs?team_id=&date=`

Returns jobs and blockers for the board. Response contains `jobs` and `blockers` arrays.

### `GET /jobs/{id}`

Returns job details including positions, phones and structured address. Installer tokens and Dispo can access.

### `POST /jobs/{id}/done`

Marks a job as done. Installer only.

Request body:
```json
{ "comment": "Serien: QK-123456" }
```
Response:
```json
{ "status": "done" }
```

### `POST /jobs/{id}/billable`

Marks a job as billable. Requires Dispo capability.

Response:
```json
{ "status": "billable" }
```

### `POST /sync/bexio-payments`

Webhook/cron endpoint that triggers payment sync. Protected by application password or WP cron.

## Errors

Errors follow the structure:
```json
{
  "error": {
    "code": "sg_jobs_delivery_note_missing",
    "message": "Delivery note not found in bexio."
  },
  "retry_after": 15
}
```

## Idempotency

Write endpoints accept an `Idempotency-Key` header. Responses for duplicate keys are served from cache with HTTP 200.
