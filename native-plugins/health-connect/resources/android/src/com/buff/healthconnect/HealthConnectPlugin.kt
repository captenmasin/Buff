package com.buff.healthconnect

import android.content.Context
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.permission.HealthPermission
import androidx.health.connect.client.records.ActiveCaloriesBurnedRecord
import androidx.health.connect.client.records.ExerciseSessionRecord
import androidx.health.connect.client.records.TotalCaloriesBurnedRecord
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

object HealthConnectPlugin {
    private const val PERIODIC_WORK_NAME = "buff-health-connect-sync"
    private const val IMMEDIATE_WORK_NAME = "buff-health-connect-sync-now"

    val requiredPermissions: Set<String> = setOf(
        HealthPermission.getReadPermission(ExerciseSessionRecord::class),
        HealthPermission.getReadPermission(TotalCaloriesBurnedRecord::class),
        HealthPermission.getReadPermission(ActiveCaloriesBurnedRecord::class),
        HealthPermission.PERMISSION_READ_HEALTH_DATA_IN_BACKGROUND
    )

    fun isAvailable(context: Context): Boolean {
        return HealthConnectClient.getSdkStatus(context) == HealthConnectClient.SDK_AVAILABLE
    }

    suspend fun hasAllPermissions(context: Context): Boolean {
        if (!isAvailable(context)) {
            return false
        }

        val granted = HealthConnectClient.getOrCreate(context)
            .permissionController
            .getGrantedPermissions()

        return granted.containsAll(requiredPermissions)
    }

    suspend fun status(context: Context): Map<String, Any> {
        val sdkStatus = HealthConnectClient.getSdkStatus(context)
        val available = sdkStatus == HealthConnectClient.SDK_AVAILABLE
        val granted = if (available) {
            HealthConnectClient.getOrCreate(context)
                .permissionController
                .getGrantedPermissions()
        } else {
            emptySet()
        }

        val foregroundPermissions = requiredPermissions - HealthPermission.PERMISSION_READ_HEALTH_DATA_IN_BACKGROUND
        val hasForegroundPermissions = granted.containsAll(foregroundPermissions)
        val backgroundGranted = granted.contains(HealthPermission.PERMISSION_READ_HEALTH_DATA_IN_BACKGROUND)

        return mapOf(
            "supported" to true,
            "available" to available,
            "sdk_status" to sdkStatus,
            "has_permissions" to (hasForegroundPermissions && backgroundGranted),
            "foreground_granted" to hasForegroundPermissions,
            "background_granted" to backgroundGranted,
            "status" to when {
                !available -> "unavailable"
                hasForegroundPermissions && backgroundGranted -> "connected"
                hasForegroundPermissions -> "background_permission_required"
                else -> "permission_required"
            }
        )
    }

    fun schedulePeriodicSync(context: Context) {
        val request = PeriodicWorkRequestBuilder<HealthConnectSyncWorker>(1, TimeUnit.HOURS)
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            PERIODIC_WORK_NAME,
            ExistingPeriodicWorkPolicy.UPDATE,
            request
        )
    }

    fun enqueueImmediateSync(context: Context) {
        val request = OneTimeWorkRequestBuilder<HealthConnectSyncWorker>().build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            IMMEDIATE_WORK_NAME,
            ExistingWorkPolicy.REPLACE,
            request
        )
    }
}
