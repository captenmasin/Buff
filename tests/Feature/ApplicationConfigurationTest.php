<?php

use Illuminate\Support\Facades\Vite;

it('aligns the Inertia devtools recorder with the client build mode', function (): void {
    expect(config('inertia.devtools.enabled'))->toBe(Vite::isRunningHot());
});
