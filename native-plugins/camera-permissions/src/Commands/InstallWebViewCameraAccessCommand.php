<?php

namespace Buff\CameraPermissions\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

class InstallWebViewCameraAccessCommand extends NativePluginHookCommand
{
    protected $signature = 'camera-permissions:install-webview-camera-access';

    protected $description = 'Allow Android WebView camera access and file selection.';

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

        if (! str_contains($content, 'androidx.core.content.FileProvider')) {
            $content = str_replace(
                "import androidx.core.content.ContextCompat\n",
                "import androidx.core.content.ContextCompat\nimport androidx.core.content.FileProvider\n",
                $content
            );
        }

        if (! str_contains($content, 'android.provider.MediaStore')) {
            $content = str_replace(
                "import android.net.Uri\n",
                "import android.net.Uri\nimport android.provider.MediaStore\n",
                $content
            );
        }

        if (! str_contains($content, 'java.io.File')) {
            $content = str_replace(
                "import com.nativephp.mobile.security.LaravelSecurity\n",
                "import com.nativephp.mobile.security.LaravelSecurity\nimport java.io.File\n",
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

        $legacyFileChooserFields = <<<'KOTLIN'
    private val fileChooserRequestCode = 45871
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var cameraCaptureUri: Uri? = null
KOTLIN;

        $fileChooserFields = <<<'KOTLIN'
    private val fileChooserRequestCode = 45871
    private val fileChooserCameraPermissionRequestCode = 45872
    private var filePathCallback: ValueCallback<Array<Uri>>? = null
    private var cameraCaptureUri: Uri? = null
    private var cameraCaptureFile: File? = null
    private var pendingFileChooserIntent: Intent? = null
KOTLIN;

        if (str_contains($content, $legacyFileChooserFields)) {
            $content = str_replace($legacyFileChooserFields, $fileChooserFields, $content);
        } elseif (! str_contains($content, 'fileChooserRequestCode')) {
            $content = str_replace(
                "    private var pendingCameraPermissionRequest: PermissionRequest? = null\n",
                "    private var pendingCameraPermissionRequest: PermissionRequest? = null\n{$fileChooserFields}\n",
                $content
            );
        }

        $legacyCameraPermissionResult = <<<'KOTLIN'
    fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean {
        if (requestCode != cameraPermissionRequestCode) {
            return false
        }

        val request = pendingCameraPermissionRequest ?: return true
        pendingCameraPermissionRequest = null

        if (grantResults.firstOrNull() == PackageManager.PERMISSION_GRANTED) {
            request.grant(request.resources ?: arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
            return true
        }

        request.deny()
        return true
    }
KOTLIN;

        $cameraPermissionResult = <<<'KOTLIN'
    fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean {
        if (requestCode == fileChooserCameraPermissionRequestCode) {
            val intent = pendingFileChooserIntent
            pendingFileChooserIntent = null
            val activity = context as? Activity

            if (
                grantResults.firstOrNull() == PackageManager.PERMISSION_GRANTED &&
                intent != null &&
                activity != null
            ) {
                try {
                    activity.startActivityForResult(intent, fileChooserRequestCode)
                    return true
                } catch (_: ActivityNotFoundException) {
                }
            }

            filePathCallback?.onReceiveValue(null)
            filePathCallback = null
            cameraCaptureUri = null
            cameraCaptureFile = null

            return true
        }

        if (requestCode != cameraPermissionRequestCode) {
            return false
        }

        val request = pendingCameraPermissionRequest ?: return true
        pendingCameraPermissionRequest = null

        if (grantResults.firstOrNull() == PackageManager.PERMISSION_GRANTED) {
            request.grant(request.resources ?: arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
            return true
        }

        request.deny()
        return true
    }
KOTLIN;

        if (str_contains($content, $legacyCameraPermissionResult)) {
            $content = str_replace($legacyCameraPermissionResult, $cameraPermissionResult, $content);
        } elseif (! str_contains($content, 'fun handleCameraPermissionResult(requestCode: Int, grantResults: IntArray): Boolean')) {
            $content = str_replace(
                "    private fun configureWebViewSettings() {\n",
                $cameraPermissionResult."\n\n    private fun configureWebViewSettings() {\n",
                $content
            );
        }

        $legacyFileChooserResult = <<<'KOTLIN'
    fun handleFileChooserResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean {
        if (requestCode != fileChooserRequestCode) {
            return false
        }

        val callback = filePathCallback ?: return true
        val result = if (resultCode == Activity.RESULT_OK) {
            WebChromeClient.FileChooserParams.parseResult(resultCode, data)
                ?: cameraCaptureUri?.let { arrayOf(it) }
        } else {
            null
        }

        callback.onReceiveValue(result)
        filePathCallback = null
        cameraCaptureUri = null

        return true
    }
KOTLIN;

        $fileChooserResult = <<<'KOTLIN'
    fun handleFileChooserResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean {
        if (requestCode != fileChooserRequestCode) {
            return false
        }

        val callback = filePathCallback ?: return true
        val capturedPhoto = cameraCaptureUri
            ?.takeIf { (cameraCaptureFile?.length() ?: 0L) > 0L }
            ?.let { arrayOf(it) }
        val selectedPhotos = data?.clipData?.let { clipData ->
            (0 until clipData.itemCount)
                .mapNotNull { index -> clipData.getItemAt(index).uri }
                .toTypedArray()
                .takeIf { it.isNotEmpty() }
        }
        val result = if (resultCode == Activity.RESULT_OK) {
            selectedPhotos
                ?: WebChromeClient.FileChooserParams.parseResult(resultCode, data)
                ?: capturedPhoto
        } else {
            null
        }

        callback.onReceiveValue(result)
        filePathCallback = null
        cameraCaptureUri = null
        cameraCaptureFile = null
        pendingFileChooserIntent = null

        return true
    }
KOTLIN;

        if (str_contains($content, $legacyFileChooserResult)) {
            $content = str_replace($legacyFileChooserResult, $fileChooserResult, $content);
        } elseif (! str_contains($content, 'fun handleFileChooserResult(requestCode: Int, resultCode: Int, data: Intent?): Boolean')) {
            $content = str_replace(
                "    private fun configureWebViewSettings() {\n",
                $fileChooserResult."\n\n    private fun configureWebViewSettings() {\n",
                $content
            );
        }

        $singleFileChooserResult = <<<'KOTLIN'
        val result = if (resultCode == Activity.RESULT_OK) {
            WebChromeClient.FileChooserParams.parseResult(resultCode, data) ?: capturedPhoto
        } else {
            null
        }
KOTLIN;

        $multipleFileChooserResult = <<<'KOTLIN'
        val selectedPhotos = data?.clipData?.let { clipData ->
            (0 until clipData.itemCount)
                .mapNotNull { index -> clipData.getItemAt(index).uri }
                .toTypedArray()
                .takeIf { it.isNotEmpty() }
        }
        val result = if (resultCode == Activity.RESULT_OK) {
            selectedPhotos
                ?: WebChromeClient.FileChooserParams.parseResult(resultCode, data)
                ?: capturedPhoto
        } else {
            null
        }
KOTLIN;

        $content = str_replace($singleFileChooserResult, $multipleFileChooserResult, $content);

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

        $legacyFileChooser = <<<'KOTLIN'
            override fun onShowFileChooser(
                webView: WebView,
                callback: ValueCallback<Array<Uri>>,
                params: FileChooserParams
            ): Boolean {
                val activity = context as? Activity ?: return false
                filePathCallback?.onReceiveValue(null)
                filePathCallback = callback

                val pickerIntent = params.createIntent()
                val acceptsImages = params.acceptTypes.any { it.isBlank() || it.startsWith("image/") }
                val cameraIntent = Intent(MediaStore.ACTION_IMAGE_CAPTURE).takeIf {
                    acceptsImages && it.resolveActivity(context.packageManager) != null
                }?.apply {
                    val photoFile = File(context.cacheDir, "webview-upload.jpg")
                    val photoUri = FileProvider.getUriForFile(
                        context,
                        "${context.packageName}.fileprovider",
                        photoFile
                    )

                    cameraCaptureUri = photoUri
                    putExtra(MediaStore.EXTRA_OUTPUT, photoUri)
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION)
                }

                val chooserIntent = Intent.createChooser(pickerIntent, "Take or choose photo").apply {
                    cameraIntent?.let { putExtra(Intent.EXTRA_INITIAL_INTENTS, arrayOf(it)) }
                }

                return try {
                    activity.startActivityForResult(chooserIntent, fileChooserRequestCode)
                    true
                } catch (_: ActivityNotFoundException) {
                    filePathCallback?.onReceiveValue(null)
                    filePathCallback = null
                    cameraCaptureUri = null
                    false
                }
            }

KOTLIN;

        $fileChooser = <<<'KOTLIN'
            override fun onShowFileChooser(
                webView: WebView,
                callback: ValueCallback<Array<Uri>>,
                params: FileChooserParams
            ): Boolean {
                val activity = context as? Activity ?: return false
                filePathCallback?.onReceiveValue(null)
                filePathCallback = callback
                cameraCaptureUri = null
                cameraCaptureFile = null
                pendingFileChooserIntent = null

                val pickerIntent = params.createIntent()
                val acceptsImages = params.acceptTypes.any { it.isBlank() || it.startsWith("image/") }

                if (!params.isCaptureEnabled || !acceptsImages) {
                    return try {
                        activity.startActivityForResult(pickerIntent, fileChooserRequestCode)
                        true
                    } catch (_: ActivityNotFoundException) {
                        filePathCallback?.onReceiveValue(null)
                        filePathCallback = null
                        false
                    }
                }

                val cameraIntent = Intent(MediaStore.ACTION_IMAGE_CAPTURE).takeIf {
                    it.resolveActivity(context.packageManager) != null
                } ?: return try {
                    activity.startActivityForResult(pickerIntent, fileChooserRequestCode)
                    true
                } catch (_: ActivityNotFoundException) {
                    filePathCallback?.onReceiveValue(null)
                    filePathCallback = null
                    false
                }

                val photoFile = File(context.cacheDir, "webview-upload.jpg").apply {
                    writeBytes(byteArrayOf())
                }
                val photoUri = FileProvider.getUriForFile(
                    context,
                    "${context.packageName}.fileprovider",
                    photoFile
                )

                cameraCaptureUri = photoUri
                cameraCaptureFile = photoFile
                cameraIntent.putExtra(MediaStore.EXTRA_OUTPUT, photoUri)
                cameraIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION)

                if (
                    ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) !=
                    PackageManager.PERMISSION_GRANTED
                ) {
                    pendingFileChooserIntent = cameraIntent
                    activity.requestPermissions(
                        arrayOf(Manifest.permission.CAMERA),
                        fileChooserCameraPermissionRequestCode
                    )

                    return true
                }

                return try {
                    activity.startActivityForResult(cameraIntent, fileChooserRequestCode)
                    true
                } catch (_: ActivityNotFoundException) {
                    filePathCallback?.onReceiveValue(null)
                    filePathCallback = null
                    cameraCaptureUri = null
                    cameraCaptureFile = null
                    false
                }
            }

KOTLIN;

        if (str_contains($content, $legacyFileChooser)) {
            $content = str_replace($legacyFileChooser, $fileChooser, $content);
        } elseif (! str_contains($content, 'override fun onShowFileChooser(')) {
            $content = str_replace(
                "            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {\n",
                $fileChooser."            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {\n",
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

        if (! str_contains($content, 'handleFileChooserResult(requestCode, resultCode, data)')) {
            $content = str_replace(
                "    override fun onRequestPermissionsResult(\n",
                "    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {\n        if (webRenderer?.manager?.handleFileChooserResult(requestCode, resultCode, data) == true) {\n            return\n        }\n\n        super.onActivityResult(requestCode, resultCode, data)\n    }\n\n    override fun onRequestPermissionsResult(\n",
                $content
            );
        }

        if (! str_contains($content, 'webRenderer?.manager?.handleCameraPermissionResult(requestCode, grantResults)')) {
            $content = str_replace(
                "        super.onRequestPermissionsResult(requestCode, permissions, grantResults)\n\n        // Post lifecycle event for each permission result\n",
                "        super.onRequestPermissionsResult(requestCode, permissions, grantResults)\n\n        if (webRenderer?.manager?.handleCameraPermissionResult(requestCode, grantResults) == true) {\n            return\n        }\n\n        // Post lifecycle event for each permission result\n",
                $content
            );
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
        }
    }
}
