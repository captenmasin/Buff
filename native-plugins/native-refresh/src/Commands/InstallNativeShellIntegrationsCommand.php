<?php

namespace Buff\NativeRefresh\Commands;

use Dotenv\Dotenv;
use Native\Mobile\Plugins\Commands\NativePluginHookCommand;
use RuntimeException;
use ZipArchive;

class InstallNativeShellIntegrationsCommand extends NativePluginHookCommand
{
    protected $signature = 'native-shell:install';

    protected $description = 'Install Buff integrations into the generated NativePHP shell.';

    public function handle(): int
    {
        if ($this->isAndroid()) {
            $this->syncAndroidBuildMetadata();
            $this->installAndroidRefresh();
            $this->installAndroidRequestCapture();
            $this->installAndroidBackHandler();
            $this->installAndroidDeepLinkScheme();
            $this->installAndroidAddShortcut();
        }

        if ($this->isIos()) {
            $this->syncIosBuildMetadata();
            $this->installIosIcon();
            $this->installIosPrivacyManifest();
            $this->installIosRefresh();
            $this->installIosToastPosition();
            $this->installIosRootPathNormalization();
            $this->installIosShortcuts();
            $this->installIosSimulatorEntitlements();
        }

        return self::SUCCESS;
    }

    private function syncAndroidBuildMetadata(): void
    {
        $assetsPath = $this->buildPath().'/app/src/main/assets';
        $bundlePath = $assetsPath.'/laravel_bundle.zip';

        if (! is_file($bundlePath)) {
            return;
        }

        $bundle = new ZipArchive;

        if ($bundle->open($bundlePath) !== true) {
            throw new RuntimeException('Could not read the Android Laravel bundle version.');
        }

        $bundledEnvironment = $bundle->getFromName('.env');
        $bundle->close();

        if ($bundledEnvironment === false) {
            throw new RuntimeException('The Android Laravel bundle is missing its environment configuration.');
        }

        $environment = Dotenv::parse($bundledEnvironment);
        $version = (string) ($environment['NATIVEPHP_APP_VERSION'] ?? config('nativephp.version'));
        $versionCode = (string) ($environment['NATIVEPHP_APP_VERSION_CODE'] ?? config('nativephp.version_code'));

        if ($version === '' || ! ctype_digit($versionCode) || (int) $versionCode < 1) {
            throw new RuntimeException('NATIVEPHP_APP_VERSION and NATIVEPHP_APP_VERSION_CODE must be valid before building Android.');
        }

        $versionCode = (int) $versionCode;
        config([
            'nativephp.version' => $version,
            'nativephp.version_code' => $versionCode,
        ]);

        $this->patchFile(
            $this->buildPath().'/app/build.gradle.kts',
            fn (string $content): string => preg_replace(
                [
                    '/versionCode\s*=\s*(?:\d+|REPLACEMECODE)/',
                    '/versionName\s*=\s*(?:".*?"|"REPLACEME")/',
                ],
                [
                    "versionCode = {$versionCode}",
                    "versionName = \"{$version}\"",
                ],
                $content,
            ) ?? $content,
        );

        $bundleMetadataPath = $assetsPath.'/bundle_meta.json';

        if (is_file($bundleMetadataPath)) {
            $metadata = json_decode((string) file_get_contents($bundleMetadataPath), true, flags: JSON_THROW_ON_ERROR);
            $metadata['version'] = $version;
            $metadata['version_code'] = (string) $versionCode;
            file_put_contents($bundleMetadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }

        $bundleVersion = $version === 'DEBUG' ? 'DEBUG' : "{$version}b{$versionCode}";

        if ($bundle->open($bundlePath) !== true || ! $bundle->addFromString('.version', "{$bundleVersion}\n") || ! $bundle->close()) {
            throw new RuntimeException('Could not reconcile the Android Laravel bundle version.');
        }
    }

    private function syncIosBuildMetadata(): void
    {
        $assetsPath = $this->buildPath().'/NativePHP';
        $bundlePath = $assetsPath.'/app.zip';

        if (! is_file($bundlePath)) {
            return;
        }

        $project = (string) file_get_contents($this->buildPath().'/NativePHP.xcodeproj/project.pbxproj');

        if (! preg_match('/MARKETING_VERSION = "?([A-Za-z0-9.]+)"?;/', $project, $versionMatch)
            || ! preg_match('/CURRENT_PROJECT_VERSION = ([1-9][0-9]*);/', $project, $codeMatch)) {
            throw new RuntimeException('Could not read the iOS Xcode release version.');
        }

        $version = $versionMatch[1];
        $versionCode = (int) $codeMatch[1];
        $bundle = new ZipArchive;

        if ($bundle->open($bundlePath) !== true) {
            throw new RuntimeException('Could not read the iOS Laravel bundle version.');
        }

        $environment = $bundle->getFromName('.env');

        if ($environment === false) {
            $bundle->close();
            throw new RuntimeException('The iOS Laravel bundle is missing its environment configuration.');
        }

        $environment = preg_replace('/^NATIVEPHP_APP_VERSION(?:_CODE)?=.*\R?/m', '', $environment);
        $environment = rtrim($environment)."\nNATIVEPHP_APP_VERSION={$version}\nNATIVEPHP_APP_VERSION_CODE={$versionCode}\n";

        if (! $bundle->addFromString('.env', $environment) || ! $bundle->close()) {
            throw new RuntimeException('Could not reconcile the iOS Laravel bundle version.');
        }

        $metadataPath = $assetsPath.'/bundle_meta.json';
        $metadata = json_decode((string) file_get_contents($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $metadata['version'] = $version;
        $metadata['version_code'] = $versionCode;
        file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        file_put_contents($assetsPath.'/bundled.version', $version === 'DEBUG' ? 'DEBUG' : "{$version}b{$versionCode}");
    }

    private function installIosIcon(): void
    {
        $source = public_path('icon.icon');

        if (! is_dir($source)) {
            return;
        }

        $destination = $this->buildPath().'/NativePHP/AppIcon.icon';

        $this->delete($destination);
        $this->copyDirectory($source, $destination);
    }

    private function installIosPrivacyManifest(): void
    {
        $this->copyFile(
            $this->pluginPath().'/resources/ios/PrivacyInfo.xcprivacy',
            $this->buildPath().'/NativePHP/PrivacyInfo.xcprivacy',
        );
    }

    private function installIosRefresh(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP/ContentView.swift',
            function (string $content): string {
                if (str_contains($content, 'func addPullToRefresh(')) {
                    return $content;
                }

                $content = str_replace(
                    "        var hasCompletedInitialLoad = false\n",
                    <<<'SWIFT'
        var hasCompletedInitialLoad = false

        @objc func refreshWebView(_ refreshControl: UIRefreshControl) {
            webView?.reload()
        }
SWIFT."\n",
                    $content,
                );

                $content = str_replace(
                    "            existingWebView.alpha = 1.0\n",
                    "            existingWebView.alpha = 1.0\n            addPullToRefresh(webView: existingWebView, context: context)\n",
                    $content,
                );

                $content = str_replace(
                    "        addSwipeGestureSupport(webView: webView, context: context)\n",
                    "        addSwipeGestureSupport(webView: webView, context: context)\n        addPullToRefresh(webView: webView, context: context)\n",
                    $content,
                );

                $content = str_replace(
                    "            // Re-inject safe area insets to ensure they're set (like Android does)\n",
                    "            webView.scrollView.refreshControl?.endRefreshing()\n\n            // Re-inject safe area insets to ensure they're set (like Android does)\n",
                    $content,
                );

                return str_replace(
                    "    func addSwipeGestureSupport(webView: WKWebView, context: Context) {\n",
                    <<<'SWIFT'
    func addPullToRefresh(webView: WKWebView, context: Context) {
        guard webView.scrollView.refreshControl == nil else {
            return
        }

        let refreshControl = UIRefreshControl()
        refreshControl.addTarget(
            context.coordinator,
            action: #selector(Coordinator.refreshWebView(_:)),
            for: .valueChanged
        )
        webView.scrollView.refreshControl = refreshControl
    }

    func addSwipeGestureSupport(webView: WKWebView, context: Context) {
SWIFT."\n",
                    $content,
                );
            }
        );
    }

    private function installIosToastPosition(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP/Components/Toast.swift',
            fn (string $content): string => str_replace(
                [
                    '// Position toast at bottom, respecting safe area',
                    'let yPosition = window.frame.height - safeAreaInsets.bottom - toastHeight - 100',
                    'toastLabel.backgroundColor = UIColor.black.withAlphaComponent(0.6)',
                ],
                [
                    '// Keep confirmations clear of bottom banners and navigation.',
                    'let yPosition = safeAreaInsets.top + 16',
                    'toastLabel.backgroundColor = .darkGray',
                ],
                $content,
            ),
        );
    }

    private function installIosRootPathNormalization(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP/PHPSchemeHandler.swift',
            function (string $content): string {
                $content = str_replace(
                    '            uri = urlComponents?.percentEncodedPath ?? "/"',
                    "            let encodedPath = urlComponents?.percentEncodedPath ?? \"\"\n            uri = encodedPath.isEmpty ? \"/\" : encodedPath",
                    $content,
                );

                return str_replace(
                    <<<'SWIFT'
                    request.uri = location.trimmingCharacters(in: .whitespaces)
                    request.method = "GET"

                    // Fix root URL redirects: ensure php://127.0.0.1 has trailing slash
                    if request.uri == "php://127.0.0.1" {
                        request.uri = "php://127.0.0.1/"
                    }

                    // Perform an external redirect to the webview, not trying to pass the location to PHP again
                    if !request.uri.hasPrefix("http://") && !request.uri.hasPrefix("php://") {
                        let trimmedLocation = location.trimmingCharacters(in: .whitespaces)
SWIFT,
                    <<<'SWIFT'
                    let trimmedLocation = location.trimmingCharacters(in: .whitespaces)
                    let redirectComponents = URLComponents(string: trimmedLocation)
                    let encodedPath = redirectComponents?.percentEncodedPath ?? ""
                    request.uri = encodedPath.isEmpty ? "/" : (encodedPath.hasPrefix("/") ? encodedPath : "/\(encodedPath)")
                    request.query = redirectComponents?.percentEncodedQuery
                    request.method = "GET"

                    // Perform an external redirect to the webview, not trying to pass the location to PHP again
                    if !trimmedLocation.hasPrefix("http://") && !trimmedLocation.hasPrefix("php://") {
SWIFT,
                    $content,
                );
            },
        );
    }

    private function installIosShortcuts(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP/AppDelegate.swift',
            function (string $content): string {
                if (str_contains($content, 'performActionFor shortcutItem')) {
                    return $content;
                }

                return str_replace(
                    "    static let shared = AppDelegate()\n",
                    <<<'SWIFT'
    static let shared = AppDelegate()

    func application(
        _ application: UIApplication,
        performActionFor shortcutItem: UIApplicationShortcutItem,
        completionHandler: @escaping (Bool) -> Void
    ) {
        guard let url = URL(string: shortcutItem.type) else {
            completionHandler(false)
            return
        }

        DeepLinkRouter.shared.handle(url: url)
        completionHandler(true)
    }
SWIFT."\n",
                    $content,
                );
            }
        );

        $shortcuts = <<<'PLIST'
	<key>UIApplicationShortcutItems</key>
	<array>
		<dict>
			<key>UIApplicationShortcutItemType</key>
			<string>buff://add</string>
			<key>UIApplicationShortcutItemTitle</key>
			<string>Add</string>
			<key>UIApplicationShortcutItemIconType</key>
			<string>UIApplicationShortcutIconTypeCompose</string>
		</dict>
		<dict>
			<key>UIApplicationShortcutItemType</key>
			<string>buff://add?mode=food&amp;scan=1</string>
			<key>UIApplicationShortcutItemTitle</key>
			<string>Scan</string>
			<key>UIApplicationShortcutItemIconType</key>
			<string>UIApplicationShortcutIconTypeCapturePhoto</string>
		</dict>
		<dict>
			<key>UIApplicationShortcutItemType</key>
			<string>buff://add?mode=workout</string>
			<key>UIApplicationShortcutItemTitle</key>
			<string>Add workout</string>
			<key>UIApplicationShortcutItemIconType</key>
			<string>UIApplicationShortcutIconTypeTime</string>
		</dict>
	</array>
PLIST;

        foreach (['Info.plist', '../NativePHP-simulator-Info.plist'] as $plist) {
            $this->patchFile(
                $this->buildPath().'/NativePHP/'.$plist,
                fn (string $content): string => str_contains($content, '<key>UIApplicationShortcutItems</key>')
                    ? $content
                    : str_replace("</dict>\n</plist>", $shortcuts."\n</dict>\n</plist>", $content),
            );
        }
    }

    private function installIosSimulatorEntitlements(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP.xcodeproj/project.pbxproj',
            fn (string $content): string => preg_replace_callback(
                '/buildSettings = \{.*?INFOPLIST_FILE = "NativePHP-simulator-Info\.plist";.*?\n\t\t\t\};/s',
                fn (array $matches): string => str_contains($matches[0], 'CODE_SIGN_ENTITLEMENTS')
                    ? $matches[0]
                    : str_replace(
                        "\t\t\t\tCODE_SIGN_STYLE = Automatic;",
                        "\t\t\t\tCODE_SIGN_ENTITLEMENTS = NativePHP/NativePHP.entitlements;\n\t\t\t\tCODE_SIGN_STYLE = Automatic;",
                        $matches[0],
                    ),
                $content,
            ),
        );
    }

    private function installAndroidRefresh(): void
    {
        $this->patchFile(
            $this->buildPath().'/app/build.gradle.kts',
            function (string $content): string {
                if (str_contains($content, 'androidx.swiperefreshlayout:swiperefreshlayout')) {
                    return $content;
                }

                return str_replace(
                    "    implementation(\"androidx.compose.ui:ui-viewbinding\")\n",
                    "    implementation(\"androidx.compose.ui:ui-viewbinding\")\n    implementation(\"androidx.swiperefreshlayout:swiperefreshlayout:1.1.0\")\n",
                    $content
                );
            }
        );

        $this->patchFile(
            $this->buildPath().'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt',
            function (string $content): string {
                if (! str_contains($content, 'androidx.swiperefreshlayout.widget.SwipeRefreshLayout')) {
                    $content = str_replace(
                        "import android.webkit.WebView\n",
                        "import android.webkit.WebView\nimport androidx.swiperefreshlayout.widget.SwipeRefreshLayout\n",
                        $content
                    );
                }

                if (! str_contains($content, 'private var swipeRefreshLayout: SwipeRefreshLayout? = null')) {
                    $content = str_replace(
                        "    private var webRenderer by mutableStateOf<com.nativephp.mobile.network.WebRenderer?>(null)\n",
                        "    private var webRenderer by mutableStateOf<com.nativephp.mobile.network.WebRenderer?>(null)\n    private var swipeRefreshLayout: SwipeRefreshLayout? = null\n",
                        $content
                    );
                }

                if (! str_contains($content, 'fun finishPullRefresh()')) {
                    $content = str_replace(
                        "    override fun getWebView(): WebView {\n",
                        "    fun finishPullRefresh() {\n        swipeRefreshLayout?.isRefreshing = false\n    }\n\n    override fun getWebView(): WebView {\n",
                        $content
                    );
                }

                if (! str_contains($content, 'SwipeRefreshLayout(context).apply')) {
                    $content = str_replace(
                        "                                AndroidView(\n                                    factory = { renderer.webView },",
                        "                                AndroidView(\n                                    factory = { context ->\n                                        SwipeRefreshLayout(context).apply {\n                                            swipeRefreshLayout = this\n                                            setColorSchemeColors(android.graphics.Color.rgb(37, 61, 44))\n                                            val webView = renderer.webView\n                                            setOnRefreshListener { webView.reload() }\n                                            (webView.parent as? ViewGroup)?.removeView(webView)\n                                            addView(\n                                                webView,\n                                                ViewGroup.LayoutParams(\n                                                    ViewGroup.LayoutParams.MATCH_PARENT,\n                                                    ViewGroup.LayoutParams.MATCH_PARENT\n                                                )\n                                            )\n                                        }\n                                    },",
                        $content
                    );
                }

                return $content;
            }
        );

        $this->patchFile(
            $this->buildPath().'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt',
            function (string $content): string {
                if (str_contains($content, 'finishPullRefresh()')) {
                    return $content;
                }

                return str_replace(
                    "                // Inject safe area insets again to ensure they're set\n                (context as? MainActivity)?.injectSafeAreaInsetsToWebView()\n",
                    "                // Inject safe area insets again to ensure they're set\n                (context as? MainActivity)?.injectSafeAreaInsetsToWebView()\n                (context as? MainActivity)?.finishPullRefresh()\n",
                    $content
                );
            }
        );

        $this->info('Installed Android native pull-to-refresh.');
    }

    private function installAndroidRequestCapture(): void
    {
        $this->patchFile(
            $this->buildPath().'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt',
            function (string $content): string {
                if (str_contains($content, 'private fun nativeJavaScript(): String')) {
                    return $content;
                }

                $replacements = [
                    "        setupJavaScriptInterfaces()\n" => <<<'KOTLIN'
        setupJavaScriptInterfaces()
        if (androidx.webkit.WebViewFeature.isFeatureSupported(androidx.webkit.WebViewFeature.DOCUMENT_START_SCRIPT)) {
            androidx.webkit.WebViewCompat.addDocumentStartJavaScript(
                webView, nativeJavaScript(), setOf("http://127.0.0.1")
            )
        }
KOTLIN."\n",
                    "                injectJavaScript(view)\n" => <<<'KOTLIN'
                if (!androidx.webkit.WebViewFeature.isFeatureSupported(androidx.webkit.WebViewFeature.DOCUMENT_START_SCRIPT)) {
                    injectJavaScript(view)
                }
KOTLIN."\n",
                    "    private fun injectJavaScript(view: WebView) {\n        val jsCode = \"\"\"" => '    private fun nativeJavaScript(): String = """',
                    'observer.observe(document.body, {' => 'observer.observe(document, {',
                    '        view.evaluateJavascript(jsCode) { result ->' => "    private fun injectJavaScript(view: WebView) {\n        view.evaluateJavascript(nativeJavaScript()) { result ->",
                ];

                foreach ($replacements as $before => $after) {
                    if (substr_count($content, $before) !== 1) {
                        throw new RuntimeException('NativePHP Android request capture changed; review the document-start integration before building.');
                    }

                    $content = str_replace($before, $after, $content);
                }

                return $content;
            },
        );
    }

    private function installAndroidBackHandler(): void
    {
        $this->patchFile(
            $this->buildPath().'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt',
            function (string $content): string {
                if (str_contains($content, '__buffHandleAndroidBack')) {
                    return $content;
                }

                return str_replace(
                    <<<'KOTLIN'
            val web = webRenderer?.webView
            if (web?.canGoBack() == true) {
                web.goBack()
            } else {
                finish()
            }
KOTLIN,
                    <<<'KOTLIN'
            val web = webRenderer?.webView
            if (web == null) {
                finish()
                return@addCallback
            }

            web.evaluateJavascript(
                "(function () { return window.__buffHandleAndroidBack ? window.__buffHandleAndroidBack() : false; })();"
            ) { handled ->
                if (handled != "true") {
                    if (web.canGoBack()) {
                        web.goBack()
                    } else {
                        finish()
                    }
                }
            }
KOTLIN,
                    $content,
                );
            }
        );
    }

    private function installAndroidDeepLinkScheme(): void
    {
        $scheme = (string) config('nativephp.deeplink_scheme');

        if ($scheme === '') {
            return;
        }

        $this->patchFile(
            $this->buildPath().'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt',
            fn (string $content): string => str_replace(
                ['nativephp://', 'uri.scheme == "nativephp"'],
                ["{$scheme}://", "uri.scheme == \"{$scheme}\""],
                $content,
            ),
        );
        $this->patchFile(
            $this->buildPath().'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt',
            fn (string $content): string => str_replace('nativephp://', "{$scheme}://", $content),
        );
    }

    private function installAndroidAddShortcut(): void
    {
        $this->patchFile(
            $this->buildPath().'/app/src/main/AndroidManifest.xml',
            function (string $content): string {
                $metadata = "            <meta-data\n                android:name=\"android.app.shortcuts\"\n                android:resource=\"@xml/shortcuts\" />\n";

                $content = preg_replace(
                    '/\s*<meta-data\b[^>]*android:name="android\.app\.shortcuts"[^>]*\/>/',
                    '',
                    $content,
                ) ?? $content;

                return preg_replace(
                    '/(<activity\b[^>]*android:name="\.ui\.MainActivity"[\s\S]*?)(\s*<\/activity>)/',
                    "$1\n{$metadata}        </activity>",
                    $content,
                    1,
                ) ?? $content;
            }
        );

        $shortcutsPath = $this->buildPath().'/app/src/main/res/xml/shortcuts.xml';

        if (! is_dir(dirname($shortcutsPath))) {
            mkdir(dirname($shortcutsPath), 0755, true);
        }

        file_put_contents($shortcutsPath, <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<shortcuts xmlns:android="http://schemas.android.com/apk/res/android">
    <shortcut
        android:shortcutId="add"
        android:enabled="true"
        android:icon="@mipmap/shortcut_add"
        android:shortcutShortLabel="@string/shortcut_add_short"
        android:shortcutLongLabel="@string/shortcut_add_long">
        <intent
            android:action="android.intent.action.VIEW"
            android:data="buff://add" />
    </shortcut>
    <shortcut
        android:shortcutId="scan"
        android:enabled="true"
        android:icon="@mipmap/shortcut_scan"
        android:shortcutShortLabel="@string/shortcut_scan_short"
        android:shortcutLongLabel="@string/shortcut_scan_long">
        <intent
            android:action="android.intent.action.VIEW"
            android:data="buff://add?mode=food&amp;scan=1" />
    </shortcut>
    <shortcut
        android:shortcutId="workout"
        android:enabled="true"
        android:icon="@mipmap/shortcut_workout"
        android:shortcutShortLabel="@string/shortcut_workout_short"
        android:shortcutLongLabel="@string/shortcut_workout_long">
        <intent
            android:action="android.intent.action.VIEW"
            android:data="buff://add?mode=workout" />
    </shortcut>
</shortcuts>
XML
        );

        $this->patchFile(
            $this->buildPath().'/app/src/main/res/values/strings.xml',
            function (string $content): string {
                $strings = [
                    'shortcut_add_short' => 'Add',
                    'shortcut_add_long' => 'Add to Buff',
                    'shortcut_scan_short' => 'Scan',
                    'shortcut_scan_long' => 'Scan barcode',
                    'shortcut_workout_short' => 'Add workout',
                    'shortcut_workout_long' => 'Add workout',
                ];

                foreach ($strings as $name => $value) {
                    if (str_contains($content, "name=\"{$name}\"")) {
                        continue;
                    }

                    $content = str_replace(
                        '</resources>',
                        "    <string name=\"{$name}\">{$value}</string>\n</resources>",
                        $content
                    );
                }

                return $content;
            }
        );

        $this->info('Installed Android Add, Scan, and Add workout shortcut targets.');
    }

    private function patchFile(string $path, callable $patch): void
    {
        if (! is_file($path)) {
            $this->warn("Native refresh target not found: {$path}");

            return;
        }

        $original = file_get_contents($path);
        $patched = $patch($original);

        if ($patched !== $original) {
            file_put_contents($path, $patched);
        }
    }
}
