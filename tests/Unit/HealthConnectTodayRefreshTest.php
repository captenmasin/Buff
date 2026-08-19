<?php

it('refreshes the visible workout summary after a health connect sync changes state', function (): void {
    $todayPage = file_get_contents(__DIR__.'/../../resources/js/Pages/Today.vue');

    expect($todayPage)
        ->toContain("['permission_requested', 'sync_queued'].includes(healthImport.value?.state.status ?? '')")
        ->toContain('waitForSummaryRefresh && ! summaryRefreshed')
        ->toContain('refreshTodaySummaryWhenHealthConnectChanged();')
        ->toContain("only: ['summary', 'week', 'healthConnect', 'appleHealth']")
        ->toContain("prefix: '/apple-health'");
});
