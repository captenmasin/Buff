<?php

use App\Models\BodyMetric;
use App\Models\PendingBodyMetricPhotoUpload;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use App\Services\BuffSyncService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

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

function stubIdleSync(): void
{
    Http::fake([
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);
}

function stubAcknowledgingSync(?callable $onPhotoUpload = null): void
{
    Http::fake(function (ClientRequest $request) use ($onPhotoUpload) {
        if (str_ends_with($request->url(), '/sync')) {
            return Http::response([
                'acknowledged' => collect($request['changes'] ?? [])->map(fn (array $change): array => [
                    'type' => $change['type'],
                    'id' => $change['id'],
                    'accepted' => true,
                ])->all(),
                'changes' => [],
                'cursor' => 1,
                'has_more' => false,
            ]);
        }

        if (str_contains($request->url(), '/body-metrics/') && str_ends_with($request->url(), '/photos') && $request->method() === 'POST') {
            return $onPhotoUpload
                ? $onPhotoUpload($request)
                : Http::response(['photos' => []], 201);
        }

        return Http::response(['message' => 'unexpected '.$request->url()], 500);
    });
}

function outgoingField(ClientRequest $request, string $name): array
{
    return collect($request->data())
        ->filter(fn (mixed $part): bool => is_array($part) && (
            ($part['name'] ?? null) === $name
            || ($part['name'] ?? null) === $name.'[]'
            || str_starts_with((string) ($part['name'] ?? ''), $name.'[')
        ))
        ->flatMap(function (array $part): array {
            $contents = $part['contents'] ?? null;

            return is_array($contents) ? $contents : [$contents];
        })
        ->values()
        ->all();
}

it('proxies multipart body metric photos when the metric is not pending sync', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);
    SyncOutbox::query()->where('record_id', $metric->id)->delete();

    Http::fake([
        "*/body-metrics/{$metric->id}/photos" => Http::response([
            'photos' => [[
                'id' => '30000000-0000-4000-8000-000000000003',
                'url' => 'https://objects.example.com/signed-photo?expires=600',
                'mime_type' => 'image/jpeg',
            ]],
        ], 201),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('progress.jpg', 800, 600)->size(900)],
        'poses' => ['front'],
    ])->assertOk()
        ->assertJsonPath('photos.0.id', '30000000-0000-4000-8000-000000000003');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === "https://dev.api.usebuff.app/api/v1/body-metrics/{$metric->id}/photos"
        && $request->hasHeader('Authorization', 'Bearer photo-token')
        && $request->hasFile('photos[]', filename: 'progress.jpg')
        && outgoingField($request, 'poses') === ['front']);

    $this->assertDatabaseCount('pending_body_metric_photo_uploads', 0);
});

it('stages photos while the body metric is still in the sync outbox', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);

    expect(SyncOutbox::query()->where('record_id', $metric->id)->exists())->toBeTrue();

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('front.jpg', 800, 600)->size(700),
            UploadedFile::fake()->image('side.jpg', 800, 600)->size(700),
        ],
        'poses' => ['front', 'side'],
    ])->assertOk()
        ->assertJsonPath('pending', true);

    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/body-metrics/')
        && str_ends_with($request->url(), '/photos')
        && $request->method() === 'POST');

    $pending = PendingBodyMetricPhotoUpload::query()->sole();
    expect($pending->body_metric_id)->toBe($metric->id)
        ->and($pending->paths)->toHaveCount(2)
        ->and($pending->original_names)->toContain('front.jpg', 'side.jpg')
        ->and($pending->poses)->toBe(['front', 'side']);

    foreach ($pending->paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
});

it('lists and serves staged pending photos when cloud has none yet', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('front.jpg', 800, 600)->size(700)],
        'poses' => ['front'],
    ])->assertOk()->assertJsonPath('pending', true);

    $pending = PendingBodyMetricPhotoUpload::query()->sole();

    Http::fake([
        "*/body-metrics/{$metric->id}/photos" => Http::response(['photos' => []]),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $list = $this->getJson("/progress/body-metrics/{$metric->id}/photos")
        ->assertOk()
        ->assertJsonPath('pending', true)
        ->assertJsonPath('photos.0.id', $pending->id.':0')
        ->assertJsonPath('photos.0.pose', 'front');

    $url = $list->json('photos.0.url');
    expect($url)->toContain("/progress/body-metrics/{$metric->id}/photos/pending/{$pending->id}/0");

    $this->get($url)->assertOk();
});

