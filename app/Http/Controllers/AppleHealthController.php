<?php

namespace App\Http\Controllers;

use App\Services\AppleHealthBridge;
use Illuminate\Http\JsonResponse;

class AppleHealthController extends Controller
{
    public function status(AppleHealthBridge $bridge): JsonResponse
    {
        return response()->json($this->statusPayload($bridge));
    }

    public function connect(AppleHealthBridge $bridge): JsonResponse
    {
        $native = $bridge->call('AppleHealth.RequestPermissions');

        return response()->json([
            ...$this->statusPayload($bridge),
            'native' => $native,
        ]);
    }

    public function sync(AppleHealthBridge $bridge): JsonResponse
    {
        $native = $bridge->call('AppleHealth.SyncNow');

        return response()->json([
            ...$this->statusPayload($bridge),
            'native' => $native,
        ]);
    }

    public static function sharedStatus(): array
    {
        return app(AppleHealthBridge::class)->sharedStatus();
    }

    private function statusPayload(AppleHealthBridge $bridge): array
    {
        $native = $bridge->call('AppleHealth.Status');

        return [
            ...$bridge->sharedStatus(),
            ...$native,
        ];
    }
}
