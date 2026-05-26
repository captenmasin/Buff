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

        if ($this->isIos()) {
            $this->installIosRefresh();
            $this->installIosAddShortcut();
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
            fn (string $content): string => str_replace(
                'android:data="nativephp://add"',
                'android:data="nativephp:///?add=1"',
                $content
            )
        );

        $this->info('Installed Android Add shortcut drawer target.');
    }

    private function installIosRefresh(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP/ContentView.swift',
            function (string $content): string {
                if (! str_contains($content, 'var refreshControl: UIRefreshControl?')) {
                    $content = str_replace(
                        "        var webView: WKWebView?\n        var hasCompletedInitialLoad = false\n",
                        "        var webView: WKWebView?\n        var refreshControl: UIRefreshControl?\n        var hasCompletedInitialLoad = false\n",
                        $content
                    );
                }

                if (! str_contains($content, 'refreshControl?.endRefreshing()')) {
                    $content = str_replace(
                        "            // Re-inject safe area insets to ensure they're set (like Android does)\n            injectSafeAreaInsets(webView)\n",
                        "            refreshControl?.endRefreshing()\n\n            // Re-inject safe area insets to ensure they're set (like Android does)\n            injectSafeAreaInsets(webView)\n",
                        $content
                    );
                }

                if (! str_contains($content, 'func refreshWebView()')) {
                    $content = str_replace(
                        "        @objc func reloadWebView() {\n            // Views are already cleared during persistent runtime reboot — just reload\n            self.webView?.reload()\n        }\n",
                        "        @objc func reloadWebView() {\n            // Views are already cleared during persistent runtime reboot — just reload\n            self.webView?.reload()\n        }\n\n        @objc func refreshWebView() {\n            self.webView?.reload()\n        }\n",
                        $content
                    );
                }

                if (! str_contains($content, 'func addPullToRefresh')) {
                    $content = str_replace(
                        "    func addSwipeGestureSupport(webView: WKWebView, context: Context) {\n",
                        "    func addPullToRefresh(webView: WKWebView, coordinator: Coordinator) {\n        if coordinator.refreshControl != nil {\n            return\n        }\n\n        let refreshControl = UIRefreshControl()\n        refreshControl.addTarget(coordinator, action: #selector(Coordinator.refreshWebView), for: .valueChanged)\n        webView.scrollView.refreshControl = refreshControl\n        coordinator.refreshControl = refreshControl\n    }\n\n    func addSwipeGestureSupport(webView: WKWebView, context: Context) {\n",
                        $content
                    );
                }

                if (! str_contains($content, 'addPullToRefresh(webView: existingWebView')) {
                    $content = str_replace(
                        "            existingWebView.alpha = 1.0\n\n            // Observers are still registered",
                        "            existingWebView.alpha = 1.0\n            addPullToRefresh(webView: existingWebView, coordinator: coordinator)\n\n            // Observers are still registered",
                        $content
                    );
                }

                if (! str_contains($content, 'addPullToRefresh(webView: webView')) {
                    $content = str_replace(
                        "        addNativeHelper(webView: webView)\n\n        addSwipeGestureSupport(webView: webView, context: context)\n",
                        "        addNativeHelper(webView: webView)\n\n        addPullToRefresh(webView: webView, coordinator: coordinator)\n\n        addSwipeGestureSupport(webView: webView, context: context)\n",
                        $content
                    );
                }

                return $content;
            }
        );

        $this->info('Installed iOS native pull-to-refresh.');
    }

    private function installIosAddShortcut(): void
    {
        $this->patchFile(
            $this->buildPath().'/NativePHP/AppDelegate.swift',
            fn (string $content): string => str_replace(
                'URL(string: "nativephp://add")',
                'URL(string: "nativephp:///?add=1")',
                $content
            )
        );

        $this->info('Installed iOS Add shortcut drawer target.');
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
