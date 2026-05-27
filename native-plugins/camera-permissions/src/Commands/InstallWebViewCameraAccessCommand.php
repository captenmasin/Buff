<?php

namespace Buff\CameraPermissions\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

class InstallWebViewCameraAccessCommand extends NativePluginHookCommand
{
    protected $signature = 'camera-permissions:install-webview-camera-access';

    protected $description = 'Allow Android WebView camera requests when the app camera permission is granted.';

    public function handle(): int
    {
        if (! $this->isAndroid()) {
            return self::SUCCESS;
        }

        $this->patchWebViewManager();
        $this->patchMainActivity();

        return self::SUCCESS;
    }

    private function patchWebViewManager(): void
    {
        $path = $this->buildPath().'/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt';

        if (! is_file($path)) {
            $this->warn("Camera permission target not found: {$path}");

            return;
        }

        $original = file_get_contents($path);
        $content = $original;

        if (! str_contains($content, 'android.Manifest')) {
            $content = str_replace(
                "import android.content.ActivityNotFoundException\n",
                "import android.Manifest\nimport android.content.ActivityNotFoundException\n",
                $content
            );
        }

        if (! str_contains($content, 'android.content.pm.PackageManager')) {
            $content = str_replace(
                "import android.content.Intent\n",
                "import android.content.Intent\nimport android.content.pm.PackageManager\n",
                $content
            );
        }

        if (! str_contains($content, 'android.webkit.PermissionRequest')) {
            $content = str_replace(
                "import android.webkit.*\n",
                "import android.webkit.*\nimport android.webkit.PermissionRequest\n",
                $content
            );
        }

        if (! str_contains($content, 'androidx.core.content.ContextCompat')) {
            $content = str_replace(
                "import android.app.Activity\n",
                "import android.app.Activity\nimport androidx.core.content.ContextCompat\n",
                $content
            );
        }

        if (! str_contains($content, 'cameraPermissionRequestCode')) {
            $content = str_replace(
                "    private var customViewCallback: WebChromeClient.CustomViewCallback? = null\n",
                "    private var customViewCallback: WebChromeClient.CustomViewCallback? = null\n    private val cameraPermissionRequestCode = 45870\n    private var pendingCameraPermissionRequest: PermissionRequest? = null\n",
                $content
            );
        }

        if (! str_contains($content, 'fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean')) {
            $content = str_replace(
                "    private fun configureWebViewSettings() {\n",
                "    fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean {\n        if (requestCode != cameraPermissionRequestCode) {\n            return false\n        }\n\n        val request = pendingCameraPermissionRequest ?: return true\n        pendingCameraPermissionRequest = null\n\n        if (grantResults.firstOrNull() == PackageManager.PERMISSION_GRANTED) {\n            request.grant(request.resources ?: arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))\n            return true\n        }\n\n        request.deny()\n        return true\n    }\n\n    private fun configureWebViewSettings() {\n",
                $content
            );
        }

        $legacyPermissionRequest = <<<'KOTLIN'
            override fun onPermissionRequest(request: PermissionRequest) {
                val requestedResources = request.resources ?: emptyArray()
                val requestsCamera = requestedResources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)
                val hasCameraPermission = ContextCompat.checkSelfPermission(
                    context,
                    Manifest.permission.CAMERA
                ) == PackageManager.PERMISSION_GRANTED

                if (requestsCamera && hasCameraPermission) {
                    request.grant(requestedResources)
                    return
                }

                request.deny()
            }

KOTLIN;

        $runtimePermissionRequest = <<<'KOTLIN'
            override fun onPermissionRequest(request: PermissionRequest) {
                val requestedResources = request.resources ?: emptyArray()
                val requestsCamera = requestedResources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)

                if (!requestsCamera) {
                    request.deny()
                    return
                }

                val hasCameraPermission = ContextCompat.checkSelfPermission(
                    context,
                    Manifest.permission.CAMERA
                ) == PackageManager.PERMISSION_GRANTED

                if (hasCameraPermission) {
                    request.grant(requestedResources)
                    return
                }

                val activity = context as? Activity

                if (activity == null) {
                    request.deny()
                    return
                }

                pendingCameraPermissionRequest?.deny()
                pendingCameraPermissionRequest = request
                activity.requestPermissions(arrayOf(Manifest.permission.CAMERA), cameraPermissionRequestCode)
            }

KOTLIN;

        if (str_contains($content, $legacyPermissionRequest)) {
            $content = str_replace($legacyPermissionRequest, $runtimePermissionRequest, $content);
        }

        if (! str_contains($content, 'activity.requestPermissions(arrayOf(Manifest.permission.CAMERA), cameraPermissionRequestCode)')) {
            $content = str_replace(
                "            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {\n",
                $runtimePermissionRequest."            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {\n",
                $content
            );
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
        }

        $this->info('Installed Android WebView camera access handling.');
    }

    private function patchMainActivity(): void
    {
        $path = $this->buildPath().'/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt';

        if (! is_file($path)) {
            $this->warn("Camera permission target not found: {$path}");

            return;
        }

        $original = file_get_contents($path);
        $content = $original;

        if (! str_contains($content, 'webViewManager.handleCameraPermissionResult(requestCode, grantResults)')) {
            $content = str_replace(
                "        super.onRequestPermissionsResult(requestCode, permissions, grantResults)\n\n        // Post lifecycle event for each permission result\n",
                "        super.onRequestPermissionsResult(requestCode, permissions, grantResults)\n\n        if (::webViewManager.isInitialized && webViewManager.handleCameraPermissionResult(requestCode, grantResults)) {\n            return\n        }\n\n        // Post lifecycle event for each permission result\n",
                $content
            );
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
        }
    }
}
