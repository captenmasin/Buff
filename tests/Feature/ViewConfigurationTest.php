<?php

it('disables webview zoom so the app behaves like a native shell', function (): void {
    $layout = file_get_contents(resource_path('views/app.blade.php'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($layout)
        ->toContain('user-scalable=no')
        ->toContain('maximum-scale=1')
        ->toContain('viewport-fit=cover')
        ->and($css)->toContain('touch-action: manipulation');
});

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

it('shares page chrome across the main screens', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $settings = file_get_contents(resource_path('js/Pages/Settings.vue'));
    $shell = file_get_contents(resource_path('js/Layouts/AppShell.vue'));
    $macros = file_get_contents(resource_path('js/Components/Add/MacroSummary.vue'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($today)
        ->toContain('PageHeader')
        ->toContain('CalorieRing')
        ->toContain('Start today')
        ->toContain('rounded-2xl bg-card p-1.5 shadow-card')
        ->toContain('PopoverTrigger')
        ->toContain('from \'../Components/ui/calendar\'')
        ->toContain('layout="month-and-year"')
        ->not->toContain('type="date"')
        ->not->toContain('kicker')
        ->not->toContain('bg-card/70')
        ->not->toContain('border-0 bg-secondary shadow-none')
        ->and($settings)->toContain('PageHeader')
        ->and($settings)->not->toContain('kicker')
        ->and($settings)->toContain('deleteAccountOpen')
        ->and($settings)->toContain('openDeleteAccountModal')
        ->and($settings)->not->toContain('{{ syncDetail }}')
        ->and($settings)->not->toContain('Save units')
        ->and($settings)->not->toContain('Save reminders')
        ->and($settings)->toContain('divide-y divide-border/60')
        ->and($settings)->toContain('Select v-model="accountForm.timezone"')
        ->and($settings)->not->toContain('Input v-model="accountForm.timezone"')
        ->and($settings)->not->toContain('rounded-xl border border-border/80 bg-muted/40 p-3')
        ->and($settings)->not->toContain('Import / export')
        ->and($settings)->not->toContain('/settings/export')
        ->and($settings)->not->toContain('/settings/import')
        ->and($settings)->toContain('Sign out and remove local data')
        ->and($shell)->toContain('bottom-nav')
        ->and($shell)->toContain('openAddDrawer')
        ->and($shell)->toContain('bottom-drawer')
        ->and($shell)->toContain('grid-cols-3')
        ->and($shell)->toContain("openAddMode('food')")
        ->and($shell)->toContain("openAddMode('food', { scan: '1' })")
        ->and($shell)->toContain("openAddMode('custom')")
        ->and($shell)->toContain("openAddMode('workout')")
        ->and($shell)->toContain("openAddMode('photo')")
        ->and($shell)->not->toContain('addHref')
        ->and($macros)->toContain('field-label')
        ->and($macros)->not->toContain('uppercase')
        ->and($css)->toContain('.page-title')
        ->and($css)->toContain('.bottom-drawer')
        ->and($css)->toContain('.page-kicker')
        ->toContain('.field-label')
        ->and($css)->not->toContain('text-transform: uppercase');
});

it('exposes focus, caption, dark domain, and motion tokens', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));
    $button = file_get_contents(resource_path('js/Components/ui/button/index.ts'));
    $card = file_get_contents(resource_path('js/Components/ui/card/Card.vue'));
    $ring = file_get_contents(resource_path('js/Components/CalorieRing.vue'));

    expect($css)
        ->toContain('prefers-reduced-motion')
        ->toContain('prefers-contrast')
        ->toContain('--primary: #24382b')
        ->toContain('--background: #f4f4f5')
        ->toContain('--card: #ffffff')
        ->toContain('--radius: 0.5rem')
        ->toContain('--protein: #6eb8d8')
        ->toContain('font-size: 0.8125rem')
        ->not->toContain('font-size: 0.7rem')
        ->not->toContain('font-size: 0.75rem')
        ->and($button)->toContain('focus-visible:ring-2')
        ->and($button)->toContain('text-xs')
        ->and($button)->not->toContain('text-[10px]')
        ->and($card)->toContain('shadow-card')
        ->and($card)->not->toContain('ring-1')
        ->and($ring)->toContain('role="img"');
});

