<?php

it('uses HealthKit and the NativePHP ephemeral runtime for Apple Health imports', function (): void {
    $plugin = file_get_contents(__DIR__.'/../../native-plugins/apple-health/resources/ios/Sources/AppleHealthPlugin.swift');
    $php = file_get_contents(__DIR__.'/../../native-plugins/apple-health/resources/ios/Sources/AppleHealthPHP.swift');
    $functions = file_get_contents(__DIR__.'/../../native-plugins/apple-health/resources/ios/Sources/AppleHealthFunctions.swift');
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/apple-health/nativephp.json');
    $settings = file_get_contents(__DIR__.'/../../resources/js/Pages/Settings/Health.vue');
    $today = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($manifest)
        ->toContain('"platforms": ["ios"]')
        ->toContain('"min_version": 33')
        ->toContain('AppleHealth.Status')
        ->toContain('AppleHealth.RequestPermissions')
        ->toContain('AppleHealth.SyncNow')
        ->toContain('com.apple.developer.healthkit')
        ->toContain('NSHealthShareUsageDescription')
        ->toContain('sync them to your Buff account')
        ->not->toContain('stays on this device')
        ->toContain('NSHealthUpdateUsageDescription')
        ->toContain('AppleHealthPlugin.startObserving')
        ->not->toContain('"background_modes"')
        ->and($functions)
        ->toContain('AppleHealthPlugin.requestAuthorization()')
        ->toContain('AppleHealthPlugin.enqueueImmediateSync()')
        ->toContain('"status": "permission_requested"')
        ->and($plugin)
        ->toContain('HKHealthStore.isHealthDataAvailable()')
        ->toContain('requestAuthorization(toShare: nil, read: readTypes)')
        ->toContain('HKObjectType.workoutType()')
        ->toContain('enableBackgroundDelivery')
        ->toContain('apple-health:import --payload=')
        ->toContain('BUFF_APPLE_HEALTH_IMPORT_OK')
        ->and($php)
        ->toContain('PersistentPHPRuntime.shared.artisan')
        ->toContain('ephemeral_php_artisan')
        ->and($settings)
        ->toContain("prefix: '/apple-health'")
        ->toContain("'Apple Health'")
        ->and($today)
        ->toContain("prefix: '/apple-health'")
        ->toContain("'Apple Health'");
});
