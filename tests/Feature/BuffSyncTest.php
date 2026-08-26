<?php

use App\BuffApiStatus;
use App\Models\BodyMetric;
use App\Models\DailyLog;
use App\Models\MealEntry;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use App\Services\BuffSyncService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $account = [
        'id' => '10000000-0000-4000-8000-000000000001',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('sync-token', $account);
    SyncState::current($account['id']);
});

afterEach(function (): void {
    Date::setTestNow();
});

it('captures complete normalized snapshots and fresh delete tombstones', function (): void {
    Date::setTestNow('2026-08-15T10:00:00.123456Z');
    $log = DailyLog::query()->create([
        'date' => '2026-08-15',
        'burned_calories' => 250,
    ]);

    $outbox = SyncOutbox::query()->where('record_id', $log->id)->firstOrFail();

    expect($outbox->payload)->toBe([
        'date' => '2026-08-15',
        'burned_calories' => 250,
    ])->and($outbox->client_updated_at->format('Y-m-d\TH:i:s.u\Z'))
        ->toMatch('/\.\d{6}Z$/');

    $snapshotTimestamp = $outbox->client_updated_at->copy();
    $log->delete();
    $outbox->refresh();
    Date::setTestNow();

    expect($outbox->is_deleted)->toBeTrue()
        ->and($outbox->payload)->toBeNull()
        ->and($outbox->client_updated_at->greaterThan($snapshotTimestamp))->toBeTrue();
});

it('pushes local changes, applies remote changes, and advances the cursor atomically', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Local meal',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 40,
        'fat_g' => 13.33,
    ]);

    Http::fake(function (ClientRequest $request) use ($meal) {
        return Http::response([
            'acknowledged' => [[
                'type' => 'meal_entries',
                'id' => $meal->id,
                'accepted' => true,
                'event_id' => 4,
            ]],
            'changes' => [[
                'type' => 'body_metrics',
                'id' => '20000000-0000-4000-8000-000000000002',
                'updated_at' => '2026-08-15T10:00:00.123456Z',
                'source_device_id' => '30000000-0000-4000-8000-000000000003',
                'deleted' => false,
                'data' => [
                    'date' => '2026-08-15',
                    'weight_kg' => 80,
                    'body_fat_percent' => 15,
                    'chest_cm' => 102.5,
                    'waist_cm' => 84.2,
                    'hips_cm' => null,
                    'upper_arm_cm' => 35.1,
                    'thigh_cm' => 58.4,
                    'notes' => null,
                ],
            ]],
            'cursor' => 9,
            'has_more' => false,
        ]);
    });

    $result = app(BuffSyncService::class)->sync();

    expect($result->successful())->toBeTrue()
        ->and(SyncState::current()->cursor)->toBe(9)
        ->and(BodyMetric::query()->find('20000000-0000-4000-8000-000000000002')?->weight_kg)->toBe('80.00')
        ->and(BodyMetric::query()->find('20000000-0000-4000-8000-000000000002')?->chest_cm)->toBe('102.50');
    $this->assertDatabaseMissing('sync_outboxes', ['record_id' => $meal->id]);

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasHeader('Authorization', 'Bearer sync-token')
        && $request['changes'][0]['data']['date'] === '2026-08-15');
});

it('applies the server record after a stale local conflict', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Losing edit',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 40,
        'fat_g' => 13.33,
    ]);

    Http::fake(['*/sync' => Http::response([
        'acknowledged' => [[
            'type' => 'meal_entries',
            'id' => $meal->id,
            'accepted' => false,
            'server_record' => [
                'type' => 'meal_entries',
                'id' => $meal->id,
                'updated_at' => now()->addMinute()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'source_device_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
                'deleted' => false,
                'data' => [
                    'date' => '2026-08-15',
                    'meal_type' => 'dinner',
                    'source_type' => 'custom',
                    'food_product_id' => null,
                    'name' => 'Server winner',
                    'portion_quantity' => 100,
                    'portion_unit' => 'g',
                    'calories' => 500,
                    'protein_g' => 35,
                    'carbs_g' => 50,
                    'fat_g' => 17.78,
                ],
            ],
        ]],
        'changes' => [],
        'cursor' => 10,
        'has_more' => false,
    ])]);

    app(BuffSyncService::class)->sync();

    expect($meal->refresh()->name)->toBe('Server winner');
    $this->assertDatabaseMissing('sync_outboxes', ['record_id' => $meal->id]);
});

