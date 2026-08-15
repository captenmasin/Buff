<?php

it('registers daily Android meal notifications', function (): void {
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/nativephp.json');
    $worker = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/resources/android/src/com/buff/backgroundtasks/BackgroundTaskFunctions.kt');

    expect($manifest)
        ->toContain('BackgroundTasks.RegisterMealReminders')
        ->toContain('android.permission.POST_NOTIFICATIONS')
        ->and($worker)
        ->toContain('ActivityCompat.requestPermissions(')
        ->toContain('!hasPermission && !permissionAlreadyRequested')
        ->toContain('OneTimeWorkRequestBuilder<MealReminderWorker>()')
        ->toContain('ExistingWorkPolicy.APPEND_OR_REPLACE')
        ->toContain('ZonedDateTime.now()')
        ->toContain('"meal-reminder:check $mealId $localDate"')
        ->toContain('output.contains(MEAL_DUE_PREFIX + mealId)')
        ->toContain('!output.contains(MEAL_LOGGED_PREFIX + mealId)')
        ->toContain('NotificationCompat.Builder(context, MEAL_NOTIFICATION_CHANNEL_ID)')
        ->toContain('"notification_url", "/add?mode=food&meal=$mealId"')
        ->toContain('MEAL_TIME_PATTERN.matches(time)');
});
