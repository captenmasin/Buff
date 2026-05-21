<?php

it('generates a per-install laravel app key instead of writing a static fallback', function (): void {
    $worker = file_get_contents(__DIR__.'/../../native-plugins/health-connect/resources/android/src/com/buff/healthconnect/HealthConnectSyncWorker.kt');
    $literalKeyWrite = 'appKeyFile.writeText('.'"base64:';

    expect($worker)
        ->not->toContain($literalKeyWrite)
        ->toContain('SecureRandom().nextBytes(key)')
        ->toContain('Base64.encodeToString(key, Base64.NO_WRAP)')
        ->toContain('appKeyFile.writeText(generateLaravelAppKey())');
});
