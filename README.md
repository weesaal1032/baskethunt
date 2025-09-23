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

## Modules

Domains are available under `app/Domains/*` with corresponding repository interfaces in `app/Repositories/Contracts` and Eloquent stubs in `app/Repositories/Eloquent`.
