<?php

namespace App\Services;

use App\Models\HealthConnectSyncState;
use Closure;
use Native\Mobile\Facades\System;
use Throwable;

class HealthConnectBridge
{
    public function __construct(
        private ?Closure $nativeCaller = null,
        private ?Closure $androidDetector = null,
    ) {}

    public function sharedStatus(): array
    {
        $state = HealthConnectSyncState::query()->find(HealthConnectSyncState::SOURCE_TYPE);
        $isAndroid = $this->isAndroid();

        return [
            'is_android' => $isAndroid,
            'supported' => $isAndroid,
            'available' => null,
            'has_permissions' => null,
            'foreground_granted' => null,
            'background_granted' => null,
            'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
            'last_successful_sync_at' => $state?->last_successful_sync_at?->toIso8601String(),
            'last_status' => $state?->last_status,
            'last_error' => $state?->last_error,
            'synced_records' => $state?->synced_records ?? 0,
            'deleted_records' => $state?->deleted_records ?? 0,
        ];
    }

    public function call(string $method, array $params = []): array
    {
        if (! $this->isAndroid()) {
            return [
                'is_android' => false,
                'supported' => false,
                'available' => false,
                'status' => 'unsupported',
                'message' => 'Health Connect is only available in the Android app.',
            ];
        }

        try {
            $payload = json_encode($params, JSON_THROW_ON_ERROR);
            $result = $this->nativeCaller
                ? ($this->nativeCaller)($method, $payload)
                : nativephp_call($method, $payload);
        } catch (Throwable) {
            return $this->errorPayload('Health Connect bridge call failed.');
        }

        if (! $result) {
            return [
                'supported' => true,
                'available' => false,
                'status' => 'unavailable',
                'message' => 'Health Connect bridge is not registered.',
            ];
        }

        $decoded = json_decode($result, true);

        if (! is_array($decoded)) {
            return $this->errorPayload('Health Connect returned an invalid response.');
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }

        if (isset($decoded['status']) && ! is_string($decoded['status'])) {
            return $this->errorPayload('Health Connect returned a malformed status.');
        }

        return [
            'is_android' => true,
            'supported' => true,
            ...$decoded,
        ];
    }

    private function isAndroid(): bool
    {
        if ($this->androidDetector) {
            return (bool) ($this->androidDetector)();
        }

        if ($this->nativeCaller) {
            return true;
        }

        if (! function_exists('nativephp_call')) {
            return false;
        }

        if (request()->server('NATIVEPHP_PLATFORM') === 'android' || getenv('NATIVEPHP_PLATFORM') === 'android') {
            return true;
        }

        if (function_exists('nativephp_can') && nativephp_can('HealthConnect.Status')) {
            return true;
        }

        try {
            return System::isAndroid();
        } catch (Throwable) {
            return false;
        }
    }

    private function errorPayload(string $message): array
    {
        return [
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => $message,
        ];
    }
}
