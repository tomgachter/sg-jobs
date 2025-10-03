# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2025-10-03
### Added
- Admin health diagnostics endpoint that checks bexio connectivity, CalDAV calendars, Action Scheduler backlogs, payment sync cron status and JWT secret configuration in a single REST call. 
- Operations runbook, security model and system architecture documentation that outline cron schedules, release packaging, troubleshooting steps, authentication flows and data integrations.

### Changed
- Jobs board shortcode registers board assets relative to the plugin main file and only enqueues the stylesheet when present so zipped build artifacts load correctly across environments.
- Build and release automation packages production artifacts through dedicated GitHub workflows and a Makefile target to standardise CI and tagged releases.

### Fixed
- npm lockfile updated to reflect the declared dependencies so CI installs use consistent versions.

## [0.1.0] - 2024-06-01
### Added
- Dispo board with week/day view, delivery note import dialog and blocker overlay.
- CalDAV event sync that reflects status emojis in titles and keeps structured notes.
- Mobile job sheet PWA with magic links, “My jobs today” list and offline ✅ Done queue.
- Payment sync that marks jobs as 💰 when linked invoices are paid.
- Admin settings UI, documentation set, mockups and API Postman collection.
