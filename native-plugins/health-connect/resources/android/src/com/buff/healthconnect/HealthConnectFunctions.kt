package com.buff.healthconnect

import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper
import androidx.fragment.app.FragmentActivity
import androidx.health.connect.client.HealthConnectClient
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext

object HealthConnectFunctions {
    private const val MANAGE_HEALTH_PERMISSIONS = "android.health.connect.action.MANAGE_HEALTH_PERMISSIONS"

    class Status(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = runBlocking {
            BridgeResponse.success(HealthConnectPlugin.status(context))
        }
    }

    class RequestPermissions(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = runBlocking {
            if (!HealthConnectPlugin.isAvailable(activity)) {
                return@runBlocking BridgeResponse.success(mapOf(
                    "supported" to true,
                    "available" to false,
                    "status" to "unavailable",
                    "message" to "Health Connect is not available on this device."
                ))
            }

            if (HealthConnectPlugin.permissionsToRequest(activity).isEmpty()) {
                return@runBlocking BridgeResponse.success(
                    HealthConnectPlugin.status(activity) + mapOf(
                        "status" to "connected",
                        "message" to "Health Connect is connected."
                    )
                )
            }

            Handler(Looper.getMainLooper()).post {
                activity.startActivity(Intent(activity, HealthConnectPermissionActivity::class.java))
            }

            return@runBlocking BridgeResponse.success(mapOf(
                "supported" to true,
                "available" to true,
                "status" to "permission_requested"
            ))
        }
    }

    class SyncNow(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = runBlocking {
            if (!HealthConnectPlugin.hasAllPermissions(context)) {
                return@runBlocking BridgeResponse.success(mapOf(
                    "supported" to true,
                    "available" to HealthConnectPlugin.isAvailable(context),
                    "has_permissions" to false,
                    "status" to "permission_required"
                ))
            }

            HealthConnectPlugin.enqueueImmediateSync(context)

            BridgeResponse.success(mapOf(
                "supported" to true,
                "available" to true,
                "has_permissions" to true,
                "status" to "sync_queued"
            ))
        }
    }

    class ManageAccess(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = runBlocking {
            if (!HealthConnectPlugin.isAvailable(activity)) {
                return@runBlocking BridgeResponse.success(mapOf(
                    "supported" to true,
                    "available" to false,
                    "message" to "Health Connect is not available on this device."
                ))
            }

            val appPermissions = Intent(MANAGE_HEALTH_PERMISSIONS)
                .putExtra(Intent.EXTRA_PACKAGE_NAME, activity.packageName)
            val intent = if (appPermissions.resolveActivity(activity.packageManager) != null) {
                appPermissions
            } else {
                Intent(HealthConnectClient.ACTION_HEALTH_CONNECT_SETTINGS)
            }

            try {
                withContext(Dispatchers.Main.immediate) {
                    activity.startActivity(intent)
                }
            } catch (exception: Exception) {
                return@runBlocking BridgeResponse.success(mapOf(
                    "supported" to true,
                    "available" to true,
                    "manage_access_opened" to false,
                    "last_error" to "Could not open Health Connect access settings. Open Health Connect from Android Settings."
                ))
            }

            BridgeResponse.success(mapOf(
                "supported" to true,
                "available" to true,
                "manage_access_opened" to true,
                "message" to "Update Buff's Health Connect access, then return to Buff."
            ))
        }
    }

    class Disconnect(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = runBlocking {
            if (HealthConnectPlugin.isAvailable(context)) {
                HealthConnectClient.getOrCreate(context)
                    .permissionController
                    .revokeAllPermissions()
            }

            HealthConnectPlugin.markPermissionsRevoked()

            BridgeResponse.success(
                HealthConnectPlugin.status(context) + mapOf(
                    "message" to "Health Connect disconnected."
                )
            )
        }
    }

}
