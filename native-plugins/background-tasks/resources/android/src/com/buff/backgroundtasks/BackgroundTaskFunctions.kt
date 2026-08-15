package com.buff.backgroundtasks

import android.Manifest
import android.annotation.SuppressLint
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import androidx.work.workDataOf
import com.nativephp.mobile.bridge.BridgeError
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.bridge.LaravelEnvironment
import com.nativephp.mobile.bridge.PHPBridge
import com.nativephp.mobile.bridge.plugins.registerContextOnlyBridgeFunctions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.time.Duration
import java.time.ZonedDateTime
import java.util.concurrent.TimeUnit

private const val PREFERENCES_NAME = "buff-background-tasks"
private const val REGISTERED_TASKS_KEY = "registered-tasks"
private const val TASK_ID_KEY = "task-id"
private const val TASK_INTERVAL_KEY = "task-interval"
private const val WORK_NAME_PREFIX = "buff-background-task-"
private const val LEGACY_HEALTH_CONNECT_WORK_NAME = "buff-health-connect-sync"
private const val BACKGROUND_ENV = "BUFF_BACKGROUND_TASK_RUNNING"
private const val SUCCESS_PREFIX = "BUFF_BACKGROUND_TASK_OK:"
private const val MEAL_DUE_PREFIX = "BUFF_MEAL_REMINDER_DUE:"
private const val MEAL_LOGGED_PREFIX = "BUFF_MEAL_REMINDER_LOGGED:"
private const val MEAL_PREFERENCES_NAME = "buff-meal-reminders"
private const val MEAL_ID_KEY = "meal-id"
private const val MEAL_TIME_KEY = "meal-time"
private const val MEAL_WORK_NAME_PREFIX = "buff-meal-reminder-"
private const val MEAL_NOTIFICATION_CHANNEL_ID = "meal-reminders"
private const val NOTIFICATION_PERMISSION_REQUEST_CODE = 1002
private const val NOTIFICATION_PERMISSION_REQUESTED_KEY = "notification-permission-requested"
private val TASK_ID_PATTERN = Regex("^[a-f0-9]{64}$")
private val MEAL_IDS = setOf("breakfast", "lunch", "dinner")
private val MEAL_TIME_PATTERN = Regex("^([01]\\d|2[0-3]):[0-5]\\d$")
private val BACKGROUND_ARTISAN_LOCK = Any()

object BackgroundTaskFunctions {
    class Register(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val tasks = parameters["tasks"] as? JSONArray
                ?: throw BridgeError.InvalidParameters("tasks must be an array")
            val registrations = buildList {
                for (index in 0 until tasks.length()) {
                    val task = tasks.optJSONObject(index)
                        ?: throw BridgeError.InvalidParameters("tasks[$index] must be an object")
                    add(parseTask(task, index))
                }
            }
            val workManager = WorkManager.getInstance(context)
            val preferences = context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)
            val previousIds = preferences.getStringSet(REGISTERED_TASKS_KEY, emptySet())?.toSet().orEmpty()
            val registeredIds = registrations.mapTo(mutableSetOf()) { it.id }

            workManager.cancelUniqueWork(LEGACY_HEALTH_CONNECT_WORK_NAME)
            preferences.edit().putStringSet(REGISTERED_TASKS_KEY, registeredIds).apply()

            (previousIds - registeredIds).forEach { id ->
                workManager.cancelUniqueWork(WORK_NAME_PREFIX + id)
            }

            registrations.forEach { task ->
                if (task.intervalMinutes < 15) {
                    enqueueScheduledTask(context, task, ExistingWorkPolicy.KEEP)
                } else {
                    val request = PeriodicWorkRequestBuilder<ScheduledTaskWorker>(
                        task.intervalMinutes,
                        TimeUnit.MINUTES
                    ).setInputData(workDataOf(
                        TASK_ID_KEY to task.id,
                        TASK_INTERVAL_KEY to task.intervalMinutes
                    )).build()

                    workManager.enqueueUniquePeriodicWork(
                        WORK_NAME_PREFIX + task.id,
                        ExistingPeriodicWorkPolicy.UPDATE,
                        request
                    )
                }
            }

