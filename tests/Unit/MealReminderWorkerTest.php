<?php

it('registers daily Android meal notifications', function (): void {
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/nativephp.json');
    $worker = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/resources/android/src/com/buff/backgroundtasks/BackgroundTaskFunctions.kt');
    $notificationIcon = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/resources/android/res/drawable/buff_notification.xml');

    expect($manifest)
        ->toContain('BackgroundTasks.RegisterMealReminders')
        ->toContain('android.permission.POST_NOTIFICATIONS')
        ->toContain('"android/res/drawable/buff_notification.xml": "res/drawable/buff_notification.xml"')
        ->and($worker)
        ->toContain('ActivityCompat.requestPermissions(')
        ->toContain('!hasPermission && !permissionAlreadyRequested')
        ->toContain('OneTimeWorkRequestBuilder<MealReminderWorker>()')
        ->toContain('ExistingWorkPolicy.APPEND_OR_REPLACE')
        ->toContain('ZonedDateTime.now()')
        ->toContain('"meal-reminder:check --meal=$mealId --date=$localDate"')
        ->toContain('output.contains(MEAL_DUE_PREFIX + mealId)')
        ->toContain('!output.contains(MEAL_LOGGED_PREFIX + mealId)')
        ->toContain('if (runAttemptCount < MEAL_REMINDER_MAX_RETRIES)')
        ->toContain('return@withContext Result.retry()')
        ->toContain('NotificationCompat.Builder(context, MEAL_NOTIFICATION_CHANNEL_ID)')
        ->toContain('R.drawable.buff_notification')
        ->not->toContain('android.R.drawable.ic_dialog_info')
        ->toContain('"notification_url", "/add?mode=food&meal=$mealId"')
        ->toContain('MEAL_TIME_PATTERN.matches(time)')
        ->and($notificationIcon)
        ->toContain('<vector')
        ->toContain('android:fillColor="#FFFFFFFF"');
});

it('registers daily iOS meal notifications', function (): void {
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/nativephp.json');
    $notifications = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/resources/ios/Sources/BackgroundTaskFunctions.swift');

    expect($manifest)
        ->toContain('"platforms": ["android", "ios"]')
        ->toContain('"ios": "BackgroundTaskFunctions.RegisterMealReminders"')
        ->toContain('"init_function": "BackgroundTasksNotifications.start"')
        ->and($notifications)
        ->toContain('import UserNotifications')
        ->toContain('Set(reminders.keys) == Set(mealIds)')
        ->toContain('parseTime(time)')
        ->toContain('UNUserNotificationCenter.current().requestAuthorization')
        ->toContain('UNCalendarNotificationTrigger(dateMatching: components, repeats: true)')
        ->toContain('removePendingNotificationRequests(withIdentifiers: reminderIdentifiers)')
        ->toContain('"notification_url": "/add?mode=food&meal=\(reminder.id)"')
        ->toContain('UNUserNotificationCenterDelegate')
        ->toContain('DeepLinkRouter.shared.handle(url: url)');
});
