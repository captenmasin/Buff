<?php

use App\Http\Middleware\EnsureBuffAccount;
use App\Services\BuffCredentialStore;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withMiddleware(EnsureBuffAccount::class);
    Http::preventStrayRequests();
});

it('requires a signed-in Buff account', function (): void {
    $this->get('/settings/subscription')->assertRedirect('/account/login');
    $this->postJson('/subscription/refresh')->assertRedirect('/account/login');
});

it('renders the subscription settings page for a signed-in account', function (): void {
    storeSubscriptionAccount();

    $this->get('/settings/subscription')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Subscription'));
});

it('refreshes from the API without trusting client entitlement fields', function (): void {
    storeSubscriptionAccount();
    Http::fake(['*/subscription/refresh' => Http::response([
        'data' => [
            ...subscriptionAccount(),
            'subscription' => [
                'entitled' => true,
                'expires_at' => '2026-09-29T12:00:00.000000Z',
                'product_id' => 'monthly-product',
                'store' => 'app_store',
                'management_url' => 'https://apps.apple.com/account/subscriptions',
            ],
        ],
    ])]);

    $this->postJson('/subscription/refresh', [
        'entitled' => false,
        'expires_at' => null,
        'receipt' => 'untrusted',
    ])->assertOk()
        ->assertJsonPath('data.subscription.entitled', true);

    expect(app(BuffCredentialStore::class)->account()['subscription']['product_id'])->toBe('monthly-product');
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/subscription/refresh')
        && $request->data() === []);
});

it('preserves cached state and normalized provider failures', function (): void {
    storeSubscriptionAccount();
    Http::fake(['*/subscription/refresh' => Http::response([
        'message' => 'Subscriptions are temporarily unavailable.',
        'code' => 'subscription_unavailable',
    ], 503)]);

    $this->postJson('/subscription/refresh')
        ->assertStatus(503)
        ->assertJsonPath('code', 'subscription_unavailable')
        ->assertJsonPath('message', 'Subscriptions are temporarily unavailable.');

    expect(app(BuffCredentialStore::class)->account()['subscription']['expires_at'])->toBeNull();
});

/** @return array<string, mixed> */
function subscriptionAccount(): array
{
    return [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
        'revenuecat_app_user_id' => '10000000-0000-4000-8000-000000000001',
        'subscription' => [
            'entitled' => false,
            'expires_at' => null,
            'product_id' => null,
            'store' => null,
            'management_url' => null,
        ],
    ];
}

function storeSubscriptionAccount(): void
{
    app(BuffCredentialStore::class)->store('subscription-token', subscriptionAccount());
}