            return BridgeResponse.success(mapOf(
                "registered" to registeredIds.size,
                "cancelled" to (previousIds - registeredIds).size
            ))
        }

        private fun parseTask(task: JSONObject, index: Int): RegisteredTask {
            val id = task.optString("id")
            val intervalMinutes = task.optLong("interval_minutes")

            if (!TASK_ID_PATTERN.matches(id)) {
                throw BridgeError.InvalidParameters("tasks[$index].id is invalid")
            }
            if (intervalMinutes < 1) {
                throw BridgeError.InvalidParameters("tasks[$index].interval_minutes must be at least 1")
            }

            return RegisteredTask(id, intervalMinutes)
        }
    }

    class RegisterMealReminders(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val reminders = parameters["reminders"] as? JSONObject
                ?: throw BridgeError.InvalidParameters("reminders must be an object")
            val registrations = parseMealReminders(reminders)
            val context = activity.applicationContext
            val workManager = WorkManager.getInstance(context)
            val preferences = context.getSharedPreferences(MEAL_PREFERENCES_NAME, Context.MODE_PRIVATE)
            val editor = preferences.edit()

            createMealReminderChannel(context)

            registrations.forEach { reminder ->
                editor
                    .putBoolean(mealEnabledKey(reminder.id), reminder.enabled)
                    .putString(mealTimeKey(reminder.id), reminder.time)

                if (reminder.enabled) {
                    enqueueMealReminder(context, reminder, ExistingWorkPolicy.REPLACE)
                } else {
                    workManager.cancelUniqueWork(MEAL_WORK_NAME_PREFIX + reminder.id)
                }
            }
            editor.apply()

            val enabledCount = registrations.count { it.enabled }
            val hasPermission = ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.POST_NOTIFICATIONS
            ) == PackageManager.PERMISSION_GRANTED
            val permissionAlreadyRequested = preferences.getBoolean(
                NOTIFICATION_PERMISSION_REQUESTED_KEY,
                false
            )
            val status = when {
                enabledCount == 0 -> "disabled"
                !hasPermission && !permissionAlreadyRequested -> {
                    preferences.edit()
                        .putBoolean(NOTIFICATION_PERMISSION_REQUESTED_KEY, true)
                        .apply()
                    activity.runOnUiThread {
                        ActivityCompat.requestPermissions(
                            activity,
                            arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                            NOTIFICATION_PERMISSION_REQUEST_CODE
                        )
                    }
                    "permission_requested"
                }
                !hasPermission -> "notifications_disabled"
                !mealNotificationsEnabled(context) -> "notifications_disabled"
                else -> "scheduled"
            }

            return BridgeResponse.success(mapOf(
                "status" to status,
                "scheduled" to enabledCount
            ))
        }

        private fun parseMealReminders(reminders: JSONObject): List<MealReminder> {
            if (reminders.length() != MEAL_IDS.size || MEAL_IDS.any { !reminders.has(it) }) {
                throw BridgeError.InvalidParameters("breakfast, lunch, and dinner reminders are required")
            }

            return MEAL_IDS.map { id ->
                val reminder = reminders.optJSONObject(id)
                    ?: throw BridgeError.InvalidParameters("$id must be an object")
                val enabled = reminder.opt("enabled")
                val time = reminder.optString("time")

                if (enabled !is Boolean) {
                    throw BridgeError.InvalidParameters("$id.enabled must be a boolean")
                }
                if (!MEAL_TIME_PATTERN.matches(time)) {
                    throw BridgeError.InvalidParameters("$id.time must use HH:mm")
                }

                MealReminder(id, enabled, time)
            }
        }
    }
}

private data class RegisteredTask(
    val id: String,
    val intervalMinutes: Long
)

private data class MealReminder(
    val id: String,
    val enabled: Boolean,
    val time: String
)

