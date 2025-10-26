# CallHub v1.0 Release Notes

## Overview
CallHub delivers an end-to-end call quality operations platform on Laravel 11. The release bundles authenticated access, multi-stage ingestion, RBAC-secured playback, transcription, QA scoring workflows, and observability dashboards suitable for hosted cPanel deployments. The package includes a WordPress-style installer, vendor-bundled build tooling, and documentation to streamline first-time installs.

## Feature Highlights
- **Authentication & RBAC** – Email/password login with optional OTP, throttled login attempts, and role middleware/policies for Admin, QA, Lead, and Readonly personas.
- **Installer Wizard** – Four-step `/install` flow verifies prerequisites, writes `.env`, seeds the database, provisions the admin account, and locks itself post-setup with CLI unlock support.
- **Telephony Ingestion** – Generic REST client with configurable field mapping, rate-limit aware pagination, and a poller command that upserts calls, tracks cursors, and enqueues recording downloads.
- **Recording Pipeline** – Download worker performs streamed fetches with checksum validation, FFmpeg transcoding to MP3, waveform generation, pluggable Local/S3 storage drivers, and transcription job dispatch.
- **Transcription Engines** – Whisper API and local `whisper.cpp` drivers with configurable language, model, timeout, and daily minute caps.
- **QA Workspace** – Genesys-style review page with waveform player, transcript sync/search, keyboard shortcuts, rubric-driven scoring with autosave/versioning, and history exports.
- **Reporting & APIs** – Recording library filters with CSV export, QA trend dashboards, internal JWT-secured APIs for calls and QA scores, and admin help/docs for BI consumers.
- **Security & Privacy** – Phone masking, audit logging, retention enforcement with deletion grace periods, installer lock, and hardened audio streaming with short-lived signed URLs.
- **Notifications & Health** – Scheduler-driven alerts for poll/download/transcription/disk events, daily summaries, admin dashboard metrics, log viewer, and `/admin/health/e2e` diagnostics harness.
- **Deployment Tooling** – `build:zip` artisan command for vendor-bundled archives, cPanel deployment playbook, smoke-test script, and PHPStan/Pest coverage.

## Setup Checklist
1. **Install dependencies**: `composer install` and `npm install`.
2. **Provision environment**: copy `.env.example`, run `php artisan key:generate`, or execute the `/install` wizard in-browser.
3. **Configure storage**: ensure `storage` and `bootstrap/cache` are writable; run `php artisan storage:link`.
4. **Database**: `php artisan migrate` and run `php artisan db:seed` if skipping the installer.
5. **Cron Jobs** (cPanel-friendly):
   - `*/5 * * * * php /home/USER/public_html/artisan schedule:run`
   - `*/5 * * * * php /home/USER/public_html/artisan jobs:run --once --max=25`
6. **Queues**: optional worker `php artisan queue:work --stop-when-empty` on cron for burst processing.
7. **Settings**: visit `/admin/settings/general` to configure branding, telephony provider credentials, storage backend (Local/S3), transcription engine, notification channels, and privacy/retention controls.
8. **Smoke Test**: run `./scripts/smoke-test.sh` to execute Pest tests and PHPStan level 6.
9. **Deployment Packaging**: use `php artisan build:zip` when preparing vendor-bundled distributions for cPanel upload.

## Known Limitations
- Whisper transcription assumes FFmpeg availability on the host and requires manual installation of the Whisper CLI binary when using the local driver.
- Outbound webhooks for real-time provider notifications are not yet implemented; ingestion relies on scheduled polling.
- The QA analytics dashboards use aggregated queries suited for mid-sized datasets; extremely large deployments may require data warehouse offloading via the provided internal APIs.
- Smoke tests expect a configured database connection; ensure `.env` credentials point to an accessible MySQL/MariaDB instance before executing.
- S3 storage metrics rely on periodic bucket size snapshots; real-time object counts are not surfaced in the UI.

## Upgrade & Deployment Guidance
### Database Migrations
- Run `php artisan migrate` to apply new/updated tables for settings, calls, recordings, transcripts, QA scoring, audits, jobs, OTP tokens, and retention soft deletes.
- For upgrades from earlier CallHub previews, ensure existing recordings/transcripts are backfilled with soft delete columns (`deleted_at`) and that QA score tables include rubric metadata fields.

### Zero-Downtime Strategy
1. **Maintenance Window Prep**
   - Package the release with `php artisan build:zip` on a staging host.
   - Upload to the target server, extract to a versioned directory (e.g., `releases/2024-05-xx`).
   - Copy existing `.env` and `storage/installed.flag` into the new release.
2. **Database Migration**
   - Run `php artisan migrate --force` on the new release prior to switching the web root symlink.
3. **Cache Warmup**
   - Execute `php artisan optimize` and `php artisan config:cache` in the release directory.
4. **Atomic Switch**
   - Update the web root symlink (`current`) to point at the new release; reload PHP-FPM/Apache if required.
5. **Queue & Scheduler**
   - Restart queue workers or ensure cron-driven `jobs:run` picks up the new code automatically.
6. **Post-Deploy Verification**
   - Hit `/admin/health/e2e` to validate ingestion, storage, transcription, QA, exports, and dashboard metrics.
   - Confirm `/health/status` exposes fresh scheduler and jobs runner timestamps.

### Rollback Plan
- Retain the previous release directory; if issues surface, repoint the web root symlink back to the prior version and restore its vendor dependencies.
- Re-run `php artisan migrate:rollback` if database changes must be reverted (ensure backups are available before deployment).
- Review `storage/logs/laravel.log` and admin health alerts to diagnose any failed subsystems post-rollback.

## Support
For implementation questions or integration requests (additional providers, storage adapters, analytics), file issues against the project repository or contact the maintainers listed in the README.
