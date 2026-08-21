<?php

it('uses the app icon as the favicon', function (): void {
    expect(file_get_contents(resource_path('views/app.blade.php')))
        ->toContain('<link rel="icon" type="image/png" href="/icon.png">');
});

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
    $replacement = file_get_contents(resource_path('js/accountReplacement.ts'));
    $onboarding = file_get_contents(resource_path('js/Pages/Onboarding.vue'));

    expect($account)->toContain('defineOptions({ layout: null })')
        ->toContain('accountReplacementDecision')
        ->toContain('pendingSocialProvider')
        ->toContain('Clear device data')
        ->toMatch('/<\/form>\s+<\/Card>\s+<Button\s+v-if="hasDeviceData"/')
        ->toContain("clearDataForm.delete('/account/local-data')")
        ->toContain("signInWith('google')")
        ->toContain("signInWith('apple')")
        ->toContain('v-if="appleLoginAvailable"')
        ->toContain(':src="\'/icon.png\'"')
        ->toContain(':src="\'/icon-dark.png\'"')
        ->and($replacement)->toContain("hasLocalAccount ? 'confirm'")
        ->and($onboarding)->toContain('defineOptions({ layout: null })')
        ->toContain('Feet and inches')
        ->toContain('safe-area-inset-top')
        ->toContain('safe-area-inset-bottom');
});

it('shares page chrome across the main screens', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $settings = file_get_contents(resource_path('js/Pages/Settings.vue'));
    $bodyProfileEditor = file_get_contents(resource_path('js/Components/BodyProfileEditor.vue'));
    $shell = file_get_contents(resource_path('js/Layouts/AppShell.vue'));
    $macros = file_get_contents(resource_path('js/Components/Add/MacroSummary.vue'));
    $css = file_get_contents(resource_path('css/app.css'));

    expect($today)
        ->toContain('PageHeader')
        ->toContain('CalorieRing')
        ->toContain('Start today')
        ->toContain('<h2 class="text-lg font-semibold tracking-tight">Meals</h2>')
        ->not->toContain('v-if="hasMeals"')
        ->toContain('v-for="mealType in mealTypes"')
        ->toContain('rounded-2xl bg-card p-1.5 shadow-card')
        ->toContain('PopoverTrigger')
        ->toContain('from \'../Components/ui/calendar\'')
        ->toContain('layout="month-and-year"')
        ->not->toContain('type="date"')
        ->not->toContain('Copy yesterday')
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
        ->and($settings)->toContain('Feet and inches')
        ->and($bodyProfileEditor)->toContain('v-model.number="heightFeet"')
        ->toContain('v-model.number="heightInches"')
        ->not->toContain('Height {{ heightUnit }}')
        ->and($settings)->not->toContain('rounded-xl border border-border/80 bg-muted/40 p-3')
        ->and($settings)->not->toContain('Import / export')
        ->and($settings)->not->toContain('/settings/export')
        ->and($settings)->not->toContain('/settings/import')
        ->and($settings)->toContain('Sign out and remove local data')
        ->and($settings)->toContain('passwordResetUrl')
        ->and($settings)->toContain('Set or reset it by email first.')
        ->and($settings)->toContain('delete-account-password')
        ->and($shell)->toContain('bottom-nav')
        ->and($shell)->toContain('openAddDrawer')
        ->and($shell)->toContain('bottom-drawer')
        ->and($shell)->toContain('AddChooser')
        ->and($shell)->toContain('openAddMode')
        ->and($shell)->toMatch('/<Button\s+variant="default"\s+class="mt-4 h-11 justify-start px-3 text-sm"/')
        ->and($shell)->not->toContain('isAddActive')
        ->and($shell)->toContain('<Link href="/" class="mb-8 flex items-center gap-2 px-2">')
        ->and($shell)->toContain(':src="\'/icon.png\'"')
        ->and($shell)->toContain(':src="\'/icon-dark.png\'"')
        ->and($shell)->toContain('size-12 rounded-2xl dark:hidden')
        ->and($shell)->toContain('hidden size-12 rounded-2xl dark:block')
        ->and($shell)->not->toContain('addHref')
        ->and($macros)->toContain('field-label')
        ->and($macros)->not->toContain('uppercase')
        ->and($css)->toContain('.page-title')
        ->and($css)->toContain('.card-title')
        ->and($today)->toContain('card-title')
        ->and($settings)->toContain('card-title')
        ->and($css)->toContain('.bottom-drawer')
        ->and($css)->toContain('.page-kicker')
        ->toContain('.field-label')
        ->and($css)->not->toContain('text-transform: uppercase');
});

