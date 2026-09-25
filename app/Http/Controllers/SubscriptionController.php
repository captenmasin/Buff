<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsEventService;
use App\Services\BuffApiClient;
use App\Services\BuffCredentialStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function store(BuffApiClient $api, BuffCredentialStore $credentials): JsonResponse
    {
        $result = $api->post('subscription/refresh');
        $account = $result->data['data'] ?? null;

        if ($result->successful() && is_array($account)) {
            $credentials->updateAccount($account);
        }

        return $this->buffApiResponse($result);
    }

    public function markPromptSeen(BuffApiClient $api): JsonResponse
    {
        return $this->buffApiResponse($api->post('subscription/prompt-seen'));
    }

    public function checkoutStarted(Request $request, AnalyticsEventService $analytics): JsonResponse
    {
        $validated = $request->validate(['kind' => ['required', Rule::in(['monthly', 'annual'])]]);
        $analytics->record('subscription_checkout_started_'.$validated['kind']);

        return response()->json(status: 204);
    }
}
