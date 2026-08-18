<?php

it('uses a stable compiled view path for copied native builds', function (): void {
    $compiledViewPath = config('view.compiled');

    expect($compiledViewPath)->toBe(storage_path('framework/views'))
        ->and($compiledViewPath)->not->toBeFalse()
        ->and($compiledViewPath)->not->toBe('');
});

it('keeps account and onboarding outside the app shell', function (): void {
    $account = file_get_contents(resource_path('js/Pages/Account.vue'));
    $onboarding = file_get_contents(resource_path('js/Pages/Onboarding.vue'));

    expect($account)->toContain('defineOptions({ layout: null })')
        ->and($onboarding)->toContain('defineOptions({ layout: null })')
        ->toContain('safe-area-inset-top')
        ->toContain('safe-area-inset-bottom');
});
