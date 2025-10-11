<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PDO;
use Symfony\Component\Process\Process;
use Throwable;

class InstallerController extends Controller
{
    private const STEPS = [
        'system' => 'System Checks',
        'database' => 'Database Setup',
        'admin' => 'Administrator Account',
        'app' => 'Application Configuration',
    ];

    public function index(Request $request): View
    {
        $step = $this->resolveStep($request);

        $payload = match ($step) {
            'system' => $this->systemStepPayload(),
            'database' => $this->databaseStepPayload(),
            'admin' => $this->adminStepPayload(),
            'app' => $this->appStepPayload(),
            default => $this->systemStepPayload(),
        };

        return view('layouts.base', [
            'title' => 'Install CallHub',
            'slot' => view($payload['view'], $payload['data'])->render(),
        ]);
    }

    public function store(Request $request): RedirectResponse|View
    {
        $step = $request->input('step', 'system');

        return match ($step) {
            'system' => $this->completeSystemStep($request),
            'database' => $this->completeDatabaseStep($request),
            'admin' => $this->completeAdminStep($request),
            'app' => $this->completeAppStep($request),
            default => redirect()->route('install.index'),
        };
    }

    private function resolveStep(Request $request): string
    {
        if (! $request->session()->has('installer.progress')) {
            $request->session()->put('installer.progress', 'system');
        }

        if (! $request->session()->has('installer.completed')) {
            $request->session()->put('installer.completed', []);
        }

        $current = (string) $request->session()->get('installer.progress', 'system');
        $requested = $request->query('step');

        if ($requested && array_key_exists($requested, self::STEPS)) {
            $order = array_keys(self::STEPS);
            $currentIndex = array_search($current, $order, true);
            $requestedIndex = array_search($requested, $order, true);

            $currentIndex = $currentIndex === false ? 0 : $currentIndex;
            $requestedIndex = $requestedIndex === false ? 0 : $requestedIndex;

            if ($requestedIndex <= $currentIndex) {
                return $requested;
            }
        }

        return $current;
    }

    private function systemStepPayload(): array
    {
        $checks = $this->runSystemChecks();
        $allPassed = ! in_array(false, array_column($checks, 'passed'), true);

        return [
            'view' => 'install.step-system',
            'data' => [
                'checks' => $checks,
                'allPassed' => $allPassed,
                'progress' => $this->progressState('system'),
            ],
        ];
    }

    private function databaseStepPayload(): array
    {
        $defaults = array_merge([
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'callhub',
            'db_username' => 'root',
            'db_password' => '',
        ], session()->get('installer.database', []));

        return [
            'view' => 'install.step-database',
            'data' => [
                'defaults' => $defaults,
                'progress' => $this->progressState('database'),
            ],
        ];
    }

    private function adminStepPayload(): array
    {
        $defaults = array_merge([
            'admin_name' => 'System Administrator',
            'admin_email' => 'admin@example.com',
        ], session()->get('installer.admin', []));

        return [
            'view' => 'install.step-admin',
            'data' => [
                'defaults' => $defaults,
                'progress' => $this->progressState('admin'),
            ],
        ];
    }

    private function appStepPayload(): array
    {
        $stored = session()->get('installer.app', []);

        if (! isset($stored['app_key'])) {
            $stored['app_key'] = 'base64:' . base64_encode(random_bytes(32));
            session()->put('installer.app', $stored);
        }

        $defaults = array_merge([
            'app_name' => config('app.name', 'CallHub'),
            'app_url' => url('/'),
            'app_key' => $stored['app_key'],
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name' => 'CallHub',
            'default_storage' => 'local',
            'queue_connection' => 'database',
            'timezone' => config('app.timezone', 'UTC'),
        ], $stored);

        return [
            'view' => 'install.step-app',
            'data' => [
                'defaults' => $defaults,
                'progress' => $this->progressState('app'),
            ],
        ];
    }

    private function progressState(string $current): array
    {
        $completed = session()->get('installer.completed', []);
        $state = [];

        foreach (self::STEPS as $key => $label) {
            $status = 'pending';

            if (in_array($key, $completed, true)) {
                $status = 'complete';
            } elseif ($key === $current) {
                $status = 'current';
            }

            $state[] = [
                'key' => $key,
                'label' => $label,
                'status' => $status,
            ];
        }

        return $state;
    }

    private function completeSystemStep(Request $request): RedirectResponse
    {
        $checks = $this->runSystemChecks();

        if (in_array(false, array_column($checks, 'passed'), true)) {
            return back()->withErrors([
                'system' => 'Please resolve the failing checks before continuing.',
            ]);
        }

        $this->markStepComplete('system', 'database');

        return redirect()->route('install.index', ['step' => 'database'])
            ->with('status', 'System requirements satisfied. Configure the database next.');
    }

