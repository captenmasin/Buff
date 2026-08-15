<?php

use App\Http\Middleware\EnsureBuffAccount;
use App\Models\DailyGoal;
use App\Models\FoodProduct;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withMiddleware(EnsureBuffAccount::class);
    SyncState::query()->delete();
});

it('keeps local credentials and their encryption key out of native bundles', function (): void {
    expect(config('nativephp.cleanup_env_keys'))->toContain('APP_KEY')
        ->toContain('APP_PREVIOUS_KEYS')
        ->and(config('nativephp.cleanup_exclude_files'))->toContain('storage/app/private');
});

it('requires sign in even when offline account data exists', function (): void {
    $this->get('/')->assertRedirect('/account/login');
    $this->get('/account/register')->assertRedirect('/onboarding');

    SyncState::current('10000000-0000-4000-8000-000000000001');

    $this->get('/')->assertRedirect('/account/login');
    $this->get('/onboarding')->assertRedirect('/account/login');
    $this->get('/account/verification-status')->assertRedirect('/account/login');
    $this->post('/account/logout')->assertRedirect('/account/login');
    $this->post('/workouts', [
        'date' => '2026-08-15',
        'title' => 'Unsigned workout',
        'calories_burned' => 200,
        'time' => '09:00',
    ])->assertRedirect('/account/login');

    $this->assertDatabaseEmpty('workout_entries');
});

