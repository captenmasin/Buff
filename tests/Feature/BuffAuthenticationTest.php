<?php

use App\Http\Middleware\EnsureBuffAccount;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Models\FoodProduct;
use App\Models\Recipe;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Native\Mobile\Facades\System;

beforeEach(function (): void {
    $this->withMiddleware(EnsureBuffAccount::class);
    SyncState::query()->delete();
    Http::preventStrayRequests();
});

it('blocks registration while an active token exists without remote requests', function (): void {
    app(BuffCredentialStore::class)->store('token', [
        'id' => '1', 'name' => 'Mason', 'email' => 'mason@example.com', 'timezone' => 'Europe/London', 'email_verified' => false,
    ]);

    $this->get('/account/register')->assertRedirect('/');
    $this->post('/account/register', [])->assertRedirect('/');
    Http::assertNothingSent();
});

it('blocks registration when local identity data exists without remote requests', function (): void {
    app(BuffCredentialStore::class)->store('token', [
        'id' => '1', 'name' => 'Mason', 'email' => 'mason@example.com', 'timezone' => 'Europe/London', 'email_verified' => false,
    ]);
    app(BuffCredentialStore::class)->clearToken();

    $this->get('/account/register')->assertRedirect('/account/login');
    $this->post('/account/register', [])->assertRedirect('/account/login');
    Http::assertNothingSent();
});

it('blocks registration when sync state exists without credentials or remote requests', function (): void {
    SyncState::current('1');

    $this->get('/account/register')->assertRedirect('/account/login');
    $this->post('/account/register', [])->assertRedirect('/account/login');
    Http::assertNothingSent();
});

it('clears saved device data without signing in', function (): void {
    app(BuffCredentialStore::class)->store('expired-token', [
        'id' => '1', 'name' => 'Mason', 'email' => 'mason@example.com', 'timezone' => 'Europe/London', 'email_verified' => true,
    ]);
    app(BuffCredentialStore::class)->clearToken();
    SyncState::current('1');
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    $this->delete('/account/local-data')
        ->assertRedirect('/account/register')
        ->assertSessionHas('message', 'Device data cleared.');

    $this->assertDatabaseEmpty('daily_goals');
    $this->assertDatabaseEmpty('sync_states');
    Storage::disk('local')->assertMissing('buff/credentials.enc');
    app()->forgetInstance(BuffCredentialStore::class);
    expect(app(BuffCredentialStore::class)->account())->toBeNull();
    $this->get('/account/register')->assertOk();
    Http::assertNothingSent();
});

it('keeps local credentials and their encryption key out of native bundles', function (): void {
    expect(config('nativephp.cleanup_env_keys'))->toContain('APP_KEY')
        ->toContain('APP_PREVIOUS_KEYS')
        ->and(config('nativephp.cleanup_exclude_files'))->toContain('storage/app/private');
});

