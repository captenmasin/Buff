<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Native\Mobile\Support\BundleFileManager;

it('aligns the Inertia devtools recorder with the client build mode', function (): void {
    expect(config('inertia.devtools.enabled'))->toBe(Vite::isRunningHot());
});

it('excludes build credentials from mobile bundles', function (): void {
    $cleanupEnvKeys = config('nativephp.cleanup_env_keys');
    $unprotectedKeys = collect([
        'ANDROID_KEYSTORE_FILE',
        'ANDROID_KEYSTORE_PASSWORD',
        'ANDROID_KEY_ALIAS',
        'ANDROID_KEY_PASSWORD',
        'APP_STORE_API_ISSUER_ID',
        'APP_STORE_API_KEY_ID',
        'APP_STORE_API_KEY_PATH',
        'GOOGLE_SERVICE_ACCOUNT_KEY',
        'IOS_DISTRIBUTION_CERTIFICATE_PASSWORD',
        'IOS_DISTRIBUTION_CERTIFICATE_PATH',
        'IOS_DISTRIBUTION_PROVISIONING_PROFILE_PATH',
        'NATIVEPHP_DEVELOPMENT_TEAM',
    ])->reject(fn (string $key): bool => str($key)->is($cleanupEnvKeys));

    expect($unprotectedKeys)->toBeEmpty()
        ->and(str('BUFF_API_URL')->is($cleanupEnvKeys))->toBeFalse()
        ->and(BundleFileManager::excludes(config('nativephp.cleanup_exclude_files')))->toContain('/credentials');
});

it('excludes the Vite development marker from mobile bundles', function (): void {
    expect(BundleFileManager::excludes(config('nativephp.cleanup_exclude_files')))->toContain('/public/hot');
});

it('excludes local Inertia devtools captures from mobile bundles', function (): void {
    $source = sys_get_temp_dir().'/buff-native-source-'.uniqid();
    $destination = sys_get_temp_dir().'/buff-native-destination-'.uniqid();

    File::ensureDirectoryExists($source.'/storage/inertia-devtools');
    File::ensureDirectoryExists($source.'/app');
    File::put($source.'/storage/inertia-devtools/capture.json', '{"email":"test@example.com"}');
    File::put($source.'/app/keep.php', '<?php');

    try {
        BundleFileManager::copy($source, $destination, config('nativephp.cleanup_exclude_files'));

        expect(File::exists($source.'/storage/inertia-devtools/capture.json'))->toBeTrue()
            ->and(File::exists($destination.'/storage/inertia-devtools/capture.json'))->toBeFalse()
            ->and(File::exists($destination.'/app/keep.php'))->toBeTrue();
    } finally {
        File::deleteDirectory($source);
        File::deleteDirectory($destination);
    }
});

it('keeps Android camera features optional', function (): void {
    $manifest = json_decode(
        file_get_contents(base_path('native-plugins/camera-permissions/nativephp.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($manifest['android']['features'])->toContain(
        ['name' => 'android.hardware.camera', 'required' => false],
        ['name' => 'android.hardware.camera.autofocus', 'required' => false],
    );
});
