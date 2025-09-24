# CallHub

CallHub is a Laravel 11 starter for call analytics, QA, and transcription workflows. The project is structured around dedicated domain modules and repository interfaces to support future adapter-driven integrations.

## Getting Started

1. **Install dependencies**
   ```bash
   composer install
   npm install
   ```
2. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   _Production installs can alternatively run the browser installer at `/install` to generate the `.env` file._
3. **Set permissions for storage**
   ```bash
   php artisan storage:link
   chmod -R 775 storage bootstrap/cache
   ```
4. **Run database migrations**
   ```bash
   php artisan migrate
   ```
5. **Serve the application**
   ```bash
   php artisan serve
   ```
6. **Schedule and queue workers (cPanel friendly)**
   - Queue worker cron: `* * * * * cd /path/to/callhub && php artisan queue:work --queue=default --sleep=3 --tries=3 >> /path/to/callhub/storage/logs/queue-worker.log 2>&1`
   - Schedule runner cron: `* * * * * cd /path/to/callhub && php artisan schedule:run >> /path/to/callhub/storage/logs/scheduler.log 2>&1`

## Installer

- Navigate to `/install` after uploading the project to run the four-step wizard: system checks, database connection & migrations,
  administrator provisioning, and application configuration.
- The wizard writes `.env`, seeds the default provider, and places `storage/installed.flag`. Subsequent requests to `/install`
  respond with `403` until the flag is removed manually.
- Final step surfaces production-ready cPanel Cron commands for the scheduler and queue worker. Update the project path before
  saving the jobs in your hosting panel.

## Authentication & RBAC

- Email and password authentication is provided via `/auth/login` with password reset flows under `/auth/forgot-password` and `/auth/reset-password/{token}`.
- Optional email OTP enforcement can be toggled with `CALLHUB_EMAIL_OTP` (default `false`) and expiration configured via `CALLHUB_EMAIL_OTP_EXPIRY` (minutes).
- Role-aware middleware aliases (`role:admin`, `role:qa`, `role:lead`, `role:readonly`) gate sensitive routes; policies guard access to recordings, transcripts, and QA scoring data.
- OTP codes are single-use, expire quickly, and are delivered via the default mail channel.

## Settings

- Admins can configure general metadata, telephony provider credentials, storage backends, transcription engines, notification channels, and privacy retention windows at `/admin/settings/general`. Values persist to the `settings` table via `SettingsService`, refresh runtime configuration, and mask stored secrets.
- The global `setting('app.name')` helper resolves configuration with database values first and falls back to `.env`/config, simplifying consumption inside Blade templates, services, and jobs.

## Modules

Domains are available under `app/Domains/*` with corresponding repository interfaces in `app/Repositories/Contracts` and Eloquent stubs in `app/Repositories/Eloquent`.

## Schema Diagram

```
users (id, name, email*, role, status, password)
  ├─< calls.agent_id
  └─< qa_scores.scored_by

providers (id, name*)
  └─< calls.provider_id

calls (id, provider_call_id*, provider_id, agent_id, direction, from_number, to_number, started_at)
  ├─< recordings.call_id
  └─< qa_scores.call_id

recordings (id, call_id, storage_backend, status)
  └─1 transcripts.recording_id

transcripts (id, recording_id, engine, status)

qa_scores (id, call_id, scored_by)

jobs (id, type, status, run_at)

audits (id, user_id, subject_type, subject_id)
```

The database seeder provisions a default administrator account (`CALLHUB_ADMIN_EMAIL`) and a placeholder provider for integration testing.
