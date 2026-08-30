<?php

use App\Http\Middleware\EnsureBuffAccount;
use App\Services\BuffCredentialStore;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withMiddleware(EnsureBuffAccount::class);
    Http::preventStrayRequests();

    app(BuffCredentialStore::class)->store('mobile-token', [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ]);
});

it('resolves a deep-link token without approving it', function (): void {
    $token = str_repeat('A', 64);
    Http::fake([
        '*/mcp/browser-approvals/'.$token => Http::response([
            'status' => 'pending',
            'client_name' => 'ChatGPT',
            'redirect_origin' => 'https://chatgpt.com',
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ]),
    ]);

    $this->get('/mcp-approve?'.http_build_query(['token' => $token]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('McpApproval')
            ->where('token', $token)
            ->where('approval.status', 'pending')
            ->where('approval.clientName', 'ChatGPT')
            ->where('approval.redirectOrigin', 'https://chatgpt.com')
            ->where('approved', false)
            ->where('error', null));

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.usebuff.app/api/v1/mcp/browser-approvals/'.$token
        && $request->hasHeader('Authorization', 'Bearer mobile-token'));
});

it('approves only after the local consent form is posted', function (): void {
    $token = str_repeat('B', 64);
    Http::fake([
        '*/mcp/browser-approvals' => Http::response(['status' => 'approved']),
    ]);

    $this->post('/mcp-approve', ['token' => $token])
        ->assertRedirect(route('mcp.approval.complete'));

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->data() === ['token' => $token]
        && $request->hasHeader('Authorization', 'Bearer mobile-token'));

    $this->get('/mcp-approve/complete')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('McpApproval')
            ->where('approved', true)
            ->where('token', null));

    $this->get('/mcp-approve/complete')->assertNotFound();
});

it('denies a pending connection request from the consent form', function (): void {
    $token = str_repeat('H', 64);
    Http::fake([
        '*/mcp/browser-approvals' => Http::response(['status' => 'denied']),
    ]);

    $this->post('/mcp-approve', ['token' => $token, 'decision' => 'denied'])
        ->assertRedirect('/')
        ->assertSessionHas('message', 'Connection request denied.')
        ->assertSessionMissing('mcp_approval_complete');

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->data() === ['token' => $token, 'decision' => 'denied']
        && $request->hasHeader('Authorization', 'Bearer mobile-token'));
});

it('does not claim denial unless the account API confirms it', function (): void {
    $token = str_repeat('I', 64);
    Http::fake([
        '*/mcp/browser-approvals' => Http::response(['status' => 'approved']),
    ]);

    $this->from('/mcp-approve?token='.$token)
        ->post('/mcp-approve', ['token' => $token, 'decision' => 'denied'])
        ->assertRedirect('/mcp-approve?token='.$token)
        ->assertSessionHasErrors(['token' => 'The connection request could not be denied. Try again.'])
        ->assertSessionMissing('message');
});

it('requires a signed-in Buff account and a valid opaque token', function (): void {
    app(BuffCredentialStore::class)->clearToken();

    $this->get('/mcp-approve?token=invalid')
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', url('/mcp-approve?token=invalid'));
    Http::assertNothingSent();

    app(BuffCredentialStore::class)->replaceToken('mobile-token');

    $this->get('/mcp-approve?token=invalid')->assertNotFound();
    Http::assertNothingSent();
});

it('returns to the original approval after signing in', function (): void {
    $token = str_repeat('D', 64);
    $approvalUrl = url('/mcp-approve').'?'.http_build_query(['token' => $token]);
    app(BuffCredentialStore::class)->clearToken();

    $this->get('/mcp-approve?'.http_build_query(['token' => $token]))
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', $approvalUrl);

    Http::fake([
        '*/auth/login' => Http::response([
            'token' => 'signed-in-token',
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
    ])->assertRedirect($approvalUrl);
});

it('returns to the original approval after resuming the saved account', function (): void {
    $token = str_repeat('E', 64);
    $approvalUrl = url('/mcp-approve').'?'.http_build_query(['token' => $token]);
    app(BuffCredentialStore::class)->store('expired-token', [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ], 'refresh-token');
    app(BuffCredentialStore::class)->clearToken();

    $this->get('/mcp-approve?'.http_build_query(['token' => $token]))
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', $approvalUrl);

    Http::fake([
        '*/auth/resume' => Http::response([
            'token' => 'resumed-token',
            'refresh_token' => 'rotated-refresh-token',
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

    $this->post('/account/resume')->assertRedirect($approvalUrl);
});

it('returns expired app sessions to sign in without losing the approval request', function (): void {
    $token = str_repeat('F', 64);
    $approvalUrl = url('/mcp-approve').'?'.http_build_query(['token' => $token]);
    app(BuffCredentialStore::class)->store('expired-token', [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'timezone' => 'Europe/London',
        'email_verified' => true,
    ], 'refresh-token');
    Http::fake([
        '*/mcp/browser-approvals/'.$token => Http::response([], 401),
    ]);

    $this->get('/mcp-approve?'.http_build_query(['token' => $token]))
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', $approvalUrl);

    expect(app(BuffCredentialStore::class)->token())->toBeNull()
        ->and(app(BuffCredentialStore::class)->refreshToken())->toBe('refresh-token');

    $this->get('/account/login')->assertSuccessful();
});

it('returns an expired approval submission to sign in with its token intact', function (): void {
    $token = str_repeat('G', 64);
    $approvalUrl = url('/mcp-approve').'?'.http_build_query(['token' => $token]);
    Http::fake([
        '*/mcp/browser-approvals' => Http::response([], 401),
    ]);

    $this->post('/mcp-approve', ['token' => $token])
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', $approvalUrl);

    expect(app(BuffCredentialStore::class)->token())->toBeNull();
});

it('shows expired approval requests without posting approval', function (): void {
    $token = str_repeat('C', 64);
    Http::fake([
        '*/mcp/browser-approvals/'.$token => Http::response([], 404),
    ]);

    $this->get('/mcp-approve?'.http_build_query(['token' => $token]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('McpApproval')
            ->where('approval', null)
            ->where('error', 'This connection request is invalid or has expired.'));

    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET');
});
