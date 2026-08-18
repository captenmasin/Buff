<?php

it('keeps the native shortcut targets pointed at their add flows', function (): void {
    $hook = file_get_contents(__DIR__.'/../../native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php');

    expect($hook)
        ->toContain('nativephp://add')
        ->toContain('nativephp://add?mode=food&amp;scan=1')
        ->toContain('nativephp://add?mode=workout')
        ->toContain('shortcut_scan_short')
        ->toContain('shortcut_workout_short')
        ->toContain('installAndroidAddShortcut');
});