it('registers during onboarding without blocking unverified accounts', function (): void {
    Http::fake([
        '*/auth/register' => Http::response([
            'token' => 'registration-token',
            'user' => [
                'id' => 1,
                'name' => 'Mason',
                'email' => 'mason@example.com',
                'timezone' => 'Europe/London',
                'email_verified' => false,
            ],
        ], 201),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $this->get('/onboarding')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding')
            ->where('buff.needs_sign_in', true));

    $this->post('/account/register', [
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/onboarding');

    expect(app(BuffCredentialStore::class)->token())->toBe('registration-token')
        ->and(app(BuffCredentialStore::class)->account()['id'])->toBe('1')
        ->and(SyncState::current()->account_id)->toBe('1');

    $encryptedCredentials = Storage::disk('local')->get('buff/credentials.enc');
    expect($encryptedCredentials)->not->toContain('registration-token')
        ->not->toContain('mason@example.com');

    app()->forgetInstance(BuffCredentialStore::class);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://dev.api.usebuff.app/api/v1/auth/register'
        && $request['timezone'] === 'Europe/London'
        && $request['device_name'] === 'Buff mobile');

    $this->postJson('/sync')
        ->assertOk()
        ->assertJsonPath('status', 'Success');

    $this->post('/onboarding', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.6667,
        'height_cm' => 180,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
    ])->assertRedirect('/');

    $this->get('/')->assertOk();

    $this->get('/account/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('screen', 'verify')
            ->where('email', 'mason@example.com'));
});

it('stays signed in across requests after login', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    Http::fake([
        '*/auth/login' => Http::response([
            'token' => 'login-token',
            'user' => [
                'id' => 1,
                'name' => 'Mason',
                'email' => 'mason@example.com',
                'timezone' => 'Europe/London',
                'email_verified' => true,
            ],
        ]),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $this->post('/account/login', [
        'email' => 'mason@example.com',
        'password' => 'password123',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/');

    app()->forgetInstance(BuffCredentialStore::class);

    $this->get('/')->assertOk();
    expect(app(BuffCredentialStore::class)->token())->toBe('login-token')
        ->and(app(BuffCredentialStore::class)->rotationIsDue())->toBeFalse();
});

it('blocks the app after a token expires', function (): void {
    app(BuffCredentialStore::class)->store('expired-token', [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ]);
    SyncState::current('1');
    app(BuffCredentialStore::class)->clearToken();
    app()->forgetInstance(BuffCredentialStore::class);

    $this->get('/')->assertRedirect('/account/login');
});

it('drops unreadable persisted credentials', function (): void {
    Storage::disk('local')->put('buff/credentials.enc', 'not-encrypted');
    app()->forgetInstance(BuffCredentialStore::class);

    expect(app(BuffCredentialStore::class)->token())->toBeNull()
        ->and(app(BuffCredentialStore::class)->account())->toBeNull();
    Storage::disk('local')->assertMissing('buff/credentials.enc');
});

it('refreshes the locally cached verification status', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => false,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    SyncState::current($account['id']);

    Http::fake([
        '*/account' => Http::response(['data' => [...$account, 'id' => 1, 'email_verified' => true]]),
        '*/sync' => Http::response([
            'acknowledged' => [],
            'changes' => [],
            'cursor' => 0,
            'has_more' => false,
        ]),
    ]);

    $this->getJson('/account/verification-status')
        ->assertOk()
        ->assertJson(['verified' => true]);

    expect(app(BuffCredentialStore::class)->account()['email_verified'])->toBeTrue()
        ->and(app(BuffCredentialStore::class)->account()['id'])->toBe('1');
});

it('keeps offline data behind the same account identity after a process restart', function (): void {
    SyncState::current('1');

    Http::fake([
        '*/auth/login' => Http::response([
            'token' => 'other-token',
            'user' => [
                'id' => 2,
                'name' => 'Other',
                'email' => 'other@example.com',
                'timezone' => 'Europe/London',
                'email_verified' => true,
            ],
        ]),
        '*/auth/logout' => Http::response(['message' => 'Signed out.']),
    ]);

    $this->from('/account/login')->post('/account/login', [
        'email' => 'other@example.com',
        'password' => 'password123',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/account/login')->assertSessionHasErrors('email');

    expect(app(BuffCredentialStore::class)->token())->toBeNull();
});

it('logs out remotely and wipes only user-owned local data', function (): void {
    $account = [
        'id' => '10000000-0000-4000-8000-000000000001',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    SyncState::current($account['id']);
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);
    FoodProduct::query()->create([
        'id' => '30000000-0000-4000-8000-000000000003',
        'barcode' => '12345678',
        'name' => 'Shared food',
        'nutrition_unit' => 'g',
        'calories_per_100' => 100,
        'protein_per_100' => 1,
        'carbs_per_100' => 2,
        'fat_per_100' => 3,
    ]);

    Http::fake(['*/auth/logout' => Http::response(['message' => 'Signed out.'])]);

    $this->post('/account/logout')->assertRedirect('/account/login');

    $this->assertDatabaseEmpty('daily_goals');
    $this->assertDatabaseEmpty('sync_states');
    $this->assertDatabaseEmpty('sync_outboxes');
    $this->assertDatabaseCount('food_products', 1);
    Storage::disk('local')->assertMissing('buff/credentials.enc');
    app()->forgetInstance(BuffCredentialStore::class);
    expect(app(BuffCredentialStore::class)->token())->toBeNull()
        ->and(app(BuffCredentialStore::class)->account())->toBeNull();
});

it('keeps account access after an email change', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    SyncState::current($account['id']);
    Http::fake(['*/account' => Http::response(['data' => [
        ...$account,
        'id' => 1,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'email_verified' => false,
    ]])]);

    $this->from('/settings')->patch('/account', [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/settings');

    expect(app(BuffCredentialStore::class)->account()['email'])->toBe('updated@example.com')
        ->and(app(BuffCredentialStore::class)->account()['id'])->toBe('1');

    $this->get('/settings')->assertOk();
});

it('requires a successful server deletion before wiping local data', function (): void {
    $account = [
        'id' => '10000000-0000-4000-8000-000000000001',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    SyncState::current($account['id']);
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    $deletionAllowed = false;
    Http::fake(function () use (&$deletionAllowed): mixed {
        return $deletionAllowed
            ? Http::response(status: 204)
            : Http::response([
                'message' => 'The given data was invalid.',
                'errors' => ['password' => ['The password is incorrect.']],
            ], 422);
    });
    $this->from('/settings')->delete('/account', ['password' => 'wrong'])
        ->assertRedirect('/settings')->assertSessionHasErrors('password');
    $this->assertDatabaseCount('daily_goals', 1);

    $deletionAllowed = true;
    $this->delete('/account', ['password' => 'password123'])
        ->assertRedirect('/onboarding');
    $this->assertDatabaseEmpty('daily_goals');
    Storage::disk('local')->assertMissing('buff/credentials.enc');
});