it('requires sign in even when offline account data exists', function (): void {
    $this->get('/')->assertRedirect('/account/login');
    $this->get('/account/register')->assertOk();

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

it('prefills the password reset request email', function (): void {
    $this->get('/account/forgot-password?email=mason%40example.com')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('screen', 'forgot')
            ->where('email', 'mason@example.com'));
});

it('only offers apple login on ios', function (bool $isIos): void {
    System::shouldReceive('isIos')->once()->andReturn($isIos);

    $this->get('/account/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('appleLoginAvailable', $isIos));
})->with([
    'iPhone' => true,
    'other platforms' => false,
]);

it('clears only a matching account token after password reset', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('revoked-token', $account);
    SyncState::current($account['id']);
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);
    Http::fake(['*/auth/reset-password' => Http::response(['message' => 'Password reset.'])]);

    $this->post('/reset-password', [
        'token' => 'reset-token',
        'email' => 'MASON@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect('/account/login');

    expect(app(BuffCredentialStore::class)->token())->toBeNull()
        ->and(app(BuffCredentialStore::class)->account()['email'])->toBe('mason@example.com');
    $this->assertDatabaseCount('daily_goals', 1);

    app(BuffCredentialStore::class)->store('current-token', $account);
    $this->post('/reset-password', [
        'token' => 'reset-token',
        'email' => 'other@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect('/account/login');

    expect(app(BuffCredentialStore::class)->token())->toBe('current-token');
    $this->assertDatabaseCount('daily_goals', 1);
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

    $this->get('/account/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('screen', 'register'));

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
        'current_weight_kg' => 90,
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

it('restores synced onboarding before redirecting after login', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('original-token', $account);
    SyncState::current($account['id']);
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    Http::fake([
        '*/auth/logout' => Http::response(['message' => 'Signed out.']),
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
            'changes' => [[
                'type' => 'daily_goals',
                'id' => '10000000-0000-4000-8000-000000000001',
                'updated_at' => '2026-08-26T10:00:00.000000Z',
                'source_device_id' => '20000000-0000-4000-8000-000000000002',
                'deleted' => false,
                'data' => [
                    'calories' => 2000,
                    'protein_g' => 170,
                    'carbs_g' => 195,
                    'fat_g' => 60,
                    'macro_calories' => 2000,
                    'target_weight_kg' => 80,
                    'target_body_fat_percent' => 15,
                    'height_cm' => 180,
                    'age' => 35,
                    'sex' => 'male',
                    'activity_level' => 'moderate',
                ],
            ]],
            'cursor' => 1,
            'has_more' => false,
        ]),
    ]);

    $this->post('/account/logout')->assertRedirect('/account/login');
    $this->assertDatabaseEmpty('daily_goals');

    $this->post('/account/login', [
        'email' => 'mason@example.com',
        'password' => 'password123',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/');

    $this->assertDatabaseHas('daily_goals', ['calories' => 2000]);
    $this->assertDatabaseHas('body_profiles', [
        'id' => BodyProfile::ID,
        'height_cm' => 180,
        'age' => 35,
    ]);
    $this->get('/')->assertOk();
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

it('resumes the saved device account without asking for credentials', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('expired-token', $account, 'device-refresh-token');
    app(BuffCredentialStore::class)->clearToken();
    SyncState::current('1');
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);
    Http::fake([
        '*/auth/resume' => Http::response([
            'token' => 'resumed-token',
            'refresh_token' => 'rotated-refresh-token',
            'user' => [...$account, 'id' => 1],
        ]),
    ]);

    $this->get('/account/login')->assertInertia(fn (Assert $page) => $page
        ->where('buff.account.email', 'mason@example.com')
        ->where('buff.can_resume', true));

    $this->post('/account/resume')->assertRedirect('/');

    app()->forgetInstance(BuffCredentialStore::class);
    expect(app(BuffCredentialStore::class)->token())->toBe('resumed-token')
        ->and(app(BuffCredentialStore::class)->refreshToken())->toBe('rotated-refresh-token');
    $this->assertDatabaseCount('daily_goals', 1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://dev.api.usebuff.app/api/v1/auth/resume'
        && $request->hasHeader('Authorization', 'Bearer device-refresh-token')
        && $request['device_name'] === 'Buff mobile');
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

it('keeps local data when the same account signs back in', function (): void {
    SyncState::current('1');
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    Http::fake([
        '*/auth/login' => Http::response([
            'token' => 'same-token',
            'user' => [
                'id' => 1,
                'name' => 'Mason',
                'email' => 'mason@example.com',
                'timezone' => 'Europe/London',
                'email_verified' => true,
            ],
        ]),
    ]);

    $this->post('/account/login', [
        'email' => 'mason@example.com',
        'password' => 'password123',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/');

    expect(app(BuffCredentialStore::class)->token())->toBe('same-token')
        ->and(SyncState::query()->value('account_id'))->toBe('1');
    $this->assertDatabaseCount('daily_goals', 1);
});

it('replaces local data when a different account signs in', function (): void {
    SyncState::current('1');
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

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
    ]);

    $this->post('/account/login', [
        'email' => 'other@example.com',
        'password' => 'password123',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/');

    expect(app(BuffCredentialStore::class)->token())->toBe('other-token')
        ->and(app(BuffCredentialStore::class)->account()['id'])->toBe('2')
        ->and(SyncState::query()->value('account_id'))->toBe('2');
    $this->assertDatabaseEmpty('daily_goals');
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
    Recipe::query()->create([
        'name' => 'Private recipe',
        'servings' => 1,
        'items' => [],
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
    $this->assertDatabaseEmpty('recipes');
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

    $this->from('/settings/account')->patch('/account', [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/settings/account')
        ->assertSessionHas('message', 'Account updated. Check your new email address when you can to verify it.');

    expect(app(BuffCredentialStore::class)->account()['email'])->toBe('updated@example.com')
        ->and(app(BuffCredentialStore::class)->account()['id'])->toBe('1');

    $this->get('/settings')->assertOk();
});

it('does not ask for email verification after a profile-only change', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => false,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    Http::fake(['*/account' => Http::response(['data' => [...$account, 'name' => 'Updated Name']])]);

    $this->from('/settings/account')->patch('/account', [
        'name' => 'Updated Name',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
    ])->assertRedirect('/settings/account')
        ->assertSessionHas('message', 'Account updated.');
});

it('updates the password from settings', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    SyncState::current($account['id']);
    Http::fake(['*/account/password' => Http::response(['message' => 'Password updated.'])]);

    $this->from('/settings/password')->put('/account/password', [
        'current_password' => 'password123',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect('/settings/password');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://dev.api.usebuff.app/api/v1/account/password'
        && $request->method() === 'PUT'
        && $request['current_password'] === 'password123'
        && $request['password'] === 'new-password-123'
        && $request['password_confirmation'] === 'new-password-123');
});

it('maps an incorrect current password onto the change-password form', function (): void {
    $account = [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ];
    app(BuffCredentialStore::class)->store('token', $account);
    Http::fake(['*/account/password' => Http::response([
        'message' => 'The given data was invalid.',
        'errors' => ['current_password' => ['The current password is incorrect.']],
    ], 422)]);

    $this->from('/settings/password')->put('/account/password', [
        'current_password' => 'wrong',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect('/settings/password')->assertSessionHasErrors('current_password');
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
        ->assertRedirect('/account/register');
    $this->assertDatabaseEmpty('daily_goals');
    Storage::disk('local')->assertMissing('buff/credentials.enc');
});

it('redeems a social sign-in code into local credentials', function (): void {
    Http::fake([
        '*/auth/social/redeem' => Http::response([
            'token' => 'social-token',
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

    $this->get('/account/social/callback?code='.str_repeat('a', 64))->assertRedirect('/');

    expect(app(BuffCredentialStore::class)->token())->toBe('social-token');
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/auth/social/redeem'));
});

it('continues social registration into onboarding', function (): void {
    Http::fake([
        '*/auth/social/redeem' => Http::response([
            'token' => 'social-token',
            'user' => [
                'id' => 1,
                'name' => 'Mase',
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

    $this->get('/account/social/callback?flow=register&code='.str_repeat('a', 64))
        ->assertRedirect('/onboarding');
});

it('returns failed social registration to the registration flow', function (): void {
    $this->get('/account/social/callback?flow=register&error=Sign-in+was+cancelled.')
        ->assertRedirect('/account/register')
        ->assertSessionHas('message', 'Sign-in was cancelled.');

    Http::assertNothingSent();
});
