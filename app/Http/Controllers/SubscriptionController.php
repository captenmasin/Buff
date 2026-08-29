<?php

namespace App\Http\Controllers;

use App\Services\BuffApiClient;
use App\Services\BuffCredentialStore;
use Illuminate\Http\JsonResponse;

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
}