    private function completeDatabaseStep(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'db_host' => ['required', 'string'],
            'db_port' => ['required', 'string'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        session()->put('installer.database', $data);

        try {
            $this->configureDatabaseConnection($data);
            DB::connection('installer')->getPdo();
        } catch (Throwable $throwable) {
            return back()->withErrors([
                'database' => 'Unable to connect to the database: ' . $throwable->getMessage(),
            ]);
        }

        try {
            Artisan::call('migrate', ['--force' => true, '--database' => 'installer']);
            Artisan::call('db:seed', ['--force' => true, '--database' => 'installer']);
        } catch (Throwable $throwable) {
            return back()->withErrors([
                'database' => 'Database migration failed: ' . $throwable->getMessage(),
            ]);
        }

        $this->markStepComplete('database', 'admin');

        return redirect()->route('install.index', ['step' => 'admin'])
            ->with('status', 'Database connected and migrated. Create your administrator account.');
    }

    private function completeAdminStep(Request $request): RedirectResponse
    {
        $database = session()->get('installer.database');

        if (! is_array($database)) {
            return redirect()->route('install.index');
        }

        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $this->configureDatabaseConnection($database);

        try {
            User::query()->updateOrCreate(
                ['email' => $data['admin_email']],
                [
                    'name' => $data['admin_name'],
                    'password' => Hash::make($data['admin_password']),
                    'role' => 'admin',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            Provider::query()->firstOrCreate(
                ['name' => 'Default Provider'],
                [
                    'base_url' => 'https://api.example.com',
                    'auth_type' => 'api_key',
                    'api_key' => Str::random(32),
                    'rate_limit_json' => [
                        'requests_per_minute' => 60,
                        'burst' => 10,
                    ],
                ],
            );
        } catch (Throwable $throwable) {
            return back()->withErrors([
                'admin' => 'Unable to create administrator: ' . $throwable->getMessage(),
            ]);
        }

        session()->put('installer.admin', [
            'admin_name' => $data['admin_name'],
            'admin_email' => $data['admin_email'],
        ]);

        $this->markStepComplete('admin', 'app');

        return redirect()->route('install.index', ['step' => 'app'])
            ->with('status', 'Administrator configured. Finalize the application settings.');
    }

    private function completeAppStep(Request $request): RedirectResponse|View
    {
        $database = session()->get('installer.database');
        $admin = session()->get('installer.admin');

        if (! is_array($database) || ! is_array($admin)) {
            return view('layouts.base', [
                'title' => 'Install CallHub',
                'slot' => view('components.placeholder', [
                    'heading' => 'Installation Incomplete',
                    'body' => 'Please restart the installer to ensure all steps are completed.',
                ])->render(),
            ]);
        }

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url'],
            'app_key' => ['required', 'string'],
            'mail_mailer' => ['required', 'string'],
            'mail_host' => ['required', 'string'],
            'mail_port' => ['required', 'string'],
            'mail_username' => ['nullable', 'string'],
            'mail_password' => ['nullable', 'string'],
            'mail_encryption' => ['nullable', 'string'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name' => ['required', 'string'],
            'default_storage' => ['required', 'in:local,s3'],
            'queue_connection' => ['required', 'string'],
            'timezone' => ['required', 'string'],
        ]);

        $appKey = Str::startsWith($data['app_key'], 'base64:')
            ? $data['app_key']
            : 'base64:' . base64_encode($data['app_key']);

        $envValues = [
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => $data['app_url'],
            'APP_TIMEZONE' => $data['timezone'],
            'LOG_CHANNEL' => 'stack',
            'LOG_LEVEL' => 'info',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['db_host'],
            'DB_PORT' => $database['db_port'],
            'DB_DATABASE' => $database['db_database'],
            'DB_USERNAME' => $database['db_username'],
            'DB_PASSWORD' => $database['db_password'],
            'BROADCAST_DRIVER' => 'log',
            'CACHE_DRIVER' => 'database',
            'FILESYSTEM_DISK' => $data['default_storage'],
            'QUEUE_CONNECTION' => $data['queue_connection'],
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => '120',
            'MAIL_MAILER' => $data['mail_mailer'],
            'MAIL_HOST' => $data['mail_host'],
            'MAIL_PORT' => $data['mail_port'],
            'MAIL_USERNAME' => $data['mail_username'],
            'MAIL_PASSWORD' => $data['mail_password'],
            'MAIL_ENCRYPTION' => $data['mail_encryption'],
            'MAIL_FROM_ADDRESS' => $data['mail_from_address'],
            'MAIL_FROM_NAME' => $data['mail_from_name'],
            'CALLHUB_STORAGE_BACKEND' => $data['default_storage'],
            'CALLHUB_ADMIN_EMAIL' => $admin['admin_email'],
            'CALLHUB_ADMIN_PASSWORD' => '',
        ];

        session()->put('installer.app', array_merge(session()->get('installer.app', []), $data, ['app_key' => $appKey]));

        try {
            File::put(base_path('.env'), $this->formatEnv($envValues));
            File::put(storage_path('installed.flag'), now()->toIso8601String());
            $this->lockInstallerAccess();
        } catch (Throwable $throwable) {
            return back()->withErrors([
                'app' => 'Unable to write configuration files: ' . $throwable->getMessage(),
            ]);
        }

        config()->set('app.name', $data['app_name']);
        config()->set('app.url', $data['app_url']);
        config()->set('app.key', $appKey);
        config()->set('app.timezone', $data['timezone']);
        config()->set('queue.default', $data['queue_connection']);
        config()->set('filesystems.default', $data['default_storage']);
        date_default_timezone_set($data['timezone']);

        $this->markStepComplete('app', 'app');
        $progress = $this->progressState('app');
        $cron = $this->cronExamples();

        session()->forget([
            'installer.progress',
            'installer.completed',
            'installer.database',
            'installer.admin',
            'installer.app',
        ]);

        $request->session()->flash('status', 'CallHub is ready to use. Secure the installer and log in.');

        return view('layouts.base', [
            'title' => 'Installation Complete',
            'slot' => view('install.complete', [
                'progress' => $progress,
                'cron' => $cron,
                'appUrl' => $data['app_url'],
            ])->render(),
        ]);
    }

    private function lockInstallerAccess(): void
    {
        $htaccessPath = public_path('.htaccess');

        if (! File::exists($htaccessPath)) {
            return;
        }

        $contents = File::get($htaccessPath);

        if (Str::contains($contents, 'RewriteRule ^install')) {
            return;
        }

        $rule = "    RewriteRule ^install - [R=403,L]";
        $needle = '    RewriteRule ^ index.php [L]';

        if (Str::contains($contents, $needle)) {
            $updated = Str::replaceFirst($needle, $rule . PHP_EOL . PHP_EOL . $needle, $contents);
        } else {
            $updated = rtrim($contents, "\n") . PHP_EOL . $rule . PHP_EOL;
        }

        File::put($htaccessPath, $updated);
    }

    private function runSystemChecks(): array
    {
        $checks = [];

        foreach (['curl', 'mbstring', 'openssl', 'pdo_mysql', 'zip'] as $extension) {
            $checks[] = [
                'label' => 'PHP extension: ' . $extension,
                'passed' => extension_loaded($extension),
                'details' => extension_loaded($extension) ? 'Loaded' : 'Missing',
            ];
        }

        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->setTimeout(5);
            $process->run();

            $checks[] = [
                'label' => 'FFmpeg binary availability',
                'passed' => $process->isSuccessful(),
                'details' => $process->isSuccessful()
                    ? trim(strtok($process->getOutput(), PHP_EOL))
                    : 'ffmpeg command not found or not executable',
            ];
        } catch (Throwable $throwable) {
            $checks[] = [
                'label' => 'FFmpeg binary availability',
                'passed' => false,
                'details' => 'Unable to execute ffmpeg: ' . $throwable->getMessage(),
            ];
        }

        $directories = [
            storage_path('app') => 'storage/app',
            storage_path('framework') => 'storage/framework',
            storage_path('logs') => 'storage/logs',
            base_path('bootstrap/cache') => 'bootstrap/cache',
        ];

        foreach ($directories as $path => $label) {
            $checks[] = [
                'label' => 'Writable: ' . $label,
                'passed' => is_writable($path),
                'details' => is_writable($path) ? 'Writable' : 'Update permissions',
            ];
        }

        return $checks;
    }

    private function configureDatabaseConnection(array $config): void
    {
        config()->set('database.connections.installer', [
            'driver' => 'mysql',
            'host' => $config['db_host'],
            'port' => $config['db_port'],
            'database' => $config['db_database'],
            'username' => $config['db_username'],
            'password' => $config['db_password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]);

        config()->set('database.default', 'installer');
        DB::purge('installer');
        DB::setDefaultConnection('installer');
    }

    private function markStepComplete(string $step, ?string $next): void
    {
        $completed = session()->get('installer.completed', []);

        if (! in_array($step, $completed, true)) {
            $completed[] = $step;
        }

        session()->put('installer.completed', $completed);

        if ($next) {
            session()->put('installer.progress', $next);
        }
    }

    private function formatEnv(array $values): string
    {
        $lines = [];

        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->formatEnvValue($value);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function formatEnvValue($value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value === '') {
            return "\"\"";
        }

        if (str_contains($value, ' ') || str_contains($value, '#')) {
            return '"' . addcslashes($value, "\"\\") . '"';
        }

        return $value;
    }

    private function cronExamples(): array
    {
        $projectPlaceholder = '/home/{cpanel_user}/callhub';

        return [
            [
                'title' => 'Scheduler Runner',
                'description' => 'Keeps recurring tasks and maintenance routines executing every minute.',
                'example' => sprintf('* * * * * cd %s && php artisan schedule:run >> %s/storage/logs/scheduler.log 2>&1', $projectPlaceholder, $projectPlaceholder),
            ],
            [
                'title' => 'Queue Worker',
                'description' => 'Processes database-backed queue jobs with safe retries.',
                'example' => sprintf('* * * * * cd %s && php artisan queue:work --queue=default --sleep=3 --tries=3 >> %s/storage/logs/queue-worker.log 2>&1', $projectPlaceholder, $projectPlaceholder),
            ],
        ];
    }
}
