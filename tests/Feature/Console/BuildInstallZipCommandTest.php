<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('build:zip produces installer-ready distribution', function () {
    $vendorPath = base_path('vendor');
    $autoloadPath = $vendorPath . DIRECTORY_SEPARATOR . 'autoload.php';
    $createdVendor = false;
    $originalAutoload = null;

    if (! File::exists($vendorPath)) {
        File::ensureDirectoryExists($vendorPath);
        $createdVendor = true;
    }

    if (File::exists($autoloadPath)) {
        $originalAutoload = File::get($autoloadPath);
    }

    File::put($autoloadPath, '<?php // autoload');

    $htaccessPath = public_path('.htaccess');
    $originalHtaccess = File::get($htaccessPath);
    File::append($htaccessPath, "\n    RewriteRule ^install - [R=403,L]\n");

    $logsDir = storage_path('logs');
    File::ensureDirectoryExists($logsDir);
    File::put($logsDir . DIRECTORY_SEPARATOR . 'secret.log', 'secret');

    $storageAppDummy = storage_path('app/dummy.txt');
    File::put($storageAppDummy, 'keep private');

    $zipPath = base_path('dist/callhub-latest.zip');
    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }

    $result = Artisan::call('build:zip');

    expect($result)->toBe(0);

    expect(File::exists($zipPath))->toBeTrue();

    $zip = new ZipArchive();
    expect($zip->open($zipPath))->toBeTrue();

    expect($zip->locateName('storage/logs/secret.log'))->toEqual(false);
    expect($zip->locateName('storage/app/dummy.txt'))->toEqual(false);
    expect($zip->locateName('storage/installed.flag'))->toEqual(false);
    expect($zip->locateName('.env'))->toEqual(false);
    expect(trim($zip->getFromName('vendor/autoload.php')))->toBe('<?php // autoload');
    expect($zip->getFromName('public/.htaccess'))->not()->toContain('RewriteRule ^install');

    $zip->close();

    File::delete($zipPath);
    File::delete($logsDir . DIRECTORY_SEPARATOR . 'secret.log');
    File::delete($storageAppDummy);
    File::put($htaccessPath, $originalHtaccess);

    if ($createdVendor) {
        File::deleteDirectory($vendorPath);
    } elseif ($originalAutoload !== null) {
        File::put($autoloadPath, $originalAutoload);
    } else {
        File::delete($autoloadPath);
    }
});
