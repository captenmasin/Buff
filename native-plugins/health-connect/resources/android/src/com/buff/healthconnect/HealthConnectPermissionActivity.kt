package com.buff.healthconnect

import android.os.Bundle
import androidx.fragment.app.FragmentActivity
import androidx.health.connect.client.PermissionController
import kotlinx.coroutines.runBlocking

class HealthConnectPermissionActivity : FragmentActivity() {
    private val permissionLauncher = registerForActivityResult(
        PermissionController.createRequestPermissionResultContract()
    ) {
        try {
            if (runBlocking { HealthConnectPlugin.hasAllPermissions(this@HealthConnectPermissionActivity) }) {
                HealthConnectPlugin.enqueueImmediateSync(applicationContext)
            }
        } catch (error: Throwable) {
            HealthConnectPlugin.logPermissionError(error)
        }

        finish()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (!HealthConnectPlugin.isAvailable(this)) {
            finish()
            return
        }

        try {
            val permissions = runBlocking {
                HealthConnectPlugin.permissionsToRequest(this@HealthConnectPermissionActivity)
            }

            if (permissions.isEmpty()) {
                finish()
                return
            }

            permissionLauncher.launch(permissions)
        } catch (error: Throwable) {
            HealthConnectPlugin.logPermissionError(error)
            finish()
        }
    }
}
