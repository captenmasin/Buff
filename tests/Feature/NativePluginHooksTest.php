<?php

use Illuminate\Filesystem\Filesystem;

it('patches the NativePHP v4 Android shell', function (): void {
    $files = new Filesystem;
    $buildPath = storage_path('framework/testing/nativephp-v4-'.uniqid());

    expect($files->copyDirectory(
        base_path('vendor/nativephp/mobile/resources/androidstudio'),
        $buildPath,
    ))->toBeTrue();

    try {
        $this->artisan('camera-permissions:install-webview-camera-access', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
            '--plugin-path' => base_path('native-plugins/camera-permissions'),
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        $this->artisan('native-shell:install', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
            '--plugin-path' => base_path('native-plugins/native-refresh'),
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        $mainActivity = $files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt');
        $webViewManager = $files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt');

        $manifest = $files->get($buildPath.'/app/src/main/AndroidManifest.xml');
        $mainActivityManifest = (string) str($manifest)->between('android:name=".ui.MainActivity"', '</activity>');

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
            ->toContain('Intent.EXTRA_INITIAL_INTENTS')
            ->toContain('fun handleFileChooserResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean')
            ->toContain('fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean')
            ->toContain('(context as? MainActivity)?.finishPullRefresh()')
            ->toContain('url.startsWith("buff://")')
            ->not->toContain('nativephp://')
            ->and($files->get($buildPath.'/app/build.gradle.kts'))
            ->toContain('androidx.swiperefreshlayout:swiperefreshlayout:1.1.0')
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
    } finally {
        $files->deleteDirectory($buildPath);
    }
});

it('keeps valid orientation defaults for both native platforms', function (): void {
    expect(config('nativephp.ipad'))->toBeFalse()
        ->and(config('nativephp.orientation.iphone.portrait'))->toBeTrue()
        ->and(config('nativephp.orientation.android.portrait'))->toBeTrue();
});

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

    $files->ensureDirectoryExists(dirname($appDelegatePath));
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