class ScheduledTaskWorker(
    appContext: Context,
    workerParams: WorkerParameters
) : CoroutineWorker(appContext, workerParams) {
    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val taskId = inputData.getString(TASK_ID_KEY)
        val intervalMinutes = inputData.getLong(TASK_INTERVAL_KEY, 0)

        if (
            taskId == null
            || !TASK_ID_PATTERN.matches(taskId)
            || intervalMinutes < 1
        ) {
            return@withContext Result.failure()
        }

        if (!isTaskRegistered(applicationContext, taskId)) {
            return@withContext Result.success()
        }

        val result = try {
            val output = runBackgroundArtisan(
                applicationContext,
                "background-task:run $taskId"
            )
            if (!output.contains(SUCCESS_PREFIX + taskId)) {
                throw IllegalStateException(output.trim().ifEmpty {
                    "Background command did not report success."
                })
            }

            Log.d("BuffBackgroundTasks", output.take(300))
            Result.success()
        } catch (error: Exception) {
            Log.e("BuffBackgroundTasks", "Background task failed", error)
            if (intervalMinutes < 15) Result.success() else Result.retry()
        }

        if (intervalMinutes < 15 && isTaskRegistered(applicationContext, taskId)) {
            try {
                enqueueScheduledTask(
                    applicationContext,
                    RegisteredTask(taskId, intervalMinutes),
                    ExistingWorkPolicy.APPEND_OR_REPLACE
                )
            } catch (error: Exception) {
                Log.e("BuffBackgroundTasks", "Could not schedule the next background task", error)

                return@withContext Result.retry()
            }
        }

        result
    }
}

private fun isTaskRegistered(context: Context, taskId: String): Boolean =
    context.getSharedPreferences(PREFERENCES_NAME, Context.MODE_PRIVATE)
        .getStringSet(REGISTERED_TASKS_KEY, emptySet())
        ?.contains(taskId) == true

private fun enqueueScheduledTask(
    context: Context,
    task: RegisteredTask,
    policy: ExistingWorkPolicy
) {
    if (task.intervalMinutes < 1) {
        throw IllegalArgumentException("Background task interval must be at least 1 minute.")
    }

    val request = OneTimeWorkRequestBuilder<ScheduledTaskWorker>()
        .setInitialDelay(task.intervalMinutes, TimeUnit.MINUTES)
        .setInputData(workDataOf(
            TASK_ID_KEY to task.id,
            TASK_INTERVAL_KEY to task.intervalMinutes
        ))
        .build()

    WorkManager.getInstance(context).enqueueUniqueWork(
        WORK_NAME_PREFIX + task.id,
        policy,
        request
    )
}

class MealReminderWorker(
    appContext: Context,
    workerParams: WorkerParameters
) : CoroutineWorker(appContext, workerParams) {
    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val mealId = inputData.getString(MEAL_ID_KEY)
        val scheduledTime = inputData.getString(MEAL_TIME_KEY)

        if (mealId == null || mealId !in MEAL_IDS || scheduledTime == null || !MEAL_TIME_PATTERN.matches(scheduledTime)) {
            return@withContext Result.failure()
        }

        val preferences = applicationContext.getSharedPreferences(MEAL_PREFERENCES_NAME, Context.MODE_PRIVATE)
        val enabled = preferences.getBoolean(mealEnabledKey(mealId), false)
        val currentTime = preferences.getString(mealTimeKey(mealId), null)

        if (!enabled || currentTime != scheduledTime) {
            return@withContext Result.success()
        }

        val canNotify = ContextCompat.checkSelfPermission(
            applicationContext,
            Manifest.permission.POST_NOTIFICATIONS
        ) == PackageManager.PERMISSION_GRANTED && mealNotificationsEnabled(applicationContext)

        if (canNotify) {
            try {
                val localDate = ZonedDateTime.now().toLocalDate()
                val output = runBackgroundArtisan(
                    applicationContext,
                    "meal-reminder:check $mealId $localDate"
                )

                when {
                    output.contains(MEAL_DUE_PREFIX + mealId) -> showMealReminder(applicationContext, mealId)
                    !output.contains(MEAL_LOGGED_PREFIX + mealId) -> throw IllegalStateException(
                        output.trim().ifEmpty { "Meal reminder check returned no result." }
                    )
                }
            } catch (error: Exception) {
                Log.e("BuffMealReminders", "Could not check the $mealId reminder", error)
            }
        }

        enqueueMealReminder(
            applicationContext,
            MealReminder(mealId, true, scheduledTime),
            ExistingWorkPolicy.APPEND_OR_REPLACE
        )

        Result.success()
    }
}

