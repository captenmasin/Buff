<?php

it('keeps the native shortcut targets pointed at their add flows', function (): void {
    $config = file_get_contents(__DIR__.'/../../config/nativephp.php');
    $manifest = file_get_contents(__DIR__.'/../../native-plugins/native-refresh/nativephp.json');
    $hook = file_get_contents(__DIR__.'/../../native-plugins/native-refresh/src/Commands/InstallNativeShellIntegrationsCommand.php');

    expect($config)->toContain("env('NATIVEPHP_DEEPLINK_SCHEME', 'buff')")
        ->and($hook)
        ->toContain('buff://add')
        ->toContain('buff://add?mode=food&amp;scan=1')
        ->toContain('buff://add?mode=workout')
        ->toContain('shortcut_scan_short')
        ->toContain('shortcut_workout_short')
        ->toContain('installAndroidAddShortcut')
        ->and($manifest)
        ->toContain('android/res/mipmap-anydpi-v26/shortcut_add.xml')
        ->toContain('android/res/mipmap-anydpi-v26/shortcut_scan.xml')
        ->toContain('android/res/mipmap-anydpi-v26/shortcut_workout.xml');

    foreach (['add', 'scan', 'workout'] as $shortcut) {
        expect(file_get_contents(__DIR__."/../../native-plugins/native-refresh/resources/android/res/mipmap-anydpi-v26/shortcut_{$shortcut}.xml"))
            ->toContain('<adaptive-icon')
            ->toContain('<background>')
            ->toContain('<foreground>')
            ->toContain('#FFB6FF51')
            ->toContain('#FF0F1125');
    }
});
