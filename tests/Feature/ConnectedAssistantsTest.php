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

it('lists connected assistants from the account API', function (): void {
    config()->set('buff.mcp_url', 'https://api.example.test/mcp/buff');

    $chatGptId = '10000000-0000-4000-8000-000000000042';
    $claudeId = '10000000-0000-4000-8000-000000000043';
    Http::fake([
        '*/mcp/connections' => Http::response(['data' => [[
            'id' => $chatGptId,
            'client_name' => 'ChatGPT',
            'linked_at' => '2026-08-24T10:00:00+00:00',
            'last_used_at' => '2026-08-24T11:00:00+00:00',
            'revoked_at' => null,
        ], [
            'id' => $claudeId,
            'client_name' => 'Claude',
            'linked_at' => '2026-08-20T10:00:00+00:00',
            'last_used_at' => null,
            'revoked_at' => '2026-08-21T10:00:00+00:00',
        ]]]),
    ]);

    $this->get('/settings/connected-assistants')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ConnectedAssistants')
            ->has('connections', 2)
            ->where('connections.0.id', $chatGptId)
            ->where('connections.0.clientName', 'ChatGPT')
            ->where('connections.0.revokedAt', null)
            ->where('connections.1.clientName', 'Claude')
            ->where('connections.1.revokedAt', '2026-08-21T10:00:00+00:00')
            ->where('error', null)
            ->where('mcpEndpoint', 'https://api.example.test/mcp/buff'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://dev.api.usebuff.app/api/v1/mcp/connections'
        && $request->hasHeader('Authorization', 'Bearer mobile-token'));
});

it('hides MCP installation when no endpoint is configured', function (): void {
    config()->set('buff.mcp_url');
    Http::fake([
        '*/mcp/connections' => Http::response(['data' => []]),
    ]);

    $this->get('/settings/connected-assistants')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ConnectedAssistants')
            ->where('mcpEndpoint', null));
});

it('revokes one connected assistant through the account API', function (): void {
    $connectionId = '10000000-0000-4000-8000-000000000042';
    Http::fake([
        '*/mcp/connections/'.$connectionId => Http::response(status: 204),
    ]);

    $this->from('/settings/connected-assistants')
        ->delete('/settings/connected-assistants/'.$connectionId)
        ->assertRedirect('/settings/connected-assistants')
        ->assertSessionHas('message', 'Assistant access revoked.');

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://dev.api.usebuff.app/api/v1/mcp/connections/'.$connectionId
        && $request->hasHeader('Authorization', 'Bearer mobile-token'));
});

it('shows account API failures without exposing connection data', function (): void {
    Http::fake([
        '*/mcp/connections' => Http::response(['message' => 'Service unavailable.'], 503),
    ]);

    $this->get('/settings/connected-assistants')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ConnectedAssistants')
            ->has('connections', 0)
            ->where('error', 'Service unavailable.'));

    $this->delete('/settings/connected-assistants/not-a-number')->assertNotFound();
});

it('reports a failed revocation without claiming success', function (): void {
    $connectionId = '10000000-0000-4000-8000-000000000042';
    Http::fake([
        '*/mcp/connections/'.$connectionId => Http::response(['message' => 'Connection not found.'], 404),
    ]);

    $this->from('/settings/connected-assistants')
        ->delete('/settings/connected-assistants/'.$connectionId)
        ->assertRedirect('/settings/connected-assistants')
        ->assertSessionHasErrors(['connection' => 'Connection not found.'])
        ->assertSessionMissing('message');
});

it('returns expired app sessions to sign in from assistant settings', function (): void {
    Http::fake([
        '*/mcp/connections' => Http::response([], 401),
    ]);

    $this->get('/settings/connected-assistants')
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', url('/settings/connected-assistants'));

    expect(app(BuffCredentialStore::class)->token())->toBeNull();
});

it('returns expired app sessions to sign in after revocation', function (): void {
    $connectionId = '10000000-0000-4000-8000-000000000042';
    Http::fake([
        '*/mcp/connections/'.$connectionId => Http::response([], 401),
    ]);

    $this->delete('/settings/connected-assistants/'.$connectionId)
        ->assertRedirect('/account/login')
        ->assertSessionHas('url.intended', url('/settings/connected-assistants'));

    expect(app(BuffCredentialStore::class)->token())->toBeNull();
});
