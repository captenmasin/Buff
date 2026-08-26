<?php

use App\Models\MealEntry;
use App\Models\PendingMealAnalysisConfirmation;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use App\Services\BuffSyncService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $account = [
        'id' => '10000000-0000-4000-8000-000000000001',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('photo-token', $account);
    SyncState::current($account['id']);
});

it('does not configure rollout flags for integrated features', function (): void {
    expect(config('buff.features'))->toBeNull();
});

it('proxies a bounded multipart analysis without exposing the bearer token to Vue', function (): void {
    Http::fake(['*/meal-analyses' => Http::response([
        'analysis' => [
            'id' => '20000000-0000-4000-8000-000000000002',
            'status' => 'draft',
            'draft' => [
                'name' => 'Chicken rice bowl',
                'portion_quantity' => 450,
                'portion_unit' => 'g',
                'protein_g' => 42,
                'carbs_g' => 58,
                'fat_g' => 14,
                'calories' => 526,
                'confidence' => 0.82,
                'recognized_components' => ['chicken', 'rice'],
            ],
            'photo_count' => 1,
        ],
        'quota_remaining' => 4,
    ], 201)]);

    $this->post('/meal-analyses', [
        'photos' => [UploadedFile::fake()->image('meal.jpg', 800, 600)->size(900)],
        'note' => 'There is sauce underneath.',
    ])->assertOk()
        ->assertJsonPath('analysis.draft.name', 'Chicken rice bowl')
        ->assertJsonPath('quota_remaining', 4);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://dev.api.usebuff.app/api/v1/meal-analyses'
        && $request->hasHeader('Authorization', 'Bearer photo-token')
        && $request->hasFile('photos[]', filename: 'meal.jpg'));
});

it('accepts bridge-safe meal photo data URLs', function (): void {
    Http::fake(['*/meal-analyses' => Http::response([
        'analysis' => ['id' => '20000000-0000-4000-8000-000000000002'],
    ], 201)]);
    $photo = UploadedFile::fake()->image('meal.jpg', 800, 600);

    $this->postJson('/meal-analyses', [
        'photos' => ['data:image/jpeg;base64,'.base64_encode((string) file_get_contents($photo->getPathname()))],
    ])->assertOk();

    Http::assertSent(fn (ClientRequest $request): bool => $request->hasFile('photos[]'));
});

it('proxies a follow-up correction to revise the draft', function (): void {
    $analysisId = '20000000-0000-4000-8000-000000000002';

    Http::fake(["*/meal-analyses/{$analysisId}/follow-up" => Http::response([
        'analysis' => [
            'id' => $analysisId,
            'status' => 'draft',
            'draft' => [
                'name' => 'Chicken and blue cheese salad',
                'protein_g' => 35,
                'carbs_g' => 12,
                'fat_g' => 24,
            ],
        ],
    ])]);

    $this->postJson("/meal-analyses/{$analysisId}/follow-up", [
        'correction' => 'It was blue cheese, not feta.',
    ])->assertOk()->assertJsonPath('analysis.draft.name', 'Chicken and blue cheese salad');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === "https://dev.api.usebuff.app/api/v1/meal-analyses/{$analysisId}/follow-up"
        && $request['correction'] === 'It was blue cheese, not feta.');
});

it('syncs a reviewed meal before confirming its analysis', function (): void {
    $analysisId = '20000000-0000-4000-8000-000000000002';

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/sync')) {
            return Http::response([
                'acknowledged' => collect($request['changes'])->map(fn (array $change): array => [
                    'type' => $change['type'],
                    'id' => $change['id'],
                    'accepted' => true,
                ])->all(),
                'changes' => [],
                'cursor' => 1,
                'has_more' => false,
            ]);
        }

        return Http::response(['data' => ['status' => 'confirmed']]);
    });

    $this->post('/meals/custom', [
        'date' => '2026-08-15',
        'meal_type' => 'dinner',
        'name' => 'Edited chicken bowl',
        'portion_quantity' => 450,
        'portion_unit' => 'g',
        'protein_g' => 45,
        'carbs_g' => 55,
        'fat_g' => 13,
        'analysis_id' => $analysisId,
    ])->assertRedirect('/?date=2026-08-15');

    app(BuffSyncService::class)->sync();
    $meal = MealEntry::query()->sole();

    $this->assertDatabaseMissing('sync_outboxes', ['record_id' => $meal->id]);
    $this->assertDatabaseMissing('pending_meal_analysis_confirmations', ['analysis_id' => $analysisId]);
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), "/meal-analyses/{$analysisId}/confirm")
        && $request['meal_record_id'] === $meal->id);
});

