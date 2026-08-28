<?php

it('uses the NativePHP background environment instead of cloning its setup', function (): void {
    $worker = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectSyncWorker.kt');

    expect($worker)
        ->toContain('LaravelEnvironment(applicationContext).initializeForBackground()')
        ->toContain('output.contains("BUFF_HEALTH_CONNECT_IMPORT_OK")')
        ->not->toContain('appKeyFile.writeText')
        ->not->toContain('migrate --force');
});

it('continues to the background health connect permission after foreground access is granted', function (): void {
    $permissionActivity = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectPermissionActivity.kt');

    expect($permissionActivity)
        ->toContain('grantedPermissions.any { it in HealthConnectPlugin.foregroundPermissions }')
        ->toContain('HealthConnectPlugin.permissionsToRequest(this@HealthConnectPermissionActivity)')
        ->toContain('permissionLauncher.launch(setOf(HealthConnectPlugin.backgroundPermission))');
});

it('revokes permissions and exposes a health connect disconnect control', function (): void {
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/health-connect/nativephp.json');
    $functions = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectFunctions.kt');
    $plugin = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectPlugin.kt');
    $permissionActivity = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectPermissionActivity.kt');
    $settings = file_get_contents(__DIR__.'/../../resources/js/Pages/Settings/Health.vue');

    expect($manifest)
        ->toContain('HealthConnect.Disconnect')
        ->and($functions)
        ->toContain('.revokeAllPermissions()')
        ->toContain('HealthConnectPlugin.markPermissionsRevoked()')
        ->and($settings)
        ->toContain("healthImport.value?.prefix === '/health-connect'")
        ->toContain('healthImport.value.state.foreground_granted === true')
        ->toContain('const nextState = {message: null, ...data, ...(data.native || {})};')
        ->toContain("axios.delete('/health-connect')")
        ->toContain('Disconnect Health Connect');

    expect($plugin)
        ->toContain('private var permissionsRevokedUntilRestart = false')
        ->toContain('if (permissionsRevokedUntilRestart || !isAvailable(context))')
        ->toContain('if (permissionsRevokedUntilRestart)')
        ->toContain('if (available && !permissionsRevokedUntilRestart)')
        ->and($permissionActivity)
        ->toContain('HealthConnectPlugin.markPermissionsGranted()');
});

it('schedules Health Connect through the generic background worker', function (): void {
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/health-connect/nativephp.json');
    $permissionActivity = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectPermissionActivity.kt');
    $plugin = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectPlugin.kt');
    $backgroundWorker = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/resources/android/src/com/buff/backgroundtasks/BackgroundTaskFunctions.kt');
    $backgroundProvider = file_get_contents(__DIR__.'/../../native-plugins/background-tasks/src/BackgroundTasksServiceProvider.php');

    expect($manifest)
        ->not->toContain('HealthConnect.Schedule')
        ->not->toContain('init_function')
        ->and($permissionActivity)
        ->toContain('HealthConnectPlugin.enqueueImmediateSync(applicationContext)')
        ->toContain('HealthConnectPlugin.hasAllPermissions(this@HealthConnectPermissionActivity)')
        ->not->toContain('schedulePeriodicSync')
        ->and($plugin)
        ->toContain('backgroundAvailable && !backgroundGranted -> "background_permission_required"')
        ->toContain('(!backgroundReadAvailable(context) || backgroundPermission in granted)')
        ->not->toContain('PeriodicWorkRequestBuilder<HealthConnectSyncWorker>')
        ->and($backgroundWorker)
        ->toContain('LaravelEnvironment(context).initializeForBackground()')
        ->toContain('registerContextOnlyBridgeFunctions(context)')
        ->toContain('"background-task:run $taskId"')
        ->toContain('bridge.nativeEphemeralArtisan(command)')
        ->toContain('SUCCESS_PREFIX + taskId')
        ->toContain('OneTimeWorkRequestBuilder<ScheduledTaskWorker>()')
        ->toContain('setInitialDelay(task.intervalMinutes, TimeUnit.MINUTES)')
        ->toContain('ExistingWorkPolicy.KEEP')
        ->toContain('ExistingWorkPolicy.APPEND_OR_REPLACE')
        ->toContain('PeriodicWorkRequestBuilder<ScheduledTaskWorker>')
        ->toContain('ExistingPeriodicWorkPolicy.UPDATE')
        ->toContain('isTaskRegistered(applicationContext, taskId)')
        ->toContain('cancelUniqueWork(LEGACY_HEALTH_CONNECT_WORK_NAME)')
        ->and($plugin)
        ->toContain('ExistingWorkPolicy.KEEP')
        ->and($backgroundProvider)
        ->toContain("nativephp_call('BackgroundTasks.Register'")
        ->toContain('->registrations()');
});