it('uses page kickers only for dates', function (): void {
    $add = file_get_contents(resource_path('js/Pages/Add.vue'));
    $macros = file_get_contents(resource_path('js/Pages/MacroBreakdown.vue'));
    $weekly = file_get_contents(resource_path('js/Pages/Weekly.vue'));
    $goals = file_get_contents(resource_path('js/Pages/Goals.vue'));
    $progress = file_get_contents(resource_path('js/Pages/Progress.vue'));
    $onboarding = file_get_contents(resource_path('js/Pages/Onboarding.vue'));
    $account = file_get_contents(resource_path('js/Pages/Account.vue'));
    $header = file_get_contents(resource_path('js/Components/PageHeader.vue'));

    expect($header)->toContain('kicker')
        ->and($add)->toContain(':kicker="displayDate"')
        ->and($add)->toContain('grid-cols-3')
        ->and($add)->toContain('startScan')
        ->and($add)->toContain('mode=custom')
        ->and($macros)->toContain(':kicker="displayDate"')
        ->and($weekly)->toContain('formatDisplayDate(roundup.start_date)')
        ->and($weekly)->toContain(':kicker=')
        ->and($goals)->not->toContain('kicker')
        ->and($progress)->not->toContain('kicker')
        ->and($onboarding)->not->toContain('kicker')
        ->and($account)->not->toContain('page-kicker');
});

it('presents goals as a calorie target and named macro split', function (): void {
    $goals = file_get_contents(resource_path('js/Pages/Goals.vue'));

    expect($goals)
        ->toContain('PageHeader')
        ->toContain('nudgeCalories')
        ->toContain('kcal per day')
        ->toContain('High protein')
        ->toContain('Balanced')
        ->toContain('Higher fat')
        ->toContain('bg-protein')
        ->toContain('bg-carbs')
        ->toContain('bg-fat')
        ->not->toContain('100% allocated');
});

it('routes overlays through AppSheet', function (): void {
    $sheet = file_get_contents(resource_path('js/Components/AppSheet.vue'));
    $shell = file_get_contents(resource_path('js/Layouts/AppShell.vue'));
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $progress = file_get_contents(resource_path('js/Pages/Progress.vue'));
    $add = file_get_contents(resource_path('js/Pages/Add.vue'));
    $settings = file_get_contents(resource_path('js/Pages/Settings.vue'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($sheet)
        ->toContain('role="dialog"')
        ->toContain('aria-modal')
        ->toContain('Sheet')
        ->toContain('Dialog')
        ->and($css)->toContain('justify-items: center')
        ->and($shell)->toContain('AppSheet')
        ->and($shell)->not->toContain('h-1 w-10')
        ->and($today)->toContain('AppSheet')
        ->and($progress)->toContain('AppSheet')
        ->and($add)->toContain('AppSheet')
        ->and($settings)->toContain('AppSheet');
});

it('confirms deletes in-app', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $progress = file_get_contents(resource_path('js/Pages/Progress.vue'));

    expect($today)
        ->toContain('ConfirmSheet')
        ->not->toContain('window.confirm')
        ->and($progress)->toContain('ConfirmSheet')
        ->and($progress)->not->toContain('window.confirm');
});

it('encodes day status with shape and words', function (): void {
    $status = file_get_contents(resource_path('js/dayStatus.ts'));
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $weekly = file_get_contents(resource_path('js/Pages/Weekly.vue'));

    expect($status)
        ->toContain('on target')
        ->toContain('under target')
        ->toContain('over target')
        ->toContain('no log')
        ->toContain('border-2 border-warning')
        ->and($today)->toContain('dayStatusLabel')
        ->and($today)->not->toContain('${day.status}')
        ->and($weekly)->toContain('dayStatusLabel');
});
