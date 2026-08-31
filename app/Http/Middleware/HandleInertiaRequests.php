<?php

namespace App\Http\Middleware;

use App\Models\BodyProfile;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use App\Services\BuffCredentialStore;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $credentials = app(BuffCredentialStore::class);
        $syncState = SyncState::query()->first();
        $age = BodyProfile::query()->whereKey(BodyProfile::ID)->value('age');

        return [
            ...parent::share($request),
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'save_status' => fn () => $request->session()->get('save_status'),
            ],
            'buff' => [
                'account' => $credentials->account(),
                'ad_audience' => $age !== null && (int) $age >= 18 ? 'adult' : 'teen',
                'needs_sign_in' => $credentials->token() === null,
                'can_resume' => $credentials->refreshToken() !== null,
                'has_local_account' => $syncState !== null,
                'sync' => $syncState ? [
                    'last_succeeded_at' => $syncState->last_succeeded_at?->toISOString(),
                    'last_error' => $syncState->last_error,
                    'pending' => SyncOutbox::query()->count(),
                ] : null,
            ],
        ];
    }
}
