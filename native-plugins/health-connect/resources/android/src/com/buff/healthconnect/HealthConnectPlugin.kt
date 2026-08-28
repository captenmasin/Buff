package com.buff.healthconnect

import android.content.Context
import android.util.Log
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.HealthConnectFeatures
import androidx.health.connect.client.permission.HealthPermission
import androidx.health.connect.client.records.ActiveCaloriesBurnedRecord
import androidx.health.connect.client.records.ExerciseSessionRecord
import androidx.health.connect.client.records.TotalCaloriesBurnedRecord
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager

object HealthConnectPlugin {
    private const val IMMEDIATE_WORK_NAME = "buff-health-connect-sync-now"

    @Volatile
    private var permissionsRevokedUntilRestart = false

    val foregroundPermissions: Set<String> = setOf(
        HealthPermission.getReadPermission(ExerciseSessionRecord::class),
        HealthPermission.getReadPermission(TotalCaloriesBurnedRecord::class),
        HealthPermission.getReadPermission(ActiveCaloriesBurnedRecord::class)
    )
    val backgroundPermission: String = HealthPermission.PERMISSION_READ_HEALTH_DATA_IN_BACKGROUND
    val requiredPermissions: Set<String> = foregroundPermissions + backgroundPermission

    fun isAvailable(context: Context): Boolean {
        return HealthConnectClient.getSdkStatus(context) == HealthConnectClient.SDK_AVAILABLE
    }

    suspend fun hasAllPermissions(context: Context): Boolean {
        if (permissionsRevokedUntilRestart || !isAvailable(context)) {
            return false
        }

        val granted = HealthConnectClient.getOrCreate(context)
            .permissionController
            .getGrantedPermissions()

        return granted.containsAll(foregroundPermissions) &&
            (!backgroundReadAvailable(context) || backgroundPermission in granted)
    }

    fun backgroundReadAvailable(context: Context): Boolean {
        if (!isAvailable(context)) {
            return false
        }

        return HealthConnectClient.getOrCreate(context)
            .features
            .getFeatureStatus(HealthConnectFeatures.FEATURE_READ_HEALTH_DATA_IN_BACKGROUND) == HealthConnectFeatures.FEATURE_STATUS_AVAILABLE
    }

    suspend fun permissionsToRequest(context: Context): Set<String> {
        if (!isAvailable(context)) {
            return emptySet()
        }

        if (permissionsRevokedUntilRestart) {
            return foregroundPermissions
        }

        val granted = HealthConnectClient.getOrCreate(context)
            .permissionController
            .getGrantedPermissions()

        val missingForeground = foregroundPermissions - granted

        if (missingForeground.isNotEmpty()) {
            return missingForeground
        }

        if (backgroundReadAvailable(context) && backgroundPermission !in granted) {
            return setOf(backgroundPermission)
        }

        return emptySet()
    }

    suspend fun status(context: Context): Map<String, Any> {
        val sdkStatus = HealthConnectClient.getSdkStatus(context)
        val available = sdkStatus == HealthConnectClient.SDK_AVAILABLE
        val granted = if (available && !permissionsRevokedUntilRestart) {
            HealthConnectClient.getOrCreate(context)
                .permissionController
                .getGrantedPermissions()
        } else {
            emptySet()
        }

        val hasForegroundPermissions = granted.containsAll(foregroundPermissions)
        val backgroundAvailable = backgroundReadAvailable(context)
        val backgroundGranted = granted.contains(backgroundPermission)
        val hasPermissions = hasForegroundPermissions && (!backgroundAvailable || backgroundGranted)

        return mapOf(
            "supported" to true,
            "available" to available,
            "sdk_status" to sdkStatus,
            "has_permissions" to hasPermissions,
            "foreground_granted" to hasForegroundPermissions,
            "background_granted" to backgroundGranted,
            "background_available" to backgroundAvailable,
            "status" to when {
                !available -> "unavailable"
                !hasForegroundPermissions -> "permission_required"
                backgroundAvailable && !backgroundGranted -> "background_permission_required"
                else -> "connected"
            }
        )
    }

    fun markPermissionsGranted() {
        permissionsRevokedUntilRestart = false
    }

    fun markPermissionsRevoked() {
        permissionsRevokedUntilRestart = true
    }

    fun enqueueImmediateSync(context: Context) {
        val request = OneTimeWorkRequestBuilder<HealthConnectSyncWorker>().build()

        WorkManager.getInstance(context).enqueueUniqueWork(
            IMMEDIATE_WORK_NAME,
            ExistingWorkPolicy.KEEP,
            request
        )
    }

    fun logPermissionError(error: Throwable) {
        Log.e("BuffHealthConnect", "Health Connect permission flow failed", error)
    }
}
