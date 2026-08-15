package com.buff.healthconnect

import android.content.Context
import android.util.Log
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.records.ActiveCaloriesBurnedRecord
import androidx.health.connect.client.records.ExerciseSessionRecord
import androidx.health.connect.client.records.TotalCaloriesBurnedRecord
import androidx.health.connect.client.request.AggregateRequest
import androidx.health.connect.client.request.ReadRecordsRequest
import androidx.health.connect.client.time.TimeRangeFilter
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.nativephp.mobile.bridge.LaravelEnvironment
import com.nativephp.mobile.bridge.PHPBridge
import org.json.JSONArray
import org.json.JSONObject
import java.io.File
import java.time.Instant
import java.time.ZoneId
import java.time.temporal.ChronoUnit

class HealthConnectSyncWorker(
    appContext: Context,
    workerParams: WorkerParameters
) : CoroutineWorker(appContext, workerParams) {
    private data class ImportedInterval(
        val start: Instant,
        val end: Instant
    )

    override suspend fun doWork(): Result {
        if (!HealthConnectPlugin.isAvailable(applicationContext)) {
            return Result.success()
        }

        if (!HealthConnectPlugin.hasAllPermissions(applicationContext)) {
            return Result.success()
        }

        return try {
            val payload = readPayload()
            val file = File(applicationContext.cacheDir, "buff-health-connect-${System.currentTimeMillis()}.json")
            file.writeText(payload.toString())

            LaravelEnvironment(applicationContext).initializeForBackground()
            try {
                val output = runImportCommand(file)
                Log.d("BuffHealthConnect", "Import output: ${output.take(300)}")
            } finally {
                file.delete()
            }

            Result.success()
        } catch (error: Exception) {
            Log.e("BuffHealthConnect", "Health Connect sync failed", error)
            Result.retry()
        }
    }

    private fun runImportCommand(file: File): String {
        val bridge = PHPBridge(applicationContext)
        val bootstrap = "${bridge.getLaravelPath()}/vendor/nativephp/mobile/bootstrap/android/persistent.php"

        if (bridge.nativeEphemeralBoot(bootstrap) != 0) {
            throw IllegalStateException("Could not boot NativePHP ephemeral runtime.")
        }

        return try {
            val output = bridge.nativeEphemeralArtisan("health-connect:import --payload=${file.absolutePath}")

            if (!output.contains("BUFF_HEALTH_CONNECT_IMPORT_OK")) {
                throw IllegalStateException(output.trim().ifEmpty {
                    "Health Connect import did not report success."
                })
            }

            output
        } finally {
            bridge.nativeEphemeralShutdown()
        }
    }

    private suspend fun readPayload(): JSONObject {
        val client = HealthConnectClient.getOrCreate(applicationContext)
        val end = Instant.now()
        val start = end.minus(30, ChronoUnit.DAYS)
        val records = JSONArray()
        val importedIntervals = mutableListOf<ImportedInterval>()
        var sessionsRead = 0
        var sessionsWithCalories = 0
        var pageToken: String? = null

        do {
            val response = client.readRecords(
                ReadRecordsRequest(
                    recordType = ExerciseSessionRecord::class,
                    timeRangeFilter = TimeRangeFilter.between(start, end),
                    pageToken = pageToken
                )
            )

            response.records.forEach { session ->
                sessionsRead++
                workoutJson(client, session)?.let {
                    sessionsWithCalories++
                    records.put(it)
                    importedIntervals.add(ImportedInterval(session.startTime, session.endTime))
                }
            }

            pageToken = response.pageToken
        } while (pageToken != null)

        Log.d(
            "BuffHealthConnect",
            "Read $sessionsRead Health Connect exercise sessions; importing $sessionsWithCalories with calories."
        )

        val totalCaloriesRead = appendTotalCaloriesRecords(client, start, end, records, importedIntervals)
        val activeCaloriesRead = appendActiveCaloriesRecords(client, start, end, records, importedIntervals)

        Log.d(
            "BuffHealthConnect",
            "Read $totalCaloriesRead total calorie records and $activeCaloriesRead active calorie records; payload has ${records.length()} records."
        )

        return JSONObject()
            .put("synced_at", Instant.now().toString())
            .put("window_start", start.toString())
            .put("window_end", end.toString())
            .put("records", records)
    }

    private suspend fun workoutJson(
        client: HealthConnectClient,
        session: ExerciseSessionRecord
    ): JSONObject? {
        val sourcePackage = session.metadata.dataOrigin.packageName
        val calories = caloriesForSession(client, session)

        if (calories == null || calories < 1) {
            Log.d(
                "BuffHealthConnect",
                "Skipping session ${session.metadata.id} from $sourcePackage; no positive calories found."
            )
            return null
        }

        val zone = session.startZoneOffset ?: ZoneId.systemDefault().rules.getOffset(session.startTime)
        val localStart = session.startTime.atOffset(zone)

        return JSONObject()
            .put("external_id", session.metadata.id)
            .put("title", session.title?.takeIf { it.isNotBlank() } ?: exerciseLabel(session.exerciseType))
            .put("calories_burned", calories)
            .put("date", localStart.toLocalDate().toString())
            .put("started_at", localStart.toString())
            .put("ended_at", session.endTime.atOffset(session.endZoneOffset ?: zone).toString())
            .put("duration_seconds", ChronoUnit.SECONDS.between(session.startTime, session.endTime))
            .put("source_name", sourcePackage)
            .put("source_package", sourcePackage)
    }

    private suspend fun appendTotalCaloriesRecords(
        client: HealthConnectClient,
        start: Instant,
        end: Instant,
        records: JSONArray,
        importedIntervals: MutableList<ImportedInterval>
    ): Int {
        var recordsRead = 0
        var pageToken: String? = null

        do {
            val response = client.readRecords(
                ReadRecordsRequest(
                    recordType = TotalCaloriesBurnedRecord::class,
                    timeRangeFilter = TimeRangeFilter.between(start, end),
                    pageToken = pageToken
                )
            )

            response.records.forEach { record ->
                recordsRead++

                calorieRecordJson(
                    externalId = "total-calories:${record.metadata.id}",
                    sourcePackage = record.metadata.dataOrigin.packageName,
                    startTime = record.startTime,
                    endTime = record.endTime,
                    startZoneOffset = record.startZoneOffset,
                    endZoneOffset = record.endZoneOffset,
                    calories = record.energy.inKilocalories,
                    importedIntervals = importedIntervals
                )?.let {
                    records.put(it)
                    importedIntervals.add(ImportedInterval(record.startTime, record.endTime))
                }
            }

            pageToken = response.pageToken
        } while (pageToken != null)

        return recordsRead
    }

    private suspend fun appendActiveCaloriesRecords(
        client: HealthConnectClient,
        start: Instant,
        end: Instant,
        records: JSONArray,
        importedIntervals: MutableList<ImportedInterval>
    ): Int {
        var recordsRead = 0
        var pageToken: String? = null

        do {
            val response = client.readRecords(
                ReadRecordsRequest(
                    recordType = ActiveCaloriesBurnedRecord::class,
                    timeRangeFilter = TimeRangeFilter.between(start, end),
                    pageToken = pageToken
                )
            )

            response.records.forEach { record ->
                recordsRead++

                calorieRecordJson(
                    externalId = "active-calories:${record.metadata.id}",
                    sourcePackage = record.metadata.dataOrigin.packageName,
                    startTime = record.startTime,
                    endTime = record.endTime,
                    startZoneOffset = record.startZoneOffset,
                    endZoneOffset = record.endZoneOffset,
                    calories = record.energy.inKilocalories,
                    importedIntervals = importedIntervals
                )?.let {
                    records.put(it)
                    importedIntervals.add(ImportedInterval(record.startTime, record.endTime))
                }
            }

            pageToken = response.pageToken
        } while (pageToken != null)

        return recordsRead
    }

    private fun calorieRecordJson(
        externalId: String,
        sourcePackage: String,
        startTime: Instant,
        endTime: Instant,
        startZoneOffset: java.time.ZoneOffset?,
        endZoneOffset: java.time.ZoneOffset?,
        calories: Double,
        importedIntervals: List<ImportedInterval>
    ): JSONObject? {
        val roundedCalories = Math.round(calories).toInt()

        if (roundedCalories < 1 || !startTime.isBefore(endTime) || overlapsImportedInterval(startTime, endTime, importedIntervals)) {
            return null
        }

        val zone = startZoneOffset ?: ZoneId.systemDefault().rules.getOffset(startTime)
        val localStart = startTime.atOffset(zone)

        return JSONObject()
            .put("external_id", externalId)
            .put("title", if (sourcePackage.contains("samsung", ignoreCase = true)) "Samsung Health workout" else "Health Connect workout")
            .put("calories_burned", roundedCalories)
            .put("date", localStart.toLocalDate().toString())
            .put("started_at", localStart.toString())
            .put("ended_at", endTime.atOffset(endZoneOffset ?: zone).toString())
            .put("duration_seconds", ChronoUnit.SECONDS.between(startTime, endTime))
            .put("source_name", sourcePackage)
            .put("source_package", sourcePackage)
    }

    private fun overlapsImportedInterval(
        start: Instant,
        end: Instant,
        importedIntervals: List<ImportedInterval>
    ): Boolean {
        return importedIntervals.any { interval ->
            start.isBefore(interval.end) && end.isAfter(interval.start)
        }
    }

    private suspend fun caloriesForSession(
        client: HealthConnectClient,
        session: ExerciseSessionRecord
    ): Int? {
        val origins = setOf(session.metadata.dataOrigin)
        val range = TimeRangeFilter.between(session.startTime, session.endTime)

        val filteredTotal = client.aggregate(
            AggregateRequest(
                metrics = setOf(TotalCaloriesBurnedRecord.ENERGY_TOTAL),
                timeRangeFilter = range,
                dataOriginFilter = origins
            )
        )[TotalCaloriesBurnedRecord.ENERGY_TOTAL]?.inKilocalories

        val filteredActive = filteredTotal ?: client.aggregate(
            AggregateRequest(
                metrics = setOf(ActiveCaloriesBurnedRecord.ACTIVE_CALORIES_TOTAL),
                timeRangeFilter = range,
                dataOriginFilter = origins
            )
        )[ActiveCaloriesBurnedRecord.ACTIVE_CALORIES_TOTAL]?.inKilocalories

        val unfilteredTotal = filteredActive ?: client.aggregate(
            AggregateRequest(
                metrics = setOf(TotalCaloriesBurnedRecord.ENERGY_TOTAL),
                timeRangeFilter = range
            )
        )[TotalCaloriesBurnedRecord.ENERGY_TOTAL]?.inKilocalories

        val calories = unfilteredTotal ?: client.aggregate(
            AggregateRequest(
                metrics = setOf(ActiveCaloriesBurnedRecord.ACTIVE_CALORIES_TOTAL),
                timeRangeFilter = range
            )
        )[ActiveCaloriesBurnedRecord.ACTIVE_CALORIES_TOTAL]?.inKilocalories

        return calories?.let { Math.round(it).toInt() }
    }

    private fun exerciseLabel(type: Int): String {
        return when (type) {
            ExerciseSessionRecord.EXERCISE_TYPE_BIKING,
            ExerciseSessionRecord.EXERCISE_TYPE_BIKING_STATIONARY -> "Cycling"
            ExerciseSessionRecord.EXERCISE_TYPE_RUNNING,
            ExerciseSessionRecord.EXERCISE_TYPE_RUNNING_TREADMILL -> "Run"
            ExerciseSessionRecord.EXERCISE_TYPE_STRENGTH_TRAINING -> "Strength training"
            ExerciseSessionRecord.EXERCISE_TYPE_WALKING -> "Walk"
            ExerciseSessionRecord.EXERCISE_TYPE_SWIMMING_OPEN_WATER,
            ExerciseSessionRecord.EXERCISE_TYPE_SWIMMING_POOL -> "Swim"
            else -> "Health Connect workout"
        }
    }
}
