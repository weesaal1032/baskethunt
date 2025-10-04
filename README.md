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
6. **Schedule and queue workers**
   - Minimum cron cadence: `*/5 * * * * php /home/USER/public_html/artisan schedule:run`
   - Domain job worker cron: `*/5 * * * * php /home/USER/public_html/artisan jobs:run --once --max=25`
   - Optional queue worker: `*/5 * * * * php /home/USER/public_html/artisan queue:work --stop-when-empty`
   - See [DEPLOYING_ON_CPANEL.md](DEPLOYING_ON_CPANEL.md) for vendor bundle packaging and log redirection examples tailored to shared hosting.

## Installer

- Navigate to `/install` after uploading the project to run the four-step wizard: system checks, database connection & migrations,
  administrator provisioning, and application configuration.
- The wizard writes `.env`, seeds the default provider, and places `storage/installed.flag`. Subsequent requests to `/install`
  respond with `403` until the flag is removed manually.
- To re-enable the installer intentionally, run `php artisan installer:unlock` (use `--force` for unattended scripts) which
  deletes `storage/installed.flag` after confirmation.
- Final step surfaces production-ready cPanel Cron commands for the scheduler and queue worker. Update the project path before
  saving the jobs in your hosting panel or reference [DEPLOYING_ON_CPANEL.md](DEPLOYING_ON_CPANEL.md) for copy-paste entries.

## cPanel Deployment

- Follow [DEPLOYING_ON_CPANEL.md](DEPLOYING_ON_CPANEL.md) to package a vendor-bundled release, upload it under `public_html`, set writable permissions, and register cron jobs on shared hosting.
- The deployment guide also covers S3-compatible storage, Whisper transcription modes, and telephony provider configuration to complete post-install hardening.
- After cron executes, `/health/status` exposes `scheduler_last_ran_at` and `jobs_runner_last_ran_at` timestamps so you can confirm the hosting panel is invoking tasks on schedule.

## Authentication & RBAC

- Email and password authentication is provided via `/auth/login` with password reset flows under `/auth/forgot-password` and `/auth/reset-password/{token}`.
- Optional email OTP enforcement can be toggled with `CALLHUB_EMAIL_OTP` (default `false`) and expiration configured via `CALLHUB_EMAIL_OTP_EXPIRY` (minutes).
- Role-aware middleware aliases (`role:admin`, `role:qa`, `role:lead`, `role:readonly`) gate sensitive routes; policies guard access to recordings, transcripts, and QA scoring data.
- OTP codes are single-use, expire quickly, and are delivered via the default mail channel.

## Security Hardening & Pen Test Checklist

- **PII masking** – phone numbers are rendered with only the first two and last three digits visible when privacy rules enable masking (`Privacy & Retention` settings).
- **Guarded audio streaming** – `/admin/recordings/{recording}/audio` enforces RBAC, requires a temporary signed URL, and returns one-minute S3 pre-signed redirects so buckets remain private.
- **Transcript access** – recording detail views require authenticated, active users with authorised roles; inactive users are denied.
- **Session protections** – Laravel CSRF tokens secure every form post; controllers validate all user input server-side and escape output via Blade.
- **Authentication controls** – login attempts are throttled via the `login` rate limiter (configurable with `CALLHUB_LOGIN_ATTEMPTS` / `CALLHUB_LOGIN_DECAY`) and optional email OTP provides two-factor coverage.
- **Audit trail** – viewing recordings, streaming audio, and drafting/submitting QA scores emit structured entries in the `audits` table for compliance investigations.
- **Secrets & storage** – S3 objects are uploaded with `private` ACL, temporary URLs are used for playback, and `.env` stores all credentials.
- **Operational review** – README and installer both call out cron jobs; admins should review `/admin` health widgets for failure spikes, disk alerts, and verify `storage/logs/laravel.log` remains access-controlled.

## Settings

- Admins can configure general metadata, telephony provider credentials, storage backends, transcription engines, notification channels, and privacy retention windows at `/admin/settings/general`. Values persist to the `settings` table via `SettingsService`, refresh runtime configuration, and mask stored secrets.
- The global `setting('app.name')` helper resolves configuration with database values first and falls back to `.env`/config, simplifying consumption inside Blade templates, services, and jobs.

## Privacy & Retention

- Retention windows are defined by **Retention (months)** and the **Deletion grace period (days)** fields on the Privacy & Retention tab. `CALLHUB_RETENTION_MONTHS` and `CALLHUB_DELETION_GRACE_DAYS` seed the defaults for fresh installs.
- `php artisan privacy:enforce-retention --dry-run` simulates the nightly cleanup, logging which recordings/transcripts would be soft deleted or purged without mutating data. Omit `--dry-run` to apply changes immediately.
- The scheduler runs `privacy:enforce-retention` at 02:15 server time each day, soft-deleting recordings older than the retention window and permanently purging those beyond the grace period while removing storage objects and emitting audit entries.
- Fulfil “delete my data” requests with `php artisan privacy:delete-call {callId}` (optionally `--dry-run`). The command removes call metadata, recordings, transcripts, QA scores via cascades, and storage assets while logging to the audits ledger.

## Telephony Provider Client

- `TelephonyClientInterface` models call retrieval and recording URL lookups. The default `GenericRestClient` honours admin-configured base URLs, headers, query parameters, pagination size, and rate-limit guidance with exponential backoff on `429`/`5xx` responses.
- Admins can map remote payload fields to CallHub attributes, customise request metadata, and run a live preview from `/admin/providers/telephony/mapping`. Preview results surface one page of calls and any continuation cursor without exposing shared secrets in logs.

## Exports & Internal API