it('keeps a failed confirmation for the next successful sync', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Chicken bowl',
        'portion_quantity' => 450,
        'portion_unit' => 'g',
        'calories' => 526,
        'protein_g' => 42,
        'carbs_g' => 58,
        'fat_g' => 14,
    ]);
    $analysisId = '20000000-0000-4000-8000-000000000002';
    PendingMealAnalysisConfirmation::query()->create([
        'analysis_id' => $analysisId,
        'meal_record_id' => $meal->id,
    ]);

    $confirmationAvailable = false;

    Http::fake(function (ClientRequest $request) use (&$confirmationAvailable) {
        if (str_ends_with($request->url(), '/sync')) {
            return Http::response([
                'acknowledged' => collect($request['changes'])->map(fn (array $change): array => [
                    'type' => $change['type'],
                    'id' => $change['id'],
                    'accepted' => true,
                ])->all(),
                'changes' => [],
                'cursor' => 1,
                'has_more' => false,
            ]);
        }

        return $confirmationAvailable
            ? Http::response(['data' => ['status' => 'confirmed']])
            : Http::response(['message' => 'Provider unavailable.', 'code' => 'meal_analysis_unavailable'], 503);
    });

    app(BuffSyncService::class)->sync();
    expect(PendingMealAnalysisConfirmation::query()->find($analysisId)?->last_error)->toBe('Provider unavailable.');

    $confirmationAvailable = true;
    app(BuffSyncService::class)->sync();
    $this->assertDatabaseMissing('pending_meal_analysis_confirmations', ['analysis_id' => $analysisId]);
});

it('retries an old pending confirmation instead of silently deleting it', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Chicken bowl',
        'calories' => 526,
        'protein_g' => 42,
        'carbs_g' => 58,
        'fat_g' => 14,
    ]);
    $analysisId = '20000000-0000-4000-8000-000000000002';
    PendingMealAnalysisConfirmation::query()->create([
        'analysis_id' => $analysisId,
        'meal_record_id' => $meal->id,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/sync')) {
            return Http::response([
                'acknowledged' => collect($request['changes'])->map(fn (array $change): array => [
                    'type' => $change['type'],
                    'id' => $change['id'],
                    'accepted' => true,
                ])->all(),
                'changes' => [],
                'cursor' => 1,
                'has_more' => false,
            ]);
        }

        return Http::response(['data' => ['status' => 'confirmed']]);
    });

    app(BuffSyncService::class)->sync();

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), "/meal-analyses/{$analysisId}/confirm"));
    $this->assertDatabaseMissing('pending_meal_analysis_confirmations', ['analysis_id' => $analysisId]);
});

it('proxies cancellation and temporary meal photo URLs without storing them', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-08-15',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Chicken bowl',
        'calories' => 526,
        'protein_g' => 42,
        'carbs_g' => 58,
        'fat_g' => 14,
    ]);
    $analysisId = '20000000-0000-4000-8000-000000000002';
    $temporaryUrl = 'https://objects.example.com/signed-photo?expires=600';

    Http::fake([
        "*/meal-analyses/{$analysisId}" => Http::response(status: 204),
        "*/meals/{$meal->id}/photos" => Http::response(['photos' => [[
            'id' => '30000000-0000-4000-8000-000000000003',
            'url' => $temporaryUrl,
            'mime_type' => 'image/jpeg',
        ]]]),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $this->deleteJson("/meal-analyses/{$analysisId}")->assertNoContent();
    $this->getJson("/meals/{$meal->id}/photos")
        ->assertOk()
        ->assertJsonPath('photos.0.url', $temporaryUrl);

    expect(json_encode($meal->fresh()->getAttributes()))->not->toContain($temporaryUrl);
});
