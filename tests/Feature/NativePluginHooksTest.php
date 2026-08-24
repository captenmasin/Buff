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

        $this->artisan('native-refresh:install', [
            '--platform' => 'android',
            '--build-path' => $buildPath,
            '--plugin-path' => base_path('native-plugins/native-refresh'),
            '--app-id' => 'com.mason.buff',
        ])->assertSuccessful();

        $mainActivity = $files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt');
        $webViewManager = $files->get($buildPath.'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt');

        expect($mainActivity)
            ->toContain('webRenderer?.manager?.handleFileChooserResult(requestCode, resultCode, data) == true')
            ->toContain('webRenderer?.manager?.handleCameraPermissionResult(requestCode, grantResults) == true')
            ->toContain('private var swipeRefreshLayout: SwipeRefreshLayout? = null')
            ->toContain('SwipeRefreshLayout(context).apply')
            ->toContain('val webView = renderer.webView')
            ->toContain('fun finishPullRefresh()')
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
            ->and($files->get($buildPath.'/app/src/main/AndroidManifest.xml'))
            ->toContain('android.app.shortcuts')
            ->and($files->get($buildPath.'/app/src/main/res/xml/shortcuts.xml'))
            ->toContain('buff://add')
            ->toContain('buff://add?mode=food&amp;scan=1');
    } finally {
        $files->deleteDirectory($buildPath);
    }
});

it('keeps valid orientation defaults for both native platforms', function (): void {
    expect(config('nativephp.ipad'))->toBeFalse()
        ->and(config('nativephp.orientation.iphone.portrait'))->toBeTrue()
        ->and(config('nativephp.orientation.android.portrait'))->toBeTrue();
});