it('reconciles concurrent body metrics by date and accepts meals whose catalogue item is not cached', function (): void {
    BodyMetric::query()->create([
        'date' => '2026-08-15',
        'weight_kg' => 80,
    ]);
    SyncOutbox::query()->delete();
    $remoteMetricId = '20000000-0000-4000-8000-000000000002';
    $remoteMealId = '30000000-0000-4000-8000-000000000003';

    Http::fake(['*/sync' => Http::response([
        'acknowledged' => [],
        'changes' => [
            [
                'type' => 'body_metrics',
                'id' => $remoteMetricId,
                'updated_at' => '2026-08-15T10:00:00.123456Z',
                'source_device_id' => '40000000-0000-4000-8000-000000000004',
                'deleted' => false,
                'data' => ['date' => '2026-08-15', 'weight_kg' => 81, 'body_fat_percent' => null, 'notes' => null],
            ],
            [
                'type' => 'meal_entries',
                'id' => $remoteMealId,
                'updated_at' => '2026-08-15T10:01:00.123456Z',
                'source_device_id' => '40000000-0000-4000-8000-000000000004',
                'deleted' => false,
                'data' => [
                    'date' => '2026-08-15',
                    'meal_type' => 'lunch',
                    'source_type' => 'barcode',
                    'food_product_id' => '50000000-0000-4000-8000-000000000005',
                    'name' => 'Remote food',
                    'portion_quantity' => 100,
                    'portion_unit' => 'g',
                    'calories' => 300,
                    'protein_g' => 20,
                    'carbs_g' => 40,
                    'fat_g' => 6.67,
                ],
            ],
        ],
        'cursor' => 2,
        'has_more' => false,
    ])]);

    expect(app(BuffSyncService::class)->sync()->successful())->toBeTrue();
    $this->assertDatabaseCount('body_metrics', 1);
    $this->assertDatabaseHas('body_metrics', [
        'id' => $remoteMetricId,
        'weight_kg' => 81,
    ]);
    $this->assertDatabaseHas('meal_entries', [
        'id' => $remoteMealId,
        'food_product_id' => '50000000-0000-4000-8000-000000000005',
    ]);
});

it('does not acknowledge an edit made while its older snapshot is in flight', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Sent snapshot',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 40,
        'fat_g' => 13.33,
    ]);

    Http::fake(function () use ($meal) {
        usleep(1000);
        $meal->update(['name' => 'Newer local edit']);

        return Http::response([
            'acknowledged' => [[
                'type' => 'meal_entries',
                'id' => $meal->id,
                'accepted' => true,
            ]],
            'changes' => [],
            'cursor' => 3,
            'has_more' => false,
        ]);
    });

    app(BuffSyncService::class)->sync();

    expect($meal->refresh()->name)->toBe('Newer local edit');
    $this->assertDatabaseHas('sync_outboxes', ['record_id' => $meal->id]);
});

it('pulls every page with an empty second push', function (): void {
    Http::fakeSequence()
        ->push([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 10,
            'has_more' => true,
        ])
        ->push([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 11,
            'has_more' => false,
        ]);

    app(BuffSyncService::class)->sync();

    expect(SyncState::current()->cursor)->toBe(11);
    Http::assertSentCount(2);
    $requests = Http::recorded();
    expect($requests[1][0]['changes'])->toBe([]);
});

it('drops an in-flight response after local logout removes the device state', function (): void {
    Http::fake(function () {
        SyncState::query()->delete();

        return Http::response([
            'acknowledged' => [],
            'changes' => [[
                'type' => 'body_metrics',
                'id' => '20000000-0000-4000-8000-000000000002',
                'updated_at' => '2026-08-15T10:00:00.123456Z',
                'source_device_id' => '30000000-0000-4000-8000-000000000003',
                'deleted' => false,
                'data' => ['date' => '2026-08-15', 'weight_kg' => 80, 'body_fat_percent' => null, 'notes' => null],
            ]],
            'cursor' => 1,
            'has_more' => false,
        ]);
    });

    $result = app(BuffSyncService::class)->sync();

    expect($result->status)->toBe(BuffApiStatus::Unauthenticated);
    $this->assertDatabaseEmpty('body_metrics');
    $this->assertDatabaseEmpty('sync_states');
});

it('keeps the cursor and outbox when the server response is malformed', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Pending meal',
        'calories' => 300,
        'protein_g' => 20,
        'carbs_g' => 40,
        'fat_g' => 6.67,
    ]);
    Http::fake(['*/sync' => Http::response([
        'acknowledged' => [[
            'type' => 'meal_entries',
            'id' => $meal->id,
            'accepted' => true,
        ]],
        'changes' => [['type' => 'unknown']],
        'cursor' => 99,
        'has_more' => false,
    ])]);

    expect(app(BuffSyncService::class)->sync()->status)->toBe(BuffApiStatus::Failed)
        ->and(SyncState::current()->cursor)->toBe(0);
    $this->assertDatabaseHas('sync_outboxes', ['record_id' => $meal->id]);
});
