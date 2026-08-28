<?php

it('shows an add workout action above the workouts card', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)->toContain('<Button :as="Link" :href="`/add?mode=workout&date=${summary.date}`" size="sm"><Plus class="w-4" />Add workout</Button>');
});

it('labels the workout sync action and matches the small action size', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)
        ->toContain('v-if="canSyncHealthConnect"'.PHP_EOL.'                            variant="outline"'.PHP_EOL.'                            size="sm"')
        ->toContain('<RefreshCw :size="16" :class="{ \'animate-spin\': healthConnectLoading }"/>'.PHP_EOL.'                            Sync');
});

it('shows the start today card only for the selected current day', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)
        ->toContain('const isToday = computed(() => props.week.some((day) => day.is_selected && day.is_today));')
        ->toContain('<Card v-if="hasGoal && isEmptyDay && isToday">')
        ->toContain('const showDayLists = computed(() => !isEmptyDay.value || !isToday.value);');
});

it('opens the add sheet from the start today food action', function (): void {
    $appShell = file_get_contents(__DIR__.'/../../resources/js/Layouts/AppShell.vue');
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($appShell)->toContain("provide<() => void>('openAddDrawer', openAddDrawer);");
    expect($todayPage)
        ->toContain("const openAddDrawer = inject<() => void>('openAddDrawer')!;")
        ->toContain('<Button variant="default" @click="openAddDrawer()">Add food</Button>')
        ->not->toContain('<Button :as="Link" :href="`/add?mode=food&date=${summary.date}`" variant="default">Add food</Button>');
});

it('shows the food logging streak at the bottom of today', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)->toContain('<p class="text-center text-xs text-muted-foreground">🔥 {{ summary.streak }} day streak</p>');
});
