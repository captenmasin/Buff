<?php

namespace App\Http\Controllers;

use App\BuffApiStatus;
use App\Models\SyncState;
use App\Services\BuffApiClient;
use App\Services\BuffApiResult;
use App\Services\BuffCredentialStore;
use App\Services\BuffSyncService;
use App\Services\LocalAccountData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly BuffApiClient $api,
        private readonly BuffCredentialStore $credentials,
        private readonly BuffSyncService $sync,
    ) {}

    public function loginPage(): Response|RedirectResponse
    {
        if ($this->credentials->token() !== null) {
            return $this->accountRedirect();
        }

        return $this->page('login');
    }

    public function registerPage(): RedirectResponse
    {
        if ($this->credentials->account() !== null) {
            return $this->accountRedirect();
        }

        return redirect('/onboarding');
    }

    public function forgotPasswordPage(): Response
    {
        return $this->page('forgot');
    }

    public function resetPasswordPage(Request $request): Response
    {
        return $this->page('reset', [
            'email' => $request->string('email')->toString(),
            'token' => $request->string('token')->toString(),
        ]);
    }

    public function verificationPage(): Response|RedirectResponse
    {
        $account = $this->credentials->account();

        if ($account === null || $this->credentials->token() === null) {
            return redirect()->route('account.login');
        }

        if (($account['email_verified'] ?? false) === true) {
            return redirect('/');
        }

        return $this->page('verify', ['email' => $account['email'] ?? null]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $result = $this->api->post('auth/register', [
            ...$validated,
            'password_confirmation' => $request->string('password_confirmation')->toString(),
            'device_name' => $this->deviceName(),
        ], false);

        $this->finishAuthentication($result);

        return redirect('/onboarding')->with('message', 'Account created. Check your email when you can to verify it.');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $result = $this->api->post('auth/login', [
            ...$validated,
            'device_name' => $this->deviceName(),
        ], false);

        $this->finishAuthentication($result);

        return $this->accountRedirect();
    }

    public function forgotPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $result = $this->api->post('auth/forgot-password', $validated, false);
        $this->ensureSuccessful($result, 'email');

        return back()->with('message', $result->message ?? 'If that account exists, a reset link has been sent.');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $result = $this->api->post('auth/reset-password', [
            ...$validated,
            'password_confirmation' => $request->string('password_confirmation')->toString(),
        ], false);
        $this->ensureSuccessful($result, 'email');

        return redirect()->route('account.login')->with('message', 'Password reset. Sign in again.');
    }

    public function verificationStatus(): JsonResponse
    {
        $result = $this->api->get('account');
        $this->ensureSuccessful($result);
        $account = $result->data['data'] ?? null;

        if (is_array($account)) {
            $this->credentials->updateAccount($account);

            if (($account['email_verified'] ?? false) === true) {
                $this->sync->queueExistingRecords();
            }
        }

        return response()->json(['verified' => ($account['email_verified'] ?? false) === true]);
    }

    public function resendVerification(): RedirectResponse
    {
        $result = $this->api->post('auth/email/resend');
        $this->ensureSuccessful($result);

        return back()->with('message', $result->message ?? 'A new verification link has been sent.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
        ]);
        $result = $this->api->patch('account', $validated);
        $this->ensureSuccessful($result);
        $account = $result->data['data'] ?? null;

        if (is_array($account)) {
            $this->credentials->updateAccount($account);
        }

        $message = ($account['email_verified'] ?? true) === false
            ? 'Account updated. Check your new email address when you can to verify it.'
            : 'Account updated.';

        return back()->with('message', $message);
    }

    public function logout(LocalAccountData $localData): RedirectResponse
    {
        if ($this->credentials->token() !== null) {
            $this->api->post('auth/logout');
        }

        $this->credentials->clear();
        $localData->wipe();

        return redirect()->route('account.login')->with('message', 'Signed out and removed local health data.');
    }

    public function destroy(Request $request, LocalAccountData $localData): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $result = $this->api->delete('account', $validated);
        $this->ensureSuccessful($result, 'password');
        $this->credentials->clear();
        $localData->wipe();

        return redirect('/onboarding')->with('message', 'Your Buff account was deleted.');
    }

    private function finishAuthentication(BuffApiResult $result): void
    {
        $this->ensureSuccessful($result, 'email');
        $token = $result->data['token'] ?? null;
        $account = $result->data['user'] ?? null;
        $accountId = is_array($account) ? ($account['id'] ?? null) : null;

        if (! is_string($token) || $token === '' || ! is_array($account) || ! is_int($accountId) || $accountId < 1) {
            throw ValidationException::withMessages(['email' => 'Buff returned an invalid sign-in response.']);
        }

        $account['id'] = (string) $accountId;
        $existingState = SyncState::query()->first();

        if ($existingState?->account_id !== null && $existingState->account_id !== $account['id']) {
            $this->credentials->store($token, $account);
            $this->api->post('auth/logout');
            $this->credentials->clear();

            throw ValidationException::withMessages([
                'email' => 'This device contains offline data for another account. Sign in to that account and log out before switching.',
            ]);
        }

        $this->credentials->store($token, $account);
        SyncState::current($account['id']);

        $this->sync->queueExistingRecords();
    }

    private function ensureSuccessful(BuffApiResult $result, string $fallbackField = 'account'): void
    {
        if ($result->successful()) {
            return;
        }

        $message = match ($result->status) {
            BuffApiStatus::ConnectionFailed => 'Buff could not reach the account service. Check your connection.',
            BuffApiStatus::RateLimited => 'Too many attempts. Try again shortly.',
            BuffApiStatus::Unauthenticated => 'Your session expired. Sign in again.',
            default => $result->message ?? 'Buff could not complete that request.',
        };

        throw ValidationException::withMessages($result->errors ?: [$fallbackField => [$message]]);
    }

    private function accountRedirect(): RedirectResponse
    {
        return redirect('/');
    }

    /** @param array<string, mixed> $props */
    private function page(string $screen, array $props = []): Response
    {
        return Inertia::render('Account', ['screen' => $screen, ...$props]);
    }

    private function deviceName(): string
    {
        return 'Buff mobile';
    }
}
