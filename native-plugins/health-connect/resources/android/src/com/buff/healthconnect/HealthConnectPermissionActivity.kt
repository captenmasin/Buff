package com.buff.healthconnect

import android.os.Bundle
import androidx.fragment.app.FragmentActivity
import androidx.health.connect.client.PermissionController

class HealthConnectPermissionActivity : FragmentActivity() {
    private val permissionLauncher = registerForActivityResult(
        PermissionController.createRequestPermissionResultContract()
    ) {
        HealthConnectPlugin.schedulePeriodicSync(applicationContext)
        HealthConnectPlugin.enqueueImmediateSync(applicationContext)
        finish()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (!HealthConnectPlugin.isAvailable(this)) {
            finish()
            return
        }

        permissionLauncher.launch(HealthConnectPlugin.requiredPermissions)
    }
}
