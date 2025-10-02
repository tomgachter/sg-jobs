# Contributing to SG Jobs

Thank you for investing time in improving SG Jobs. The plugin orchestrates several external systems and we rely on deterministic processes to keep deployments safe.

## Development workflow

1. Fork the repository and clone your fork.
2. Install dependencies via `composer install` and `npm install`.
3. Copy `config/example.env` to `.env` and adjust the credentials for your sandbox services.
4. Run `npm run dev` to watch the React board and PWA bundles while developing.
5. Run `composer analyse` and `npm run typecheck` before opening a pull request.
6. Document behaviour changes in `CHANGELOG.md` under the **Unreleased** section.

## Coding standards

- PHP must follow PSR-12 and pass PHPStan level 7 checks.
- TypeScript follows the Airbnb + React rule set. Prefer functional components.
- Avoid direct `var_dump`, use the logger instead.
- Handle exceptions and propagate meaningful WP_Error objects from REST controllers.

## Commit messages

Use present tense and include the scope, e.g. `Add CalDAV retry backoff`. Reference Jira tickets where applicable.

## Security

The repository contains integration code for bexio and CalDAV. Never hardcode credentials. When sharing logs or screenshots, redact tokens and customer data.

## Reporting issues

Create a GitHub issue that includes reproduction steps, expected vs actual outcome and relevant logs. Attach screenshots or HAR files when the bug relates to the board or PWA.

We appreciate every contribution that helps installers complete jobs faster!
