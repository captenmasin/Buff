<?php

it('uses the native capability registry instead of cached runtime flags', function (): void {
    $bridge = file_get_contents(__DIR__.'/../../app/Services/MealReminderBridge.php');

    expect($bridge)
        ->toContain("nativephp_can('BackgroundTasks.RegisterMealReminders')")
        ->not->toContain("config('nativephp-internal.running')")
        ->not->toContain("config('nativephp-internal.platform')");
});
