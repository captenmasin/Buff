<?php

namespace Buff\NativeRefresh\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

class InstallNativePullRefreshCommand extends NativePluginHookCommand
{
    protected $signature = 'native-refresh:install';

    protected $description = 'Install native pull-to-refresh into the generated NativePHP WebView shell.';

    public function handle(): int
    {
        if ($this->isAndroid()) {
            $this->installAndroidRefresh();
            $this->installAndroidAddShortcut();
        }

        return self::SUCCESS;
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
                        "    private lateinit var webViewManager: WebViewManager\n",
                        "    private lateinit var webViewManager: WebViewManager\n    private var swipeRefreshLayout: SwipeRefreshLayout? = null\n",
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
                        "                        AndroidView(\n                            factory = { webView },",
                        "                        AndroidView(\n                            factory = { context ->\n                                SwipeRefreshLayout(context).apply {\n                                    swipeRefreshLayout = this\n                                    setColorSchemeColors(android.graphics.Color.rgb(37, 61, 44))\n                                    setOnRefreshListener { webView.reload() }\n                                    (webView.parent as? ViewGroup)?.removeView(webView)\n                                    addView(\n                                        webView,\n                                        ViewGroup.LayoutParams(\n                                            ViewGroup.LayoutParams.MATCH_PARENT,\n                                            ViewGroup.LayoutParams.MATCH_PARENT\n                                        )\n                                    )\n                                }\n                            },",
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

    private function installAndroidAddShortcut(): void
    {
        $this->patchFile(
            $this->buildPath().'/app/src/main/res/xml/shortcuts.xml',
            fn (string $content): string => <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<shortcuts xmlns:android="http://schemas.android.com/apk/res/android">
    <shortcut
        android:shortcutId="add"
        android:enabled="true"
        android:icon="@mipmap/ic_launcher"
        android:shortcutShortLabel="@string/shortcut_add_short"
        android:shortcutLongLabel="@string/shortcut_add_long">
        <intent
            android:action="android.intent.action.VIEW"
            android:data="nativephp:///?add=1" />
    </shortcut>
    <shortcut
        android:shortcutId="scan"
        android:enabled="true"
        android:icon="@mipmap/ic_launcher"
        android:shortcutShortLabel="@string/shortcut_scan_short"
        android:shortcutLongLabel="@string/shortcut_scan_long">
        <intent
            android:action="android.intent.action.VIEW"
            android:data="nativephp://add?mode=food&amp;scan=1" />
    </shortcut>
    <shortcut
        android:shortcutId="workout"
        android:enabled="true"
        android:icon="@mipmap/ic_launcher"
        android:shortcutShortLabel="@string/shortcut_workout_short"
        android:shortcutLongLabel="@string/shortcut_workout_long">
        <intent
            android:action="android.intent.action.VIEW"
            android:data="nativephp://add?mode=workout" />
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