it('exposes focus, caption, dark domain, and motion tokens', function (): void {
    $css = file_get_contents(resource_path('css/app.css'));
    $button = file_get_contents(resource_path('js/Components/ui/button/index.ts'));
    $card = file_get_contents(resource_path('js/Components/Card.vue'));
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
        ->and($css)->toContain('font-family: inherit')
        ->and($css)->not->toContain('font: inherit')
        ->and($css)->toContain('--ease-out: cubic-bezier(0.23, 1, 0.32, 1)')
        ->and($css)->toContain('--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1)')
        ->and($css)->toContain('transition-property: color, background-color, border-color, box-shadow, opacity')
        ->and($css)->not->toContain('transition: none !important')
        ->and($css)->toContain('(pointer: fine)')
        ->and($button)->toContain('focus-visible:ring-2')
        ->and($button)->toContain('text-xs')
        ->and($button)->not->toContain('text-[10px]')
        ->and($button)->not->toContain('transition-all')
        ->and($button)->not->toContain('translate-y-px')
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
        ->and($add)->toContain("addModeUrl('custom')")
        ->and($macros)->toContain(':kicker="displayDate"')
        ->and($weekly)->toContain('formatDisplayDate(roundup.start_date)')
        ->and($weekly)->toContain(':kicker=')
        ->and($goals)->not->toContain('kicker')
        ->and($progress)->not->toContain('kicker')
        ->and($onboarding)->not->toContain('kicker')
        ->and($account)->not->toContain('page-kicker');
});

it('groups add options the same way in the drawer and on the add page', function (): void {
    $chooser = file_get_contents(resource_path('js/Components/Add/AddChooser.vue'));
    $shell = file_get_contents(resource_path('js/Layouts/AppShell.vue'));
    $add = file_get_contents(resource_path('js/Pages/Add.vue'));
    $recipes = file_get_contents(resource_path('js/Components/RecipeMode.vue'));

    expect($chooser)
        ->toContain("mode: 'food'")
        ->toContain("scan: '1'")
        ->toContain("mode: 'photo'")
        ->toContain("mode: 'custom'")
        ->toContain("mode: 'recipe'")
        ->toContain("mode: 'workout'")
        ->and($chooser)->toContain('grid-cols-2')
        ->and($chooser)->toContain('bg-muted/80')
        ->and($shell)->toContain('AddChooser')
        ->and($add)->toContain('AddChooser')
        ->and($add)->toContain('MealTypePicker')
        ->and($add)->toContain('queueFoodSearch')
        ->and($add)->toContain('previousCustomMeals')
        ->and($add)->toContain('min-w-0 flex-1 overflow-hidden')
        ->and($add)->not->toContain('Search, scan, or custom')
        ->and($recipes)->toContain('MealTypePicker')
        ->and($recipes)->not->toContain('field-label">Meal');
});

it('presents goals as a calorie target and named macro split', function (): void {
    $editor = file_get_contents(resource_path('js/Components/DailyTargetsEditor.vue'));
    $goals = file_get_contents(resource_path('js/Pages/Goals.vue'));
    $onboarding = file_get_contents(resource_path('js/Pages/Onboarding.vue'));

    expect($editor)
        ->toContain('NumberField')
        ->toContain('NumberFieldDecrement')
        ->toContain('NumberFieldIncrement')
        ->toContain('kcal per day')
        ->toContain(':class="cn(\'mt-1 h-auto')
        ->toContain('text-5xl font-bold')
        ->toContain('md:text-5xl')
        ->not->toContain('nudgeCalories')
        ->toContain('High protein')
        ->toContain('Balanced')
        ->toContain('Higher fat')
        ->toContain('bg-protein')
        ->toContain('bg-carbs')
        ->toContain('bg-fat')
        ->toContain('macro-wheel')
        ->toContain('snap-y')
        ->toContain('w-[3ch]')
        ->toContain('role="radiogroup"')
        ->toContain('{{ percent }}')
        ->not->toContain('{{ percent }}%')
        ->not->toContain('{{ customSplit.fat }}%')
        ->toContain('macro-value')
        ->not->toContain('mask-image')
        ->not->toContain('100% allocated')
        ->and($goals)->toContain('PageHeader')
        ->toContain('DailyTargetsEditor')
        ->not->toContain('nudgeCalories')
        ->and($onboarding)->toContain('DailyTargetsEditor')
        ->not->toContain('kcal from macros')
        ->not->toContain('macrosMatch')
        ->not->toContain('v-model.number="form.protein_g"');
});

