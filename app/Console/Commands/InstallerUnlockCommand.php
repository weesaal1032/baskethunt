<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallerUnlockCommand extends Command
{
    protected $signature = 'installer:unlock {--force : Skip confirmation prompt when removing the installed flag}';

    protected $description = 'Remove the installed flag so the web installer can run again.';

    public function handle(): int
    {
        $flag = storage_path('installed.flag');

        if (! File::exists($flag)) {
            $this->info('Installer is already unlocked.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('This will re-enable the installer. Continue?')) {
            $this->warn('Operation cancelled.');

            return self::FAILURE;
        }

        File::delete($flag);
        $this->unlockHtaccess();

        $this->info('Installer unlocked. Visit /install to run the wizard again.');

        return self::SUCCESS;
    }

    private function unlockHtaccess(): void
    {
        $htaccess = public_path('.htaccess');

        if (! File::exists($htaccess)) {
            return;
        }

        $contents = File::get($htaccess);

        if (! Str::contains($contents, 'RewriteRule ^install')) {
            return;
        }

        $updated = preg_replace('/\n?\s*RewriteRule \^install - \[R=403,L\]\n?/', "\n", $contents, 1) ?? $contents;

        File::put($htaccess, $updated);
    }
}
