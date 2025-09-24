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
   - Domain job worker cron: `* * * * * cd /path/to/callhub && php artisan jobs:run --once --max=10 >> /path/to/callhub/storage/logs/domain-worker.log 2>&1`
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

## Telephony Provider Client

- `TelephonyClientInterface` models call retrieval and recording URL lookups. The default `GenericRestClient` honours admin-configured base URLs, headers, query parameters, pagination size, and rate-limit guidance with exponential backoff on `429`/`5xx` responses.
- Admins can map remote payload fields to CallHub attributes, customise request metadata, and run a live preview from `/admin/providers/telephony/mapping`. Preview results surface one page of calls and any continuation cursor without exposing shared secrets in logs.

## Call Ingestion

- The `poll:calls` Artisan command queries the configured telephony API for the trailing poll window, honours saved cursors, and upserts calls and associated recordings without duplication.
- Successful polling queues `DownloadRecordingJob` records on the `recordings` queue and records job metadata in the domain `jobs` table; the scheduler triggers this command every five minutes by default.
- Health checks at `/health/status` expose the timestamp of the last successful poll for observability dashboards.

## Recording Storage

- `StorageService` orchestrates Local and S3 backends through dedicated drivers. Local recordings are written under `storage/app/recordings/YYYY/MM/DD/<provider_call_id>.mp3` while the S3 driver keeps objects private and issues temporary signed URLs for playback controllers.
- A migration helper moves existing local recordings to S3 (and cleans up the source copy) when administrators switch the preferred backend—no additional code changes required.
- Recording downloads validate remote payload size, compute SHA-256 checksums, optionally transcode audio to MP3 via FFmpeg (`FFMPEG_BINARY`), and persist lightweight waveform JSON for UI rendering before enqueueing downstream transcription work.

## Recording Library

- `/admin/recordings` lists stored recordings with server-side filters for date range, agent, direction, disposition, queue membership, transcript readiness, and QA scoring coverage while masking phone numbers when privacy rules demand it.
- The table surfaces call start times, agent assignments, duration, storage backend, and transcript/QA badges with pagination defaults of 25/50/100 rows.
- Operators can export the filtered dataset to CSV via the `Export CSV` action; privacy masking is enforced in the export when enabled through Admin → Settings → Privacy & Retention.

## Transcription Pipeline

- `TranscribeRecordingJob` converts stored audio into transcripts using the configured Whisper API or local `whisper.cpp` binary through the `TranscriptionService`, capturing full-text output plus time-aligned segments for the QA interface.
- Admin settings expose the Whisper API timeout, CLI binary/model/threads/timeout inputs, and a daily transcription minutes cap to throttle spending; language preferences follow the saved default and persist per transcript.
- Domain jobs record success and retry metadata, while the worker backfills transcripts into the `transcripts` table with `processing`/`ready` status tracking for observability dashboards.

## Admin Dashboard & Health

- `/admin` now surfaces an operations overview with rolling 24-hour metrics for call ingestion, recording downloads, transcription completions, and job failures alongside queue depth and storage utilisation.
- Alerts raise when local disk consumption exceeds 80% or when recent job failures require investigation; admins can drill into the bundled log viewer at `/admin/logs`.
- Storage backend usage is summarised per driver, helping operators identify when to migrate recordings to S3 and ensuring health dashboards reflect the most recent telephony poll timestamp.

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
