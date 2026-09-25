<?php

use App\BuffApiStatus;
use App\Models\SyncState;
use App\Services\AnalyticsEventService;
use App\Services\BuffCredentialStore;
use App\Services\BuffSyncService;
use App\Services\LocalAccountData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app(BuffCredentialStore::class)->store('analytics-token', ['id' => 'account-one']);
    SyncState::current('account-one');
    Http::preventStrayRequests();
});

it('sends each recorded event through the next successful sync', function (): void {
    Http::fake([
        '*/sync' => Http::response(['acknowledged' => [], 'changes' => [], 'cursor' => 0, 'has_more' => false]),
        '*/analytics/events' => Http::response(['accepted' => 2]),
    ]);
    $analytics = app(AnalyticsEventService::class);
    $analytics->record('meal_logged');
    $analytics->record('meal_logged');

    $this->assertDatabaseCount('pending_analytics_events', 2);
    expect(app(BuffSyncService::class)->sync()->successful())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/analytics/events')
        && count($request['events']) === 2
        && $request['events'][0]['id'] !== $request['events'][1]['id']
        && $request['events'][0]['name'] === 'meal_logged'
        && $request['events'][1]['name'] === 'meal_logged');
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('uploads analytics even when record sync fails', function (): void {
    Http::fake([
        '*/analytics/events' => Http::response(['accepted' => 1]),
        '*/sync' => Http::response(['message' => 'Record sync unavailable.'], 503),
    ]);
    app(AnalyticsEventService::class)->record('meal_logged');

    expect(app(BuffSyncService::class)->sync()->status)->toBe(BuffApiStatus::Failed);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/analytics/events'));
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('keeps events and their IDs until the server acknowledges the whole batch', function (): void {
    $sentIds = [];
    Http::fake([
        '*/analytics/events' => function (Request $request) use (&$sentIds) {
            $sentIds[] = $request['events'][0]['id'];

            return Http::response(['accepted' => count($sentIds) === 1 ? 0 : 1]);
        },
    ]);
    $analytics = app(AnalyticsEventService::class);
    $analytics->record('workout_logged');

    $analytics->flush();
    $this->assertDatabaseCount('pending_analytics_events', 1);
    $analytics->flush();

    expect($sentIds)->toHaveCount(2)
        ->and($sentIds[0])->toBe($sentIds[1]);
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('drains more than one server batch on a single flush', function (): void {
    Http::fake([
        '*/analytics/events' => fn (Request $request) => Http::response(['accepted' => count($request['events'])]),
    ]);
    $analytics = app(AnalyticsEventService::class);

    for ($index = 0; $index < 51; $index++) {
        $analytics->record('meal_logged');
    }

    $analytics->flush();

    Http::assertSentCount(2);
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('does not send another account’s events and clears them with local data', function (): void {
    $analytics = app(AnalyticsEventService::class);
    $analytics->record('progress_logged');
    app(BuffCredentialStore::class)->store('second-token', ['id' => 'account-two']);
    Http::fake(['*/analytics/events' => Http::response(['accepted' => 1])]);

    $analytics->flush();

    Http::assertNothingSent();
    $this->assertDatabaseCount('pending_analytics_events', 1);
    app(LocalAccountData::class)->wipe();
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('does not record events while signed out', function (): void {
    app(BuffCredentialStore::class)->clearToken();

    app(AnalyticsEventService::class)->record('meal_logged');

    $this->assertDatabaseEmpty('pending_analytics_events');
});
