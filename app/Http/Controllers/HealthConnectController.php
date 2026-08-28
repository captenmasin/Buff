<?php

namespace App\Http\Controllers;

use App\Services\HealthConnectBridge;
use Illuminate\Http\JsonResponse;

class HealthConnectController extends Controller
{
    public function status(HealthConnectBridge $bridge): JsonResponse
    {
        return response()->json($this->statusPayload($bridge));
    }

    public function connect(HealthConnectBridge $bridge): JsonResponse
    {
        $native = $bridge->call('HealthConnect.RequestPermissions');

        return response()->json([
            ...$this->statusPayload($bridge),
            'native' => $native,
        ]);
    }

    public function sync(HealthConnectBridge $bridge): JsonResponse
    {
        $native = $bridge->call('HealthConnect.SyncNow');

        return response()->json([
            ...$this->statusPayload($bridge),
            'native' => $native,
        ]);
    }

    public function destroy(HealthConnectBridge $bridge): JsonResponse
    {
        $native = $bridge->call('HealthConnect.Disconnect');

        return response()->json([
            ...$this->statusPayload($bridge),
            'native' => $native,
        ]);
    }

    public static function sharedStatus(): array
    {
        return app(HealthConnectBridge::class)->sharedStatus();
    }

    private function statusPayload(HealthConnectBridge $bridge): array
    {
        $native = $bridge->call('HealthConnect.Status');

        return [
            ...$bridge->sharedStatus(),
            ...$native,
        ];
    }
}