private fun runBackgroundArtisan(context: Context, command: String): String =
    synchronized(BACKGROUND_ARTISAN_LOCK) {
        val bridge = PHPBridge(context)
        var booted = false

        try {
            if (bridge.nativeSetEnv(BACKGROUND_ENV, "1", 1) != 0) {
                throw IllegalStateException("Could not mark the NativePHP background runtime.")
            }

            LaravelEnvironment(context).initializeForBackground()
            registerContextOnlyBridgeFunctions(context)

            val bootstrap = "${bridge.getLaravelPath()}/vendor/nativephp/mobile/bootstrap/android/persistent.php"
            if (bridge.nativeEphemeralBoot(bootstrap) != 0) {
                throw IllegalStateException("Could not boot the NativePHP background runtime.")
            }
            booted = true

            bridge.nativeEphemeralArtisan(command)
        } finally {
            if (booted) {
                bridge.nativeEphemeralShutdown()
            }
            bridge.nativeSetEnv(BACKGROUND_ENV, "0", 1)
        }
    }

private fun enqueueMealReminder(
    context: Context,
    reminder: MealReminder,
    policy: ExistingWorkPolicy
) {
    val now = ZonedDateTime.now()
    val (hour, minute) = reminder.time.split(':').map(String::toInt)
    var next = now.withHour(hour).withMinute(minute).withSecond(0).withNano(0)

    if (!next.isAfter(now)) {
        next = next.plusDays(1)
    }

    val request = OneTimeWorkRequestBuilder<MealReminderWorker>()
        .setInitialDelay(Duration.between(now, next).toMillis(), TimeUnit.MILLISECONDS)
        .setInputData(workDataOf(
            MEAL_ID_KEY to reminder.id,
            MEAL_TIME_KEY to reminder.time
        ))
        .build()

    WorkManager.getInstance(context).enqueueUniqueWork(
        MEAL_WORK_NAME_PREFIX + reminder.id,
        policy,
        request
    )
}

private fun mealEnabledKey(mealId: String): String = "$mealId-enabled"

private fun mealTimeKey(mealId: String): String = "$mealId-time"

private fun createMealReminderChannel(context: Context) {
    val channel = NotificationChannel(
        MEAL_NOTIFICATION_CHANNEL_ID,
        "Meal reminders",
        NotificationManager.IMPORTANCE_DEFAULT
    ).apply {
        description = "Reminders to log breakfast, lunch, and dinner"
    }

    context.getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
}

private fun mealNotificationsEnabled(context: Context): Boolean {
    if (!NotificationManagerCompat.from(context).areNotificationsEnabled()) {
        return false
    }

    val channel = context.getSystemService(NotificationManager::class.java)
        .getNotificationChannel(MEAL_NOTIFICATION_CHANNEL_ID)

    return channel == null || channel.importance != NotificationManager.IMPORTANCE_NONE
}

@SuppressLint("MissingPermission")
private fun showMealReminder(context: Context, mealId: String) {
    createMealReminderChannel(context)

    val label = mealId.replaceFirstChar { it.titlecase() }
    val notification = NotificationCompat.Builder(context, MEAL_NOTIFICATION_CHANNEL_ID)
        .setSmallIcon(android.R.drawable.ic_dialog_info)
        .setContentTitle("$label reminder")
        .setContentText("Time to log your $mealId in Buff.")
        .setCategory(NotificationCompat.CATEGORY_REMINDER)
        .setAutoCancel(true)
        .setPriority(NotificationCompat.PRIORITY_DEFAULT)

    context.packageManager.getLaunchIntentForPackage(context.packageName)?.let { launchIntent ->
        launchIntent
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            .putExtra("notification_url", "/add?mode=food&meal=$mealId")

        notification.setContentIntent(PendingIntent.getActivity(
            context,
            mealNotificationId(mealId),
            launchIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        ))
    }

    NotificationManagerCompat.from(context).notify(
        mealNotificationId(mealId),
        notification.build()
    )
}

private fun mealNotificationId(mealId: String): Int = when (mealId) {
    "breakfast" -> 4101
    "lunch" -> 4102
    else -> 4103
}