it('uploads staged photos after the body metric syncs', function (): void {
    stubAcknowledgingSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('progress.jpg', 800, 600)->size(900)],
        'poses' => ['front'],
    ])->assertOk()->assertJsonPath('pending', true);

    app(BuffSyncService::class)->sync();

    $this->assertDatabaseMissing('sync_outboxes', ['record_id' => $metric->id]);
    $this->assertDatabaseCount('pending_body_metric_photo_uploads', 0);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === "https://dev.api.usebuff.app/api/v1/body-metrics/{$metric->id}/photos"
        && $request->hasFile('photos[]')
        && outgoingField($request, 'poses') === ['front']);
});

it('keeps a failed staged upload for the next successful sync', function (): void {
    $uploadAvailable = false;

    stubAcknowledgingSync(function () use (&$uploadAvailable) {
        return $uploadAvailable
            ? Http::response(['photos' => []], 201)
            : Http::response(['message' => 'Provider unavailable.', 'code' => 'upload_unavailable'], 503);
    });

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('progress.jpg', 800, 600)->size(900)],
        'poses' => ['front'],
    ])->assertOk()->assertJsonPath('pending', true);

    $pending = PendingBodyMetricPhotoUpload::query()->sole();
    $pending->forceFill(['created_at' => now()->subDays(2)])->save();
    $path = $pending->paths[0];

    app(BuffSyncService::class)->sync();
    expect(PendingBodyMetricPhotoUpload::query()->sole()->last_error)->toBe('Provider unavailable.');
    Storage::disk('local')->assertExists($path);

    $uploadAvailable = true;
    app(BuffSyncService::class)->sync();
    $this->assertDatabaseCount('pending_body_metric_photo_uploads', 0);
    Storage::disk('local')->assertMissing($path);
});

it('proxies temporary photo urls without storing them on the body metric', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);
    $temporaryUrl = 'https://objects.example.com/signed-photo?expires=600';

    Http::fake([
        "*/body-metrics/{$metric->id}/photos" => Http::response(['photos' => [[
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

    $this->getJson("/progress/body-metrics/{$metric->id}/photos")
        ->assertOk()
        ->assertJsonPath('photos.0.url', $temporaryUrl);

    expect(json_encode($metric->fresh()->getAttributes()))->not->toContain($temporaryUrl);
});

it('proxies deleting a body metric photo', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);
    $photoId = '30000000-0000-4000-8000-000000000003';

    Http::fake([
        "*/body-metrics/{$metric->id}/photos/{$photoId}" => Http::response(status: 204),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $this->deleteJson("/progress/body-metrics/{$metric->id}/photos/{$photoId}")
        ->assertNoContent();

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === "https://dev.api.usebuff.app/api/v1/body-metrics/{$metric->id}/photos/{$photoId}"
        && $request->method() === 'DELETE');
});

it('validates photo count type size and pose', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [])
        ->assertSessionHasErrors(['photos', 'poses']);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
            UploadedFile::fake()->image('d.jpg'),
        ],
        'poses' => ['front', 'side', 'back', 'front'],
    ])->assertSessionHasErrors('photos');

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->create('notes.txt', 100, 'text/plain')],
        'poses' => ['front'],
    ])->assertSessionHasErrors('photos.0');

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('huge.jpg')->size(6 * 1024)],
        'poses' => ['front'],
    ])->assertSessionHasErrors('photos.0');

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('front.jpg')],
        'poses' => ['profile'],
    ])->assertSessionHasErrors('poses.0');

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
        'poses' => ['front', 'front'],
    ])->assertSessionHasErrors('poses.1');

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
        'poses' => ['front'],
    ])->assertSessionHasErrors('poses');
});

it('discards staged photos when a body metric is deleted', function (): void {
    stubIdleSync();

    $metric = BodyMetric::query()->create([
        'date' => '2026-08-20',
        'weight_kg' => 82.4,
    ]);

    $this->post("/progress/body-metrics/{$metric->id}/photos", [
        'photos' => [UploadedFile::fake()->image('progress.jpg', 800, 600)->size(900)],
        'poses' => ['front'],
    ])->assertOk()->assertJsonPath('pending', true);

    $pending = PendingBodyMetricPhotoUpload::query()->sole();
    $path = $pending->paths[0];

    $this->delete("/progress/body-metrics/{$metric->id}")->assertRedirect('/progress?range=90');

    $this->assertDatabaseMissing('pending_body_metric_photo_uploads', ['id' => $pending->id]);
    Storage::disk('local')->assertMissing($path);
});
