<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Services\BuffApiClient;
use App\Services\BuffApiResult;
use App\Services\BuffCredentialStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class McpApprovalController extends Controller
{
    public function __construct(
        private readonly BuffApiClient $api,
        private readonly BuffCredentialStore $credentials,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $token = $request->string('token')->toString();

        abort_unless(strlen($token) === 64 && ctype_alnum($token), 404);

        $result = $this->api->get('mcp/browser-approvals/'.$token);

        if ($result->status === BuffApiStatus::Unauthenticated) {
            $this->credentials->clearToken();

            return redirect()->guest(route('account.login'));
        }

        $approval = $this->approval($result);

        return Inertia::render('McpApproval', [
            'token' => $token,
            'approval' => $approval,
            'approved' => false,
            'error' => $approval === null ? $this->errorMessage($result) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'alpha_num'],
            'decision' => ['sometimes', 'string', 'in:denied'],
        ]);
        $result = $this->api->post('mcp/browser-approvals', $validated);

        if ($result->status === BuffApiStatus::Unauthenticated) {
            $this->credentials->clearToken();
            $request->session()->put('url.intended', route('mcp.approval.show', ['token' => $validated['token']]));

            return redirect()->route('account.login');
        }

        if (! $result->successful()) {
            throw ValidationException::withMessages([
                'token' => [$this->errorMessage($result)],
            ]);
        }

        $decision = $validated['decision'] ?? 'approved';

        if (($result->data['status'] ?? null) !== $decision) {
            throw ValidationException::withMessages([
                'token' => ["The connection request could not be {$decision}. Try again."],
            ]);
        }

        if ($decision === 'denied') {
            return redirect('/')->with('message', 'Connection request denied.');
        }

        return redirect()->route('mcp.approval.complete')->with('mcp_approval_complete', true);
    }

    public function complete(Request $request): Response
    {
        abort_unless($request->session()->pull('mcp_approval_complete') === true, 404);

        return Inertia::render('McpApproval', [
            'token' => null,
            'approval' => null,
            'approved' => true,
            'error' => null,
        ]);
    }

    /** @return array{status: string, clientName: string, redirectOrigin: string, expiresAt: string}|null */
    private function approval(BuffApiResult $result): ?array
    {
        $status = $result->data['status'] ?? null;
        $clientName = $result->data['client_name'] ?? null;
        $redirectOrigin = $result->data['redirect_origin'] ?? null;
        $expiresAt = $result->data['expires_at'] ?? null;

        if (! $result->successful()
            || ! in_array($status, ['pending', 'approved'], true)
            || ! is_string($clientName)
            || ! is_string($redirectOrigin)
            || ! is_string($expiresAt)) {
            return null;
        }

        return compact('status', 'clientName', 'redirectOrigin', 'expiresAt');
    }

    private function errorMessage(BuffApiResult $result): string
    {
        return match (true) {
            $result->httpStatus === 404 => 'This connection request is invalid or has expired.',
            $result->status === BuffApiStatus::ConnectionFailed => 'Could not connect to Buff. Check your connection and try again.',
            $result->status === BuffApiStatus::Unauthenticated => 'Your session expired. Sign in again to continue.',
            $result->status === BuffApiStatus::Forbidden => $result->message ?? 'This account cannot approve the connection.',
            $result->status === BuffApiStatus::RateLimited => 'Too many attempts. Try again shortly.',
            default => 'The connection request could not be loaded. Try again from your AI assistant.',
        };
    }
}