- Recording library filters power both the on-screen table and CSV exports at `/admin/recordings/export`; the same filter payload can be reused to download call metadata via `/admin/calls/export` which now includes disposition, storage backend, and QA status summaries.
- QA reports continue to offer `/admin/qa/export` for rubric-level data while the new `/admin/help` page documents all CSV endpoints and JWT authentication requirements for downstream operators.
- Internal business-intelligence APIs are exposed under `/api/internal/*` and secured with short-lived HS256 JWTs signed by `CALLHUB_INTERNAL_API_SECRET`. Available resources include `/api/internal/calls` (call + recording context) and `/api/internal/qa-scores` (submitted QA history) and each response includes pagination metadata plus applied filters for auditability.

## Call Ingestion

- The `poll:calls` Artisan command queries the configured telephony API for the trailing poll window, honours saved cursors, and upserts calls and associated recordings without duplication.
- Successful polling queues `DownloadRecordingJob` records on the `recordings` queue and records job metadata in the domain `jobs` table; the scheduler triggers this command every five minutes by default.
- Health checks at `/health/status` expose the timestamp of the last successful poll alongside scheduler and job runner heartbeats so observability dashboards can confirm cron execution.

## Recording Storage

- `StorageService` orchestrates Local and S3 backends through dedicated drivers. Local recordings are written under `storage/app/recordings/YYYY/MM/DD/<provider_call_id>.mp3` while the S3 driver keeps objects private and issues temporary signed URLs for playback controllers.
- A migration helper moves existing local recordings to S3 (and cleans up the source copy) when administrators switch the preferred backend—no additional code changes required.
- Recording downloads validate remote payload size, compute SHA-256 checksums, optionally transcode audio to MP3 via FFmpeg (`FFMPEG_BINARY`), and persist lightweight waveform JSON for UI rendering before enqueueing downstream transcription work.

## Recording Library

- `/admin/recordings` lists stored recordings with server-side filters for date range, agent, direction, disposition, queue membership, transcript readiness, and QA scoring coverage while masking phone numbers when privacy rules demand it.
- The table surfaces call start times, agent assignments, duration, storage backend, and transcript/QA badges with pagination defaults of 25/50/100 rows.
- Operators can export the filtered dataset to CSV via the `Export CSV` action; privacy masking is enforced in the export when enabled through Admin → Settings → Privacy & Retention.

## QA Scoring & Reports

- Admin → Settings → QA Rubric introduces a drag-and-drop rubric builder for categories, weights, yes/no and scale questions, pass thresholds, and tag presets. Saving the rubric increments the rubric version so scorers are forced to refresh when the configuration changes.
- `/admin/recordings/{id}` now renders the Genesys-style workspace with waveform playback, synchronized transcript search, and a right-hand QA panel that auto-saves drafts, supports forced pass/fail overrides, tag suggestions, keyboard shortcuts, and versioned history.
- Draft saves and submissions happen through the new `/admin/recordings/{recording}/qa/score` endpoint; conflicts return HTTP 409 with the latest rubric version so the Alpine workspace can prompt reviewers to reload.
- `/admin/qa/reports` surfaces per-agent and per-team rollups with pass-rate trendlines and a CSV export (`/admin/qa/export`) that includes rubric metadata, tags, and timestamps for downstream analysis.

## Transcription Pipeline

- `TranscribeRecordingJob` converts stored audio into transcripts using the configured Whisper API or local `whisper.cpp` binary through the `TranscriptionService`, capturing full-text output plus time-aligned segments for the QA interface.
- Admin settings expose the Whisper API timeout, CLI binary/model/threads/timeout inputs, and a daily transcription minutes cap to throttle spending; language preferences follow the saved default and persist per transcript.
- Domain jobs record success and retry metadata, while the worker backfills transcripts into the `transcripts` table with `processing`/`ready` status tracking for observability dashboards.

## Notifications & Alerting

- Admin → Settings → Notifications captures SMTP credentials, alert recipients, Slack webhook, and the transcription backlog threshold. Alert recipients accept comma-separated emails and are persisted securely in the settings store.
- `NotificationService` delivers email and Slack alerts for poller failures, repeated download job failures, transcription backlog spikes, and storage thresholds. Alerts are throttled to avoid duplicate floods and persist last-dispatched timestamps in settings.
- Storage thresholds include local disk utilisation (`CALLHUB_STORAGE_ALERT_PERCENT`) and optional S3 usage ceilings (`CALLHUB_STORAGE_ALERT_S3_GB`); transcription backlogs use the configured threshold or the admin override (`CALLHUB_TRANSCRIPTION_BACKLOG_THRESHOLD`).
- New scheduler entries run `notifications:health-check` every 15 minutes for disk/backlog alerts and `notifications:daily-summary` at 07:30 to email daily ingestion/download/transcription/QA rollups. Trigger test notifications from the settings tab to validate mail and Slack plumbing.

## Admin Dashboard & Health

- `/admin` now surfaces an operations overview with rolling 24-hour metrics for call ingestion, recording downloads, transcription completions, and job failures alongside queue depth and storage utilisation.
- Alerts raise when local disk consumption exceeds 80% or when recent job failures require investigation; admins can drill into the bundled log viewer at `/admin/logs`.
- Storage backend usage is summarised per driver, helping operators identify when to migrate recordings to S3 and ensuring health dashboards reflect the most recent telephony poll timestamp alongside scheduler/job heartbeat data sourced from `/health/status`.

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

qa_scores (id, call_id, scored_by, version, status)

jobs (id, type, status, run_at)

audits (id, user_id, subject_type, subject_id)
```

The database seeder provisions a default administrator account (`CALLHUB_ADMIN_EMAIL`) and a placeholder provider for integration testing.
