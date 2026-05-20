<?php

namespace App\Http\Controllers;

use App\Models\HealthConnectSyncState;
use Illuminate\Http\JsonResponse;

class HealthConnectController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json($this->statusPayload());
    }

    public function connect(): JsonResponse
    {
        $native = $this->nativeCall('HealthConnect.RequestPermissions');

        return response()->json([
            ...$this->statusPayload(),
            'native' => $native,
        ]);
    }

    public function sync(): JsonResponse
    {
        $native = $this->nativeCall('HealthConnect.SyncNow');

        return response()->json([
            ...$this->statusPayload(),
            'native' => $native,
        ]);
    }

    public static function sharedStatus(): array
    {
        $state = HealthConnectSyncState::query()->find(HealthConnectSyncState::SOURCE_TYPE);

        return [
            'supported' => function_exists('nativephp_call'),
            'available' => null,
            'has_permissions' => null,
            'background_granted' => null,
            'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
            'last_successful_sync_at' => $state?->last_successful_sync_at?->toIso8601String(),
            'last_status' => $state?->last_status,
            'last_error' => $state?->last_error,
            'synced_records' => $state?->synced_records ?? 0,
            'deleted_records' => $state?->deleted_records ?? 0,
        ];
    }

    private function statusPayload(): array
    {
        $native = $this->nativeCall('HealthConnect.Status');

        return [
            ...self::sharedStatus(),
            ...$native,
        ];
    }

    private function nativeCall(string $method, array $params = []): array
    {
        if (! function_exists('nativephp_call')) {
            return [
                'supported' => false,
                'available' => false,
                'status' => 'unsupported',
                'message' => 'Health Connect is only available in the Android app.',
            ];
        }

        $result = nativephp_call($method, json_encode($params));

        if (! $result) {
            return [
                'supported' => true,
                'available' => false,
                'status' => 'unavailable',
                'message' => 'Health Connect bridge is not registered.',
            ];
        }

        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : [
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => 'Health Connect returned an invalid response.',
        ];
    }
}
