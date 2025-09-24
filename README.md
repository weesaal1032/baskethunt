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
   - Queue worker cron: `* * * * * cd /path/to/callhub && php artisan queue:work --stop-when-empty`
   - Schedule runner cron: `* * * * * cd /path/to/callhub && php artisan schedule:run`

## Authentication & RBAC

- Email and password authentication is provided via `/auth/login` with password reset flows under `/auth/forgot-password` and `/auth/reset-password/{token}`.
- Optional email OTP enforcement can be toggled with `CALLHUB_EMAIL_OTP` (default `false`) and expiration configured via `CALLHUB_EMAIL_OTP_EXPIRY` (minutes).
- Role-aware middleware aliases (`role:admin`, `role:qa`, `role:lead`, `role:readonly`) gate sensitive routes; policies guard access to recordings, transcripts, and QA scoring data.
- OTP codes are single-use, expire quickly, and are delivered via the default mail channel.

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
