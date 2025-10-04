# Deploying CallHub on cPanel

This guide walks through packaging CallHub with vendor dependencies, uploading it to a cPanel host, configuring file permissions, and wiring the required cron jobs. Follow these steps after verifying the application locally.

## 1. Build a vendor-bundled release archive
1. Run `composer install --no-dev --optimize-autoloader` and `npm install && npm run build` locally or in CI.
2. Remove any development caches (`rm -rf node_modules tests .git`).
3. Zip the entire project directory **including** the populated `vendor/` folder, for example `zip -r callhub-release.zip .`.

## 2. Upload and extract under `public_html`
1. Log into cPanel → **File Manager**.
2. Upload `callhub-release.zip` into `public_html` (or a subdirectory such as `public_html/callhub`).
3. Extract the archive; ensure `public/index.php` is reachable relative to the web root. If you unpacked into a subdirectory, create an `.htaccess` rewrite or symlink as needed.

## 3. Ensure writable storage paths
1. Inside cPanel File Manager or via SSH, set the correct permissions:
   - `storage/` and `bootstrap/cache/` must be writable by PHP. Typical permissions are `775` with the group matching the web server user.
   - Confirm `storage/installed.flag` exists after running the installer; delete it only if you intend to re-run setup.
2. If you require additional writable directories (for example custom waveform caches), apply the same permissions.

## 4. Configure environment
1. Visit `https://<domain>/install` to run the web installer. Provide database credentials, administrator details, and base URL when prompted.
2. The wizard writes `.env` and `storage/installed.flag`. After success, `/install` returns HTTP 403 until you run `php artisan installer:unlock` deliberately.

## 5. Register cron jobs
Set the following cron entries under **cPanel → Cron Jobs**. Replace `/home/USER/public_html` with your actual path (e.g. `/home/myuser/public_html/callhub`).

```
*/5 * * * * php /home/USER/public_html/artisan schedule:run >> /home/USER/public_html/storage/logs/scheduler.log 2>&1
*/5 * * * * php /home/USER/public_html/artisan jobs:run --once --max=25 >> /home/USER/public_html/storage/logs/domain-worker.log 2>&1
```

Notes:
- `schedule:run` must execute at least every five minutes so the scheduler heartbeat updates `/health/status` and downstream metrics.
- `jobs:run` processes queued download/transcription tasks without requiring a resident worker. Increase `--max` if your workload requires.
- If you additionally rely on Laravel queue workers for other queues, register `queue:work --stop-when-empty` with similar cadence.

## 6. Configure S3-compatible storage
1. Navigate to **Admin → Settings → Storage**.
2. Select **S3** and enter access key, secret, region, bucket, and optional prefix.
3. Ensure the bucket ACL is private. CallHub issues signed URLs per request so direct object access remains blocked.
4. Update `.env` if you prefer environment defaults: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and `CALLHUB_STORAGE_PREFIX`.

## 7. Configure Whisper transcription
1. In **Admin → Settings → Transcription**, choose **Whisper API** or **Local Whisper**.
2. For Whisper API, set the API key and adjust timeout/minute caps as needed.
3. For local `whisper.cpp`, provide the CLI binary path, desired model, thread count, and timeout. Ensure the binary is executable on the cPanel host (custom builds can live under `storage/app/bin`).
4. Cron-driven transcription jobs follow the same `jobs:run` worker cadence defined above.

## 8. Configure telephony provider API
1. Open **Admin → Settings → Telephony Provider** and enter the base URL, authentication mode (header vs bearer), API key, pagination size, and rate-limit JSON if required by your carrier.
2. Map remote payload fields in **Admin → Providers → Telephony Mapping** and run a preview to validate connectivity.
3. `poll:calls` uses the saved mapping and poll window settings. Confirm the cron jobs from step 5 are active to keep ingestion rolling.

## 9. Verify deployment
1. Visit `/admin` and confirm dashboard metrics populate after cron executes.
2. Check `/health/status` for updated `scheduler_last_ran_at` and `jobs_runner_last_ran_at` timestamps (these advance when the cron jobs fire).
3. Review `storage/logs` for errors and ensure alerts/notifications fire correctly using the Settings “Send test alert” controls.

Following these steps ensures CallHub operates reliably on shared cPanel hosting while retaining all queue, transcription, and storage capabilities.
