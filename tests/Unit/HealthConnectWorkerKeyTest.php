<?php

it('uses the NativePHP background environment instead of cloning its setup', function (): void {
    $worker = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectSyncWorker.kt');

    expect($worker)
        ->toContain('LaravelEnvironment(applicationContext).initializeForBackground()')
        ->toContain('private const val IMPORT_SUCCESS_MARKER = "BUFF_HEALTH_CONNECT_IMPORT_OK"')
        ->toContain('"health-connect:import --payload=${file.absolutePath} --result=${resultFile.absolutePath}"')
        ->toContain('resultFile.readText() != IMPORT_SUCCESS_MARKER')
        ->not->toContain('output.contains("BUFF_HEALTH_CONNECT_IMPORT_OK")')
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
        ->toContain('const nextState = {message: null, last_error: null, ...data, ...(data.native || {})};')
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
        ->toContain('"background-task:run --task=$taskId --result=${resultFile.absolutePath}"')
        ->toContain('bridge.nativeEphemeralArtisan(command)')
        ->toContain('resultFile.readText() != SUCCESS_PREFIX + taskId')
        ->not->toContain('output.contains(SUCCESS_PREFIX + taskId)')
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

it('keeps health settings polling queued work and exposes request failures', function (): void {
    $settings = file_get_contents(__DIR__.'/../../resources/js/Pages/Settings/Health.vue');

    expect($settings)
        ->toContain("['permission_requested', 'sync_queued'].includes(healthImport.value?.state.status ?? '')")
        ->toContain('void refreshHealthConnectStatusAndPoll()')
        ->toContain("last_error: `Could not \${isSync ? 'sync' : 'connect'}")
        ->toContain("last_error: 'Could not disconnect Health Connect.'")
        ->toContain(":role=\"healthImport?.state.last_error ? 'alert' : undefined\"");
});

it('offers Health Connect access recovery after permission lockout', function (): void {
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/health-connect/nativephp.json');
    $functions = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectFunctions.kt');
    $settings = file_get_contents(__DIR__.'/../../resources/js/Pages/Settings/Health.vue');

    expect($manifest)
        ->toContain('HealthConnect.ManageAccess')
        ->and($functions)
        ->toContain('android.health.connect.action.MANAGE_HEALTH_PERMISSIONS')
        ->toContain('Intent.EXTRA_PACKAGE_NAME')
        ->toContain('HealthConnectClient.ACTION_HEALTH_CONNECT_SETTINGS')
        ->toContain('withContext(Dispatchers.Main.immediate)')
        ->toContain('"manage_access_opened" to false')
        ->toContain('"last_error" to "Could not open Health Connect access settings.')
        ->and($settings)
        ->toContain("axios.post('/health-connect/manage-access')")
        ->toContain('Manage Health Connect access')
        ->toContain('If Connect no longer shows a permission prompt')
        ->toContain('Could not open Health Connect access settings.');
});
