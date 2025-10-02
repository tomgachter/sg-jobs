# Data Model

## Tables

### `wp_sg_jobs`

Stores job metadata sourced from bexio delivery notes.

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | BIGINT | Primary key |
| `delivery_note_id` | BIGINT | bexio identifier |
| `delivery_note_nr` | VARCHAR(64) | search key |
| `sales_order_nr` | VARCHAR(64) | optional reference |
| `team_id` | BIGINT | FK → `wp_sg_teams.id` |
| `starts_at`, `ends_at` | DATETIME UTC | scheduled slot |
| `tz` | VARCHAR | original timezone |
| `location_city` | VARCHAR | used in calendar title |
| `address_line` | TEXT | formatted street |
| `phones_json` | LONGTEXT | JSON array from delivery note or manual input |
| `status` | ENUM | `open`, `done`, `billable`, `paid` |
| `caldav_event_uid` | VARCHAR | VEVENT UID |
| `job_token_hash` | VARCHAR | sha256 of magic link token |
| `notes` | TEXT | installer/office notes |
| `created_by`, `updated_by` | BIGINT | WP user IDs |
| `created_at`, `updated_at` | DATETIME | audit |

### `wp_sg_job_positions`

All delivery note positions without filtering.

| Column | Type |
| ------ | ---- |
| `job_id` | FK |
| `bexio_position_id` | BIGINT |
| `article_no` | VARCHAR |
| `title` | VARCHAR |
| `description` | TEXT |
| `qty` | DECIMAL |
| `unit` | VARCHAR |
| `work_type` | ENUM |
| `sort` | INT |

### `wp_sg_teams`

Defines the CalDAV integration for each crew.

| Column | Notes |
| ------ | ----- |
| `name` | Display name |
| `caldav_principal` | Nextcloud principal identifier |
| `team_calendar_path` | Full CalDAV collection URL |
| `blocker_calendar_path` | Gray overlay calendar |

### `wp_sg_users_teams`

Future mapping between WP users and teams for board filtering.

### `wp_sg_job_audit`

Immutable audit trail of actions performed on jobs.

| Column | Notes |
| ------ | ----- |
| `actor` | User ID or magic link subject |
| `action` | Status change or webhook |
| `payload_json` | Additional metadata |
| `created_at` | Timestamp |

## Relationships

- `sg_job_positions.job_id` → `sg_jobs.id`
- `sg_jobs.team_id` → `sg_teams.id`
- `sg_job_audit.job_id` → `sg_jobs.id`
- `sg_users_teams.team_id` → `sg_teams.id`

## Indexing

- Delivery note number and CalDAV UID for fast lookups.
- Composite PK on `sg_users_teams` for uniqueness.
- Foreign keys maintain referential integrity for cascading deletes.
