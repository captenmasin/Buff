package com.buff.healthconnect

import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import kotlinx.coroutines.runBlocking

object HealthConnectFunctions {
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

    class Schedule(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            HealthConnectPlugin.schedulePeriodicSync(context)

            return BridgeResponse.success(mapOf(
                "supported" to true,
                "available" to HealthConnectPlugin.isAvailable(context),
                "status" to "scheduled"
            ))
        }
    }
}
