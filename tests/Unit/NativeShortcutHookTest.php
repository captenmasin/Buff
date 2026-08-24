<?php

it('keeps the native shortcut targets pointed at their add flows', function (): void {
    $config = file_get_contents(__DIR__.'/../../config/nativephp.php');
    $hook = file_get_contents(__DIR__.'/../../native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php');

    expect($config)->toContain("env('NATIVEPHP_DEEPLINK_SCHEME', 'buff')")
        ->and($hook)
        ->toContain('buff://add')
        ->toContain('buff://add?mode=food&amp;scan=1')
        ->toContain('buff://add?mode=workout')
        ->toContain('shortcut_scan_short')
        ->toContain('shortcut_workout_short')
        ->toContain('installAndroidAddShortcut');
});
