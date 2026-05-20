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

        if (! str_contains($content, 'override fun onPermissionRequest(request: PermissionRequest)')) {
            $content = str_replace(
                "            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {\n",
                "            override fun onPermissionRequest(request: PermissionRequest) {\n                val requestedResources = request.resources ?: emptyArray()\n                val requestsCamera = requestedResources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)\n                val hasCameraPermission = ContextCompat.checkSelfPermission(\n                    context,\n                    Manifest.permission.CAMERA\n                ) == PackageManager.PERMISSION_GRANTED\n\n                if (requestsCamera && hasCameraPermission) {\n                    request.grant(requestedResources)\n                    return\n                }\n\n                request.deny()\n            }\n\n            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {\n",
                $content
            );
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
        }

        $this->info('Installed Android WebView camera access handling.');
    }
}
