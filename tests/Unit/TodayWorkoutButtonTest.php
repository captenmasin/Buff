<?php

it('shows an add workout action above the workouts card', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)->toContain('<Button :as="Link" :href="`/add?mode=workout&date=${summary.date}`" size="sm"><Plus class="w-4" />Add workout</Button>');
});

it('labels the workout sync action and matches the small action size', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)
        ->toMatch('/v-if="canSyncHealthConnect"\s+variant="outline"\s+size="sm"/')
        ->toMatch('/<RefreshCw :size="16"[^>]*\/>\s+Sync/');
});

it('keeps logging available when the selected day has no meals or workouts', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)
        ->toMatch('/<h2[^>]*>Meals<\/h2>\s*<Button :as="Link" :href="addFoodUrl"/')
        ->toContain('No meals logged.')
        ->toContain('No workouts logged.');
});

it('opens food logging for the selected date and preselects the current meal on today', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)
        ->toContain('const isToday = computed(() => props.week.some((day) => day.is_selected && day.is_today));')
        ->toContain('/add?mode=food&date=${props.summary.date}${isToday.value ? `&meal=${currentMeal.value}` : \'\'}')
        ->toContain('<Button :as="Link" :href="addFoodUrl"');
});

it('shows the food logging streak at the bottom of today', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)->toContain('<p class="text-center text-xs text-muted-foreground">🔥 {{ summary.streak }} day streak</p>');
});