it('does not animate the today week strip', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));

    expect($today)
        ->toContain('aria-label="Week"')
        ->toContain('flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold')
        ->not->toContain('flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold transition')
        ->not->toContain('absolute top-1.5 h-1.5 w-1.5 rounded-full bg-primary');
});

it('highlights meal rows across the card instead of hugging the text', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));

    expect($today)
        ->toContain('-mx-5 divide-y divide-border/60')
        ->toContain('-mx-5 mt-1 divide-y divide-border/60')
        ->toContain('Add meal')
        ->toContain('`/add?mode=food&date=${summary.date}&meal=${mealType}`')
        ->toContain('rounded-none border-0 px-5 py-2.5 text-left')
        ->not->toContain('h-auto min-w-0 flex-1 items-center justify-between gap-3 p-0 text-left');
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

it('hints previous weigh-in values on progress inputs', function (): void {
    $progress = file_get_contents(resource_path('js/Pages/Progress.vue'));

    expect($progress)
        ->toContain('previousWeight')
        ->toContain('previousBodyFat')
        ->toContain(':placeholder="previousWeight"')
        ->toContain(':placeholder="previousBodyFat"')
        ->toContain('weightFromKg(props.latest?.weight_kg, props.preferences.weight_unit)')
        ->toContain('`/progress/body-metrics?range=${encodeURIComponent(props.range)}`')
        ->toContain('v-for="metric in props.history"');
});

it('leaves calories burnt and custom macros empty so phone number entry is not blocked', function (): void {
    $add = file_get_contents(resource_path('js/Pages/Add.vue'));

    expect($add)
        ->toContain("calories_burned: ''")
        ->not->toContain('calories_burned: 0')
        ->toContain("protein_g: ''")
        ->toContain("carbs_g: ''")
        ->toContain("fat_g: ''")
        ->toContain('protein_g: Number(data.protein_g || 0)')
        ->toContain('placeholder="0"');
});

it('takes categorised progress photos', function (): void {
    $progress = file_get_contents(resource_path('js/Pages/Progress.vue'));
    $poses = file_get_contents(resource_path('js/progressPhotos.ts'));

    expect($progress)
        ->toContain('progressPhotoCaptureLabels')
        ->toContain('progressPhotoPoses')
        ->toContain('photo, empty')
        ->toContain('photo captured')
        ->toContain('openPhotos')
        ->toContain('View progress photos')
        ->toContain('loadHistoryPhotos')
        ->toContain('photoCache[metric.id]')
        ->toContain('addPhotosForMetric')
        ->toContain('addPhotosFromLibrary')
        ->toContain('photoTargetMetric')
        ->toContain('acquireCameraStream')
        ->toContain('selectPoseOverlays')
        ->toContain('No previous ${progressPhotoLabels[capturingPose].toLowerCase()} photo to ghost yet')
        ->toContain(':open="photosMetric !== null"')
        ->toContain('variant="drawer"')
        ->not->toContain('Take photo')
        ->and($poses)
        ->toContain("'Take front'")
        ->toContain("'Take side'")
        ->toContain("'Take back'");
});

it('confirms deletes in-app', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $progress = file_get_contents(resource_path('js/Pages/Progress.vue'));
    $confirm = file_get_contents(resource_path('js/Components/ConfirmSheet.vue'));

    expect($today)
        ->toContain('ConfirmSheet')
        ->not->toContain('window.confirm')
        ->and($progress)->toContain('ConfirmSheet')
        ->and($progress)->not->toContain('window.confirm')
        ->and($confirm)->toContain("emit('confirm')")
        ->and($confirm)->not->toContain('AlertDialogAction');
});

it('encodes day status with ticks, colour, and words', function (): void {
    $status = file_get_contents(resource_path('js/dayStatus.ts'));
    $indicator = file_get_contents(resource_path('js/Components/DayStatusIndicator.vue'));
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));
    $weekly = file_get_contents(resource_path('js/Pages/Weekly.vue'));

    expect($status)
        ->toContain('on target')
        ->toContain('under target')
        ->toContain('over target')
        ->toContain('no log')
        ->toContain('bg-success')
        ->toContain('bg-warning')
        ->toContain('bg-fat')
        ->and($indicator)->toContain('Check')
        ->toContain('dayStatusHasTick')
        ->toContain('rounded-full')
        ->and($today)->toContain('DayStatusIndicator')
        ->toContain('dayStatusLabel')
        ->and($today)->not->toContain('${day.status}')
        ->and($weekly)->toContain('DayStatusIndicator')
        ->toContain('dayStatusLabel')
        ->and($weekly)->toContain('class="divide-y divide-border/60 py-1.5"')
        ->and($weekly)->not->toContain('first:pt-1 last:pb-1');
});
