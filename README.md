# SG Jobs WordPress Plugin

SG Jobs bridges bexio delivery notes with a scheduling cockpit and mobile job execution. Disposition keeps working inside WordPress and Apple Calendar while installers receive magic links that open a mobile job sheet PWA. Every status update synchronises to CalDAV calendars so the office, the field team and Apple Calendar stay aligned.

## Key features

- **Dispo board** with React/FullCalendar week and day views, blocker overlay and live delivery note import.
- **Job Sheet PWA** for installers without WordPress accounts using short-lived JWT magic links.
- **CalDAV sync** that mirrors status emojis 🔴/✅/🧾/💰 in team calendars while keeping structured notes consistent.
- **Live bexio reads** with retry/backoff, Action Scheduler queues and audit logging for every state change.
- **Payment sync** that marks jobs 💰 when the linked bexio invoice has been paid.

## Onboarding

1. **Plugin aktivieren** – Lade das Plugin nach `wp-content/plugins` und aktiviere **SG Jobs** im WordPress-Backend. Aktivierung legt benötigte Tabellen und Cronjobs an.
2. **JWT Secret setzen** – Navigiere zu **Einstellungen → SG Jobs** und hinterlege ein mindestens 32 Zeichen langes Secret für Magic Links. Das Secret wird ausschließlich serverseitig verwendet und darf nicht in Repos oder Dokus landen.
3. **CalDAV Basisdaten eintragen** – Trage Basis-URL, Service-User und Passwort für den CalDAV-Zugang ein. Optionale Secrets können über `wp-config.php` oder ENV-Variablen gesetzt werden.
4. **Kalender teilen** – Teile die Team-Kalender im Nextcloud/CalDAV Backend vom Owner an den Service-User (z. B. `caldav-sync`) mit Bearbeitungsrechten.
5. **Gemountete Pfade übernehmen** – Verwende die vom Service-User gemounteten Pfade (`/remote.php/dav/calendars/caldav-sync/...`) für Ausführungs- und Blocker-Kalender, damit Cronjobs und Clients konsistent zugreifen können.
6. **Teams eintragen** – Erfasse in den Einstellungen jedes Team mit CalDAV-Principal, Execution- und Blocker-Slug. Lege die Slugs exakt wie in Nextcloud an; ein Hinweis im UI erinnert daran.
7. **Healthcheck ausführen** – Nutze den Healthcheck im Admin („Test CalDAV/Bexio“), um Anmelde- und Freigabeprobleme zu erkennen. Ergebnisse werden farblich nach HTTP-Status codiert.
8. **Dispo-Board testen** – Rufe das Dispo-Board mit dem Shortcode `[sg_jobs_board]` auf (erfordert Capability `sgjobs_manage`) und führe einen Testlauf durch. Prüfe außerdem das Installer-Board über `[sg_jobs_mine]`.

## Deployment package

WordPress must see `vendor/autoload.php` inside the plugin directory. A plain GitHub ZIP does not contain Composer dependencies, so you need to build a release package first. Every push to `main` produces a ready-to-install archive via the **Build release package** GitHub workflow, and you can also build it yourself when needed.

### Option A: Download the automated package

1. Open [GitHub → Actions → Build release package](https://github.com/sg-on-wordpress/sg-jobs/actions/workflows/release-package.yml).
2. Pick the latest successful run (triggered automatically on pushes to `main` or manually via “Run workflow”).
3. Download the `sg-jobs-build.zip` artifact and upload it through **Plugins → Install → Upload Plugin** in WordPress.

### Option B: Build locally and upload the ZIP

1. Download or clone this repository to your computer.
2. Run the dependency and build steps inside the plugin directory:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

3. Create an archive that keeps the generated `vendor/` folder:

   ```bash
   zip -r sg-jobs.zip . -x '.git/*' '.github/*' 'node_modules/*' 'tests/*'
   ```

4. Upload `sg-jobs.zip` through **Plugins → Install → Upload Plugin** in WordPress.

The repository also ships a `make zip` target that performs steps 2–3 and places the result in `artifact/sg-jobs-build.zip`. This is the same process the GitHub workflow runs before publishing the artifact.

### Option C: Install dependencies directly on the server

If you have SSH access to the WordPress host, copy the repository to `wp-content/plugins/sg-jobs`, then run:

```bash
cd wp-content/plugins/sg-jobs
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

This installs the Composer autoloader and rebuilds the production assets in place.

After either option, activate **SG Jobs** from the WordPress admin.

## Development setup

```bash
composer install
npm install
npm run build
```

Konfigurationen für bexio, CalDAV und JWT werden über die WordPress-Settings oder Umgebungsvariablen vorgenommen. Token oder Passwörter gehören nie ins Repository.

## Health endpoint

Administrators can call `GET /wp-json/sgjobs/v1/health` to verify the installation. The response reports:

- JWT configuration and CalDAV connectivity per team (existing behaviour).
- Bexio connectivity by running a lightweight authenticated API request.
- Action Scheduler backlog counts for the `sg-jobs` queue, including failed jobs.
- Cron status for the `sg_jobs_bexio_payment_sync` event with next run timestamps.

Any failing check sets `ok` to `false` and surfaces translated error messages to highlight the corrective action.

## Security notes

- Magic link JWTs are signed server-side only and scoped to job or team access.
- All REST endpoints validate capabilities or JWT scopes and enforce idempotency via the `Idempotency-Key` header.
- No API secrets are ever exposed to the browser bundle; the client communicates with WordPress REST endpoints exclusively.

## Troubleshooting

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| 207/401/403/404 bei PROPFIND | Passwort, Freigabe oder Slug falsch | Passwort prüfen, Kalender teilen und den Slug exakt aus Nextcloud übernehmen |
| `count(): Argument #1 ($value) must be of type Countable` | Option war noch als JSON-String gespeichert | Migration/Normalizer ausführen und Option erneut speichern |
| `Too few arguments to function JwtService` | Älterer Konstruktor aus Legacy-Version | Aktuelle Plugin-Dateien deployen – neuer Konstruktor akzeptiert optionale Parameter |

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

## Service user calendars

In vielen Installationen greift ein dedizierter Service-User (z. B. `caldav-sync`) auf die Kalender zu. Nextcloud mountet freigegebene Kalender dieses Users unter `/remote.php/dav/calendars/<service-user>/…`. Diese Pfade müssen sowohl für den Ausführungs- als auch für den Blocker-Kalender verwendet werden.

Beispielkonfiguration für ein Team **Montage** mit Service-User:

```
Name: Montage
Principal: https://cloud.example.com/remote.php/dav/principals/users/caldav-sync/
Execution: remote.php/dav/calendars/caldav-sync/montage/
Blocker: remote.php/dav/calendars/caldav-sync/montage-blocker/
```

Der Principal verweist auf den Service-User, die Kalenderpfade verwenden die gemounteten Freigaben. Dadurch funktionieren auch Zugriffe über CalDAV-Clients und Cronjobs ohne interaktive Anmeldung.

## License

Released under the [MIT License](LICENSE).
