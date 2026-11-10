# RequestFlow

[![tests](https://github.com/rossedimare/RequestFlow/actions/workflows/tests.yml/badge.svg)](https://github.com/rossedimare/RequestFlow/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## 1. Overview & Live Demo

RequestFlow is a full-stack service request management application, built as a portfolio project. It combines a Laravel 13 backend with a React 19 and TypeScript frontend, connected through Inertia.js. Authenticated users submit, track and update internal business requests, and a central "My Applications" dashboard acts as the entry point to the available applications.

The project is covered by automated tests (Pest), static analysis and a GitHub Actions CI pipeline, and it runs in the cloud as a Docker image on Render with a Neon PostgreSQL database.

**Live demo:** <https://requestflow-fn05.onrender.com>

- Register with your own email address. New accounts must verify their email before they can use the app, so the verification link is sent to the address you provide. Password reset works the same way.
- The demo runs on Render's free plan, which spins the service down after a period of inactivity. The first request after a pause can take up to a minute while it wakes up.

## 2. Features

- **Request management**: create, view, edit and delete service requests with a title, description, status, priority and timestamps.
- **Status and priority**: four statuses (open, in progress, resolved, closed) and four priorities (low, medium, high, urgent). New requests always start as open.
- **Search, filtering and pagination**: case-insensitive search in the title and description, filters for status and priority, 15 requests per page.
- **Authorization**: everyone signed in can view all requests and create new ones, but only a request's creator can edit or delete it. The server enforces this, and the Edit and Delete buttons are only shown to the creator.
- **Authentication**: registration, login, password reset and email verification through Laravel Fortify. The dashboard and the requests require a verified email address. Unverified users can still update their profile, resend the verification email and delete their account.
- **Email changes**: changing the email address clears the verification and sends a new link. Each user can change their email at most 3 times per hour (name-only updates are never limited). If the email cannot be delivered, the profile is still saved and a warning is shown.
- **User settings**: profile, security (password) and appearance pages.
- **Application launcher**: the "My Applications" dashboard lists applications from a typed registry (`resources/js/config/applications.ts`), which currently contains RequestFlow.

## 3. Screenshots

### My Applications

![My Applications dashboard](docs/screenshots/dashboard.png)

### Requests

![Requests list showing search, status and priority filters](docs/screenshots/requests.png)

### Request Details

![Request details showing the request description, status, priority and creator](docs/screenshots/request-details.png)

## 4. Tech Stack

| Layer               | Technology                                                              |
| ------------------- | ----------------------------------------------------------------------- |
| Backend             | PHP 8.4+, Laravel 13, Laravel Fortify                                   |
| Frontend            | React 19, TypeScript, Inertia.js 3, Tailwind CSS 4, Radix UI components |
| Routing bridge      | Laravel Wayfinder (generated, typed route helpers)                      |
| Build tooling       | Vite+, React Compiler                                                   |
| Database            | SQLite (local development and tests), PostgreSQL on Neon (production)   |
| Email               | Brevo transactional email HTTP API                                      |
| Testing and quality | Pest, PHPStan (Larastan, level 7), Pint                                 |
| CI                  | GitHub Actions                                                          |
| Deployment          | Docker (Apache + PHP 8.5) on Render                                     |

## 5. Architecture & Technical Decisions

- **Inertia instead of a separate API.** Controllers return Inertia responses and React renders the pages, so there is one codebase and one deployment. Wayfinder generates typed route helpers from the PHP routes, so the frontend never hardcodes URLs.
- **Validation and authorization live on the server.** Form Requests validate input (rules shared between create and update), a policy decides who can edit or delete a request, and status and priority are backed enums. The creator (`user_id`) is set from the signed-in user and is never mass-assignable. If a creator deletes their account, their requests stay visible but become read-only.
- **Portable search.** The search compares `LOWER()` on both sides and escapes `LIKE` wildcards, so it behaves the same on SQLite (development and tests) and PostgreSQL (production).
- **Email through Brevo's HTTP API, with no extra package.** A small custom Symfony mail transport uses Laravel's HTTP client. Some hosting plans block outbound SMTP, which is why it uses HTTP. The transport:
    - requires an exact `201` response with a message id, and never follows redirects;
    - reports every failure as a `TransportException` whose message never contains the API key or any email content.
- **Graceful degradation.** If a verification email cannot be sent after a profile email change, the change is kept and the user is told to use the resend link.
- **Database-backed sessions and cache.** Render's free plan has an ephemeral filesystem, so nothing important is stored on disk.
- **Reverse proxy awareness.** Behind Render's TLS proxy the app trusts only the `X-Forwarded-Proto` and `X-Forwarded-Port` headers, from the proxies listed in `TRUSTED_PROXIES`. It does not trust `X-Forwarded-For` or `X-Forwarded-Host`, so clients cannot spoof their IP or host.

## 6. Testing & CI

The test suite uses Pest. It covers requests (CRUD, search, filtering, validation, authorization), authentication, email verification, password reset, profile updates, the rate limit, trusted proxy handling and the Brevo transport. Tests run against an in-memory SQLite database, and the Brevo API is faked, so no test touches your local data or the network.

```bash
composer test         # Pint (check only), PHPStan, then the Pest test suite
php artisan test      # Pest test suite only
npm run check         # frontend lint and format check
npm run types:check   # TypeScript type check
composer ci:check     # the full set of checks that CI runs
```

To fix code style automatically:

```bash
composer lint         # PHP (Pint)
npm run check:fix     # frontend
```

**CI** ([`tests.yml`](.github/workflows/tests.yml)) runs on every push to `main` and on pull requests, using PHP 8.5 and Node.js 22. It installs the project with `composer setup` (which includes a production frontend build) and then runs `composer ci:check`: the frontend lint and format check, the TypeScript check, Pint, PHPStan and the Pest suite. CI does not build the Docker image. Dependabot keeps the GitHub Actions versions up to date.

## 7. Getting Started

### Prerequisites

- PHP 8.4 or later with the `pdo_sqlite` extension (CI and the Docker image use 8.5)
- [Composer](https://getcomposer.org/) 2
- Node.js 22.18 or later (or 24.11 or later) and npm

Local development uses SQLite, so no database server is needed.

### Installation

```bash
git clone https://github.com/rossedimare/RequestFlow
cd RequestFlow
composer setup
```

`composer setup` installs the PHP dependencies, creates `.env` from `.env.example`, generates the application key, runs the database migrations, installs the npm dependencies and builds the frontend assets.

### Running the application

```bash
composer dev
```

This starts the PHP development server and the Vite dev server together. Open <http://localhost:8000> and register. New accounts must verify their email address before they can use the app.

In local development the mail driver is `log`, so no email is sent. Verification and password reset links are written to `storage/logs/laravel.log`. To verify an account locally, copy the full link from the latest verification email in that file and open it in the browser while signed in. Use the same host as `APP_URL` (`http://localhost:8000`), because the link is signed for that host, and open it within 60 minutes.

To send real email, set `MAIL_MAILER=brevo` and the variables described under [Deployment & Configuration](#8-deployment--configuration).

## 8. Deployment & Configuration

The app is deployed as a Docker image on Render's free web service, with a Neon PostgreSQL database and Brevo for email.

### Docker image

The `Dockerfile` is a multi-stage build:

1. A build stage with PHP and Node installs the Composer dependencies (without dev packages) and builds the frontend. PHP is needed there because Wayfinder generates its route helpers by running an Artisan command.
2. The final image is `php:8.5-apache-bookworm` with `pdo_pgsql`. It contains only the application code, the built assets and the dependencies: no Node, no tests and no `.env`.

Apache listens on Render's `PORT` and serves `public/`. At container start, `docker/entrypoint.sh`:

1. checks that the required settings are present and warns about missing production settings;
2. caches the configuration, so the runtime environment variables are used;
3. runs `php artisan migrate --force` with a bounded number of retries, because the database may still be waking up, and exits with a clear error if it never succeeds;
4. starts Apache.

Laravel's built-in `/up` endpoint can be used as the health check path.

### Environment variables

Set these as environment variables on the host. Never commit them or put them in the image.

| Variable                              | Purpose                                                              |
| ------------------------------------- | -------------------------------------------------------------------- |
| `APP_KEY`                             | Application key. Generate one with `php artisan key:generate --show` |
| `APP_URL`                             | Public HTTPS URL of the app                                          |
| `DB_CONNECTION`                       | Must be `pgsql` in the Docker image                                  |
| `DB_URL`                              | PostgreSQL connection string (for example from Neon)                 |
| `DB_SSLMODE`                          | `require` for Neon                                                   |
| `TRUSTED_PROXIES`                     | `*` behind Render's proxy                                            |
| `SESSION_SECURE_COOKIE`               | `true`, so session cookies carry the `Secure` flag                   |
| `MAIL_MAILER`                         | `brevo`                                                              |
| `BREVO_API_KEY`                       | Brevo API key (secret)                                               |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender address (verified in Brevo) and name                          |

The image already defaults `APP_ENV=production`, `APP_DEBUG=false` and `LOG_CHANNEL=stderr`, so logs appear in the host's log viewer. `MIGRATE_MAX_ATTEMPTS` and `MIGRATE_RETRY_DELAY` optionally tune the migration retries.

## 9. License

This project is licensed under the [MIT License](LICENSE).
