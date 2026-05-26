<?php

it('keeps the native add shortcut pointed at the dashboard drawer', function (): void {
    $hook = file_get_contents(__DIR__.'/../../native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php');

    expect($hook)
        ->toContain('nativephp:///?add=1')
        ->toContain('installAndroidAddShortcut')
        ->toContain('installIosAddShortcut');
});
