<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class BuildInstallZipCommand extends Command
{
    protected $signature = 'build:zip {--filename=callhub-latest.zip : Output zip filename}';

    protected $description = 'Create a production-ready distribution zip with vendor dependencies bundled.';

    private const INSTALL_BLOCK_RULE = 'RewriteRule ^install - [R=403,L]';

    public function handle(): int
    {
        if (! File::exists(base_path('vendor/autoload.php'))) {
            $this->error('Vendor dependencies not found. Run "composer install --no-dev" before packaging.');

            return self::FAILURE;
        }

        $this->info('Running Laravel optimizations...');
        Artisan::call('optimize');
        Artisan::call('optimize:clear');

        $distPath = base_path('dist');
        File::ensureDirectoryExists($distPath);

        $zipPath = $distPath . DIRECTORY_SEPARATOR . $this->option('filename');
        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Unable to create distribution archive at ' . $zipPath);

            return self::FAILURE;
        }

        $basePath = base_path();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $relativePath = Str::after($file->getRealPath(), $basePath . DIRECTORY_SEPARATOR);

            if ($this->shouldSkip($relativePath, $file->isDir())) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
                continue;
            }

            if ($relativePath === 'public/.htaccess') {
                $zip->addFromString($relativePath, $this->sanitiseHtaccess($file->getRealPath()));
                continue;
            }

            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();

        $this->info('Distribution archive created: ' . $zipPath);

        return self::SUCCESS;
    }

    private function shouldSkip(string $relativePath, bool $isDirectory): bool
    {
        if ($relativePath === '') {
            return false;
        }

        if (Str::startsWith($relativePath, ['dist' . DIRECTORY_SEPARATOR, 'dist/'])) {
            return true;
        }

        if (Str::startsWith($relativePath, ['.git' . DIRECTORY_SEPARATOR, '.git/'])) {
            return true;
        }

        if (Str::startsWith($relativePath, ['node_modules' . DIRECTORY_SEPARATOR, 'node_modules/'])) {
            return true;
        }

        if (Str::startsWith($relativePath, ['tests' . DIRECTORY_SEPARATOR, 'tests/'])) {
            return true;
        }

        if (Str::startsWith($relativePath, ['storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache', 'storage/framework/cache'])) {
            if ($isDirectory) {
                return false;
            }

            return $this->shouldSkipStorageFile($relativePath);
        }

        if (Str::startsWith($relativePath, ['storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions', 'storage/framework/sessions'])) {
            if ($isDirectory) {
                return false;
            }

            return $this->shouldSkipStorageFile($relativePath);
        }

        if (Str::startsWith($relativePath, ['storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'testing', 'storage/framework/testing'])) {
            if ($isDirectory) {
                return false;
            }

            return $this->shouldSkipStorageFile($relativePath);
        }

        if (Str::startsWith($relativePath, ['storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views', 'storage/framework/views'])) {
            if ($isDirectory) {
                return false;
            }

            return $this->shouldSkipStorageFile($relativePath);
        }

        if (Str::startsWith($relativePath, ['storage' . DIRECTORY_SEPARATOR . 'logs', 'storage/logs'])) {
            if ($isDirectory) {
                return false;
            }

            return $this->shouldSkipStorageFile($relativePath);
        }

        if (Str::startsWith($relativePath, ['storage' . DIRECTORY_SEPARATOR . 'app', 'storage/app'])) {
            if ($isDirectory) {
                return false;
            }

            return $this->shouldSkipStorageFile($relativePath);
        }

        if (Str::startsWith($relativePath, ['vendor' . DIRECTORY_SEPARATOR, 'vendor/'])) {
            return false;
        }

        if ($relativePath === '.env') {
            return true;
        }

        if ($relativePath === 'storage/installed.flag' || $relativePath === 'storage' . DIRECTORY_SEPARATOR . 'installed.flag') {
            return true;
        }

        if ($isDirectory) {
            return false;
        }

        return false;
    }

    private function shouldSkipStorageFile(string $relativePath): bool
    {
        if (Str::endsWith($relativePath, '.gitignore')) {
            return false;
        }

        return true;
    }

    private function sanitiseHtaccess(string $path): string
    {
        $contents = File::get($path);

        if (! Str::contains($contents, self::INSTALL_BLOCK_RULE)) {
            return $contents;
        }

        $cleaned = preg_replace('/\n?\s*' . preg_quote(self::INSTALL_BLOCK_RULE, '/') . '\n?/', "\n", $contents, 1) ?? $contents;

        return $cleaned;
    }
}
