<?php

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('patches the NativePHP v4 Android shell', function (string $version, string $expectedBundleVersion): void {
    $files = new Filesystem;
    $originalBasePath = base_path();
    $buildPath = storage_path('framework/testing/nativephp-v4-'.uniqid());
    $manifestPath = $buildPath.'/app/src/main/AndroidManifest.xml';
    $assetsPath = $buildPath.'/app/src/main/assets';
    $versionCode = 42;
    $configuredVersion = config('nativephp.version');
    $configuredVersionCode = config('nativephp.version_code');

    config([
        'nativephp.version' => 'stale-version',
        'nativephp.version_code' => 41,
    ]);

    expect($files->copyDirectory(
        base_path('vendor/nativephp/mobile/resources/androidstudio'),
        $buildPath,
    ))->toBeTrue();

    $files->put($buildPath.'/.env', "NATIVEPHP_APP_VERSION=next-build\nNATIVEPHP_APP_VERSION_CODE=43\n");
    $files->ensureDirectoryExists($assetsPath);
    $files->put($assetsPath.'/bundle_meta.json', json_encode([
        'version' => '1.0.0',
        'version_code' => 1,
    ], JSON_THROW_ON_ERROR));
    $bundle = new ZipArchive;
    expect($bundle->open($assetsPath.'/laravel_bundle.zip', ZipArchive::CREATE))->toBeTrue();
    $bundle->addFromString('.version', "1.0.0b1\n");
    $bundle->addFromString('.env', "NATIVEPHP_APP_VERSION={$version}\nNATIVEPHP_APP_VERSION_CODE=42\n");
    $bundle->close();

    try {
        app()->setBasePath($buildPath);

        $this->artisan('camera-permissions:install-webview-camera-access', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
            '--plugin-path' => $originalBasePath.'/native-plugins/camera-permissions',
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        $files->put(
            $manifestPath,
            str($files->get($manifestPath))->replaceFirst(
                '</intent-filter>',
                <<<'XML'
</intent-filter><meta-data
                android:name="android.app.shortcuts"
                android:resource="@xml/shortcuts" /><meta-data
                android:name="android.app.shortcuts"
                android:resource="@xml/shortcuts" />
XML,
            )->toString(),
        );

        $this->artisan('native-shell:install', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
            '--plugin-path' => $originalBasePath.'/native-plugins/native-refresh',
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        $mainActivity = $files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt');
        $webViewManager = $files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt');

        $manifest = $files->get($manifestPath);
        $mainActivityManifest = (string) str($manifest)->between('android:name=".ui.MainActivity"', '</activity>');
        $bundleMetadata = json_decode($files->get($assetsPath.'/bundle_meta.json'), true, flags: JSON_THROW_ON_ERROR);
        $bundle = new ZipArchive;
        $bundle->open($assetsPath.'/laravel_bundle.zip');
        $bundledVersion = $bundle->getFromName('.version');
        $bundle->close();

        expect($mainActivity)
            ->toContain('webRenderer?.manager?.handleFileChooserResult(requestCode, resultCode, data) == true')
            ->toContain('webRenderer?.manager?.handleCameraPermissionResult(requestCode, grantResults) == true')
            ->toContain('private var swipeRefreshLayout: SwipeRefreshLayout? = null')
            ->toContain('SwipeRefreshLayout(context).apply')
            ->toContain('val webView = renderer.webView')
            ->toContain('fun finishPullRefresh()')
            ->toContain('window.__buffHandleAndroidBack')
            ->toContain('web.evaluateJavascript(')
            ->not->toContain('if (::webViewManager.isInitialized')
            ->toContain('uri.scheme == "buff"')
            ->not->toContain('nativephp://')
            ->and($webViewManager)
            ->toContain('override fun onShowFileChooser(')
            ->toContain('private val fileChooserCameraPermissionRequestCode = 45872')
            ->toContain('if (!params.isCaptureEnabled || !acceptsImages)')
            ->toContain('activity.startActivityForResult(pickerIntent, fileChooserRequestCode)')
            ->toContain('fileChooserCameraPermissionRequestCode')
            ->toContain('(cameraCaptureFile?.length() ?: 0L) > 0L')
            ->toContain('data?.clipData?.let { clipData ->')
            ->toContain('clipData.getItemAt(index).uri')
            ->not->toContain('Intent.EXTRA_INITIAL_INTENTS')
            ->toContain('fun handleFileChooserResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean')
            ->toContain('fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean')
            ->toContain('(context as? MainActivity)?.finishPullRefresh()')
            ->toContain('webView, nativeJavaScript(), setOf("http://127.0.0.1")')
            ->toContain('private fun nativeJavaScript(): String = """')
            ->toContain('view.evaluateJavascript(nativeJavaScript())')
            ->toContain('observer.observe(document, {')
            ->not->toContain('observer.observe(document.body, {')
            ->toContain('url.startsWith("buff://")')
            ->not->toContain('nativephp://')
            ->and($files->get($buildPath.'/app/build.gradle.kts'))
            ->toContain('androidx.swiperefreshlayout:swiperefreshlayout:1.1.0')
            ->toContain("versionCode = {$versionCode}")
            ->toContain("versionName = \"{$version}\"")
            ->and($bundleMetadata['version'])
            ->toBe($version)
            ->and($bundleMetadata['version_code'])
            ->toBe((string) $versionCode)
            ->and($bundledVersion)
            ->toBe($expectedBundleVersion."\n")
            ->and(config('nativephp.version'))
            ->toBe($version)
            ->and(config('nativephp.version_code'))
            ->toBe($versionCode)
            ->and(substr_count($manifest, 'android.app.shortcuts'))
            ->toBe(1)
            ->and($mainActivityManifest)
            ->toContain('android.app.shortcuts')
            ->and($files->get($buildPath.'/app/src/main/res/xml/shortcuts.xml'))
            ->toContain('buff://add')
            ->toContain('buff://add?mode=food&amp;scan=1')
            ->toContain('@mipmap/shortcut_add')
            ->toContain('@mipmap/shortcut_scan')
            ->toContain('@mipmap/shortcut_workout');

        $this->artisan('native-shell:install', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
            '--plugin-path' => $originalBasePath.'/native-plugins/native-refresh',
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        expect($files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt'))
            ->toBe($webViewManager);
    } finally {
        app()->setBasePath($originalBasePath);
        config([
            'nativephp.version' => $configuredVersion,
            'nativephp.version_code' => $configuredVersionCode,
        ]);
        $files->deleteDirectory($buildPath);
    }
})->with([
    'versioned' => ['1.2.3', '1.2.3b42'],
    'debug' => ['DEBUG', 'DEBUG'],
]);

it('rejects invalid Android bundle metadata before changing the shell', function (?string $environment, string $message): void {
    $files = new Filesystem;
    $buildPath = storage_path('framework/testing/nativephp-invalid-'.uniqid());
    $assetsPath = $buildPath.'/app/src/main/assets';
    $files->ensureDirectoryExists($assetsPath);
    $files->put($buildPath.'/app/build.gradle.kts', 'versionCode = 41');
    $bundle = new ZipArchive;
    $bundle->open($assetsPath.'/laravel_bundle.zip', ZipArchive::CREATE);
    $bundle->addFromString('.version', "1.2.3b41\n");

    if ($environment !== null) {
        $bundle->addFromString('.env', $environment);
    }

    $bundle->close();

    try {
        expect(fn () => Artisan::call('native-shell:install', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
        ]))->toThrow(RuntimeException::class, $message);

        expect($files->get($buildPath.'/app/build.gradle.kts'))->toBe('versionCode = 41');
    } finally {
        $files->deleteDirectory($buildPath);
    }
})->with([
    'missing environment' => [null, 'missing its environment configuration'],
    'invalid version code' => ["NATIVEPHP_APP_VERSION=1.2.3\nNATIVEPHP_APP_VERSION_CODE=0\n", 'must be valid'],
]);

it('rejects native commands when frontend assets were built for another platform', function (): void {
    $files = new Filesystem;
    $originalPublicPath = public_path();
    $publicPath = storage_path('framework/testing/nativephp-assets-'.uniqid());
    app()->usePublicPath($publicPath);
    $markerPath = public_path('build/native-platform');
    $event = fn (string $command, array $input): CommandStarting => new CommandStarting(
        $command,
        new ArrayInput($input, Artisan::findCommand($command)->getDefinition()),
        new BufferedOutput,
    );

    try {
        $files->ensureDirectoryExists(dirname($markerPath));
        $files->put($markerPath, "ios\n");

        expect(fn () => Event::dispatch($event('native:run', ['os' => 'android'])))
            ->toThrow(RuntimeException::class, 'pnpm run build:android');

        expect(fn () => Event::dispatch($event('native:package', ['--android' => true, '--skip-prepare' => true])))
            ->not->toThrow(RuntimeException::class);

        expect(fn () => Event::dispatch($event('native:package', ['platform' => 'android', '--test-push' => '/tmp/app.aab'])))
            ->not->toThrow(RuntimeException::class);

        $files->put($markerPath, "android\n");

        expect(fn () => Event::dispatch($event('native:run', ['os' => 'a'])))->not->toThrow(RuntimeException::class);

        expect(fn () => Event::dispatch($event('native:watch', ['--android' => true])))->not->toThrow(RuntimeException::class);

        expect(fn () => Event::dispatch($event('native:watch', ['--ios' => true])))
            ->toThrow(RuntimeException::class, 'pnpm run build:ios');

        expect(fn () => Event::dispatch($event('native:build', [])))
            ->toThrow(RuntimeException::class, 'pnpm run build:ios');
    } finally {
        app()->usePublicPath($originalPublicPath);
        $files->deleteDirectory($publicPath);
    }
});

it('keeps valid orientation defaults for both native platforms', function (): void {
    expect(config('nativephp.ipad'))->toBeFalse()
        ->and(config('nativephp.orientation.iphone.portrait'))->toBeTrue()
        ->and(config('nativephp.orientation.android.portrait'))->toBeTrue();
});

it('declares why Buff needs the iOS camera', function (): void {
    expect(config('nativephp.permissions.NSCameraUsageDescription'))
        ->toBe('Buff uses the camera to scan food barcodes and take meal and progress photos.');
});

it('reconciles the iOS bundle with the incremented Xcode release version', function (string $version, string $expectedVersion): void {
    $files = new Filesystem;
    $buildPath = storage_path('framework/testing/nativephp-ios-version-'.uniqid());
    $assetsPath = $buildPath.'/NativePHP';
    $files->ensureDirectoryExists($assetsPath);
    $files->ensureDirectoryExists($buildPath.'/NativePHP.xcodeproj');
    $files->put($buildPath.'/NativePHP.xcodeproj/project.pbxproj', "MARKETING_VERSION = {$version};\nCURRENT_PROJECT_VERSION = 42;\n");
    $files->put($assetsPath.'/bundle_meta.json', json_encode([
        'version' => $version, 'version_code' => 41, 'entry_mode' => 'web',
    ], JSON_THROW_ON_ERROR));
    $files->put($assetsPath.'/bundled.version', '1.0.0b41');
    $bundle = new ZipArchive;
    $bundle->open($assetsPath.'/app.zip', ZipArchive::CREATE);
    $bundle->addFromString('.env', "NATIVEPHP_APP_VERSION={$version}\nNATIVEPHP_APP_VERSION_CODE=41\nAPP_DEBUG=false\n");
    $bundle->addFromString('public/build/native-platform', "ios\n");
    $bundle->close();

    try {
        foreach ([1, 2] as $run) {
            $this->artisan('native-shell:install', [
                '--platform' => 'ios',
                '--build-path' => $buildPath,
                '--plugin-path' => base_path('native-plugins/native-refresh'),
                '--app-id' => 'com.mason.buff',
            ])->assertSuccessful();
        }

        $bundle->open($assetsPath.'/app.zip');
        expect($bundle->getFromName('.env'))->toBe("APP_DEBUG=false\nNATIVEPHP_APP_VERSION={$version}\nNATIVEPHP_APP_VERSION_CODE=42\n");
        expect($bundle->getFromName('public/build/native-platform'))->toBe("ios\n");
        $bundle->close();
        expect($files->get($assetsPath.'/bundled.version'))->toBe($expectedVersion);
        expect(json_decode($files->get($assetsPath.'/bundle_meta.json'), true))->toBe([
            'version' => $version, 'version_code' => 42, 'entry_mode' => 'web',
        ]);
    } finally {
        $files->deleteDirectory($buildPath);
    }
})->with([
    'release' => ['1.2.3', '1.2.3b42'],
    'debug' => ['DEBUG', 'DEBUG'],
]);

it('installs the iOS shell integrations', function (): void {
    $files = new Filesystem;
    $buildPath = storage_path('framework/testing/nativephp-ios-'.uniqid());
    $appDelegatePath = $buildPath.'/NativePHP/AppDelegate.swift';
    $contentViewPath = $buildPath.'/NativePHP/ContentView.swift';
    $schemeHandlerPath = $buildPath.'/NativePHP/PHPSchemeHandler.swift';
    $infoPlistPath = $buildPath.'/NativePHP/Info.plist';
    $simulatorInfoPlistPath = $buildPath.'/NativePHP-simulator-Info.plist';
    $xcodeProjectPath = $buildPath.'/NativePHP.xcodeproj/project.pbxproj';
    $appIconPath = $buildPath.'/NativePHP/AppIcon.icon';
    $privacyManifestPath = $buildPath.'/NativePHP/PrivacyInfo.xcprivacy';
    $toastPath = $buildPath.'/NativePHP/Components/Toast.swift';

    $files->ensureDirectoryExists(dirname($appDelegatePath));
    $files->ensureDirectoryExists(dirname($toastPath));
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/Components/Toast.swift'),
        $toastPath,
    );
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/AppDelegate.swift'),
        $appDelegatePath,
    );
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/ContentView.swift'),
        $contentViewPath,
    );
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/PHPSchemeHandler.swift'),
        $schemeHandlerPath,
    );
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/Info.plist'),
        $infoPlistPath,
    );
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP-simulator-Info.plist'),
        $simulatorInfoPlistPath,
    );
    $files->ensureDirectoryExists(dirname($xcodeProjectPath));
    $files->copy(
        base_path('vendor/nativephp/mobile/resources/xcode/NativePHP.xcodeproj/project.pbxproj'),
        $xcodeProjectPath,
    );

    try {
        $this->artisan('native-shell:install', [
            '--platform' => 'ios',
            '--build-path' => $buildPath,
            '--plugin-path' => base_path('native-plugins/native-refresh'),
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        $toast = $files->get($toastPath);
        expect($toast)
            ->toContain('let yPosition = safeAreaInsets.top + 16')
            ->toContain('toastLabel.backgroundColor = .darkGray')
            ->not->toContain('window.frame.height - safeAreaInsets.bottom - toastHeight - 100');

        $this->artisan('native-shell:install', [
            '--platform' => 'ios',
            '--build-path' => $buildPath,
            '--plugin-path' => base_path('native-plugins/native-refresh'),
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        expect($files->get($toastPath))->toBe($toast);

        expect($files->get($appDelegatePath))
            ->toContain('performActionFor shortcutItem: UIApplicationShortcutItem')
            ->toContain('DeepLinkRouter.shared.handle(url: url)')
            ->and($files->get($contentViewPath))
            ->toContain('func addPullToRefresh(')
            ->toContain('#selector(Coordinator.refreshWebView(_:))')
            ->toContain('webView.scrollView.refreshControl?.endRefreshing()')
            ->and($files->get($schemeHandlerPath))
            ->toContain('uri = encodedPath.isEmpty ? "/" : encodedPath')
            ->toContain('request.query = redirectComponents?.percentEncodedQuery')
            ->toContain('if !trimmedLocation.hasPrefix("http://")')
            ->not->toContain('request.uri = location.trimmingCharacters(in: .whitespaces)')
            ->and($files->get($infoPlistPath))
            ->toContain('<key>UIApplicationShortcutItems</key>')
            ->toContain('<string>buff://add?mode=food&amp;scan=1</string>')
            ->and($files->get($simulatorInfoPlistPath))
            ->toContain('<key>UIApplicationShortcutItems</key>')
            ->and($files->get($appIconPath.'/icon.json'))
            ->toBe($files->get(public_path('icon.icon/icon.json')))
            ->and($files->get($appIconPath.'/Assets/Vector.svg'))
            ->toBe($files->get(public_path('icon.icon/Assets/Vector.svg')))
            ->and($files->get($privacyManifestPath))
            ->toContain(
                '<key>NSPrivacyTracking</key>',
                '<false/>',
                'NSPrivacyAccessedAPICategoryFileTimestamp',
                'C617.1',
                'NSPrivacyAccessedAPICategoryDiskSpace',
                'E174.1',
                'NSPrivacyAccessedAPICategoryUserDefaults',
                'CA92.1',
            )
            ->and(substr_count(
                $files->get($xcodeProjectPath),
                'ASSETCATALOG_COMPILER_APPICON_NAME = AppIcon;',
            ))
            ->toBe(4)
            ->and(substr_count(
                $files->get($xcodeProjectPath),
                'CODE_SIGN_ENTITLEMENTS = NativePHP/NativePHP.entitlements;',
            ))
            ->toBe(4);
    } finally {
        $files->deleteDirectory($buildPath);
    }
});
