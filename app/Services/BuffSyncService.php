<?php

namespace App\Services;

use App\BuffApiStatus;
use App\Models\AppPreference;
use App\Models\PendingMealAnalysisConfirmation;
use App\Models\SyncedModel;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use App\Observers\SyncableObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

class BuffSyncService
{
    public function __construct(
        private readonly BuffApiClient $api,
        private readonly BuffCredentialStore $credentials,
        private readonly MealReminderBridge $mealReminders,
        private readonly BodyMetricPhotoUploader $bodyMetricPhotos,
    ) {}

    public function resume(): BuffApiResult
    {
        if ($this->credentials->token() === null) {
            return new BuffApiResult(BuffApiStatus::Unauthenticated, message: 'Sign in to sync Buff.');
        }

        if ($this->credentials->rotationIsDue()) {
            $this->credentials->markRotationAttempted();
            $rotation = $this->api->post('auth/rotate');

            if ($rotation->successful() && is_string($rotation->data['token'] ?? null)) {
                $this->credentials->replaceToken($rotation->data['token']);
                $account = $rotation->data['user'] ?? null;

                if (is_array($account)) {
                    $this->credentials->updateAccount($account);
                }
            } elseif ($rotation->status === BuffApiStatus::Unauthenticated) {
                $this->credentials->clearToken();

                return $rotation;
            }
        }

        return $this->sync();
    }

    public function sync(): BuffApiResult
    {
        if ($this->credentials->token() === null) {
            return new BuffApiResult(BuffApiStatus::Unauthenticated, message: 'Sign in to sync Buff.');
        }

        try {
            $result = Cache::lock('buff-sync', 120)->get(fn (): BuffApiResult => $this->run());
        } catch (Throwable $exception) {
            report($exception);
            SyncState::query()->first()?->update(['last_error' => 'Sync could not be applied locally.']);

            return new BuffApiResult(BuffApiStatus::Failed, message: 'Sync could not be applied locally.');
        }

        return $result instanceof BuffApiResult
            ? $result
            : BuffApiResult::success(['busy' => true]);
    }

    public function queueExistingRecords(): void
    {
        $observer = app(SyncableObserver::class);

        foreach (array_keys(config('buff.sync_models')) as $modelClass) {
            $modelClass::query()->each(fn (SyncedModel $model) => $observer->saved($model));
        }
    }

    private function run(): BuffApiResult
    {
        $accountId = $this->credentials->account()['id'] ?? null;
        $state = SyncState::current(is_string($accountId) ? $accountId : null);
        $deviceId = $state->device_id;
        $pullOnly = false;

        do {
            $state->update(['last_attempted_at' => Date::now(), 'last_error' => null]);
            $outbox = $pullOnly
                ? collect()
                : SyncOutbox::query()->oldest('id')->limit(500)->get();
            $response = $this->api->post('sync', [
                'device_id' => $deviceId,
                'cursor' => $state->cursor,
                'changes' => $outbox->map(fn (SyncOutbox $entry): array => [
                    'type' => $entry->record_type,
                    'id' => $entry->record_id,
                    'updated_at' => $this->timestamp($entry->client_updated_at),
                    'deleted' => $entry->is_deleted,
                    'data' => $entry->is_deleted ? null : $entry->payload,
                ])->values()->all(),
            ]);

            if (! $response->successful()) {
                if ($response->status === BuffApiStatus::Unauthenticated) {
                    $this->credentials->clearToken();
                }

                $state->update(['last_error' => $response->message ?? $response->code ?? $response->status->name]);

                return $response;
            }

            if (! $this->validSyncResponse($response->data)) {
                $result = new BuffApiResult(BuffApiStatus::Failed, message: 'Buff returned an invalid sync response.');
                $state->update(['last_error' => $result->message]);

                return $result;
            }

            $applied = $this->applyResponse($state, $deviceId, $outbox, $response->data);

            if (! $applied) {
                return new BuffApiResult(BuffApiStatus::Unauthenticated, message: 'Sync stopped because the local account changed.');
            }

            $state->refresh();
            $pullOnly = (bool) ($response->data['has_more'] ?? false);
        } while ($pullOnly);

        $this->retryPendingConfirmations();
        $this->bodyMetricPhotos->flushPending();

        return BuffApiResult::success([
            'cursor' => $state->cursor,
            'last_succeeded_at' => $state->last_succeeded_at?->toISOString(),
        ]);
    }

    /**
     * @param  Collection<int, SyncOutbox>  $sent
     * @param  array<string, mixed>  $data
     */
    private function applyResponse(SyncState $state, string $deviceId, Collection $sent, array $data): bool
    {
        $sentByRecord = $sent->keyBy(fn (SyncOutbox $entry): string => $entry->record_type.':'.$entry->record_id);
        $remindersChanged = false;

        $applied = Model::withoutEvents(function () use ($state, $deviceId, $data, $sentByRecord, &$remindersChanged): bool {
            return DB::transaction(function () use ($state, $deviceId, $data, $sentByRecord, &$remindersChanged): bool {
                $currentState = SyncState::query()
                    ->whereKey($state->getKey())
                    ->where('device_id', $deviceId)
                    ->lockForUpdate()
                    ->first();

                if ($currentState === null) {
                    return false;
                }

                foreach ($data['acknowledged'] as $acknowledgement) {
                    if (! is_array($acknowledgement)) {
                        continue;
                    }

                    $key = ($acknowledgement['type'] ?? '').':'.($acknowledgement['id'] ?? '');
                    $snapshot = $sentByRecord->get($key);
                    $accepted = ($acknowledgement['accepted'] ?? null) === true;
                    $serverRecord = $acknowledgement['server_record'] ?? null;

                    if (! $accepted && (! is_array($serverRecord) || ! $this->validRemoteChange($serverRecord))) {
                        continue;
                    }

                    if (! $snapshot instanceof SyncOutbox || ! $this->deleteMatchingOutbox($snapshot)) {
                        continue;
                    }

                    if (! $accepted) {
                        $remindersChanged = $this->applyRemote($serverRecord) || $remindersChanged;
                    }
                }

                foreach ($data['changes'] as $change) {
                    if (! is_array($change) || ! $this->validRemoteChange($change) || ! $this->remoteWinsPendingChange($change, $deviceId)) {
                        continue;
                    }

                    $remindersChanged = $this->applyRemote($change) || $remindersChanged;
                }

                $currentState->update([
                    'cursor' => (int) $data['cursor'],
                    'last_succeeded_at' => Date::now(),
                    'last_error' => null,
                ]);

                return true;
            });
        });

        if ($applied && $remindersChanged) {
            $preferences = AppPreference::query()->find(AppPreference::ID) ?? new AppPreference;
            $this->mealReminders->sync($preferences->mealReminders());
        }

        return $applied;
    }

    private function deleteMatchingOutbox(SyncOutbox $snapshot): bool
    {
        $current = SyncOutbox::query()
            ->where('record_type', $snapshot->record_type)
            ->where('record_id', $snapshot->record_id)
            ->first();

        if ($current === null || $this->timestamp($current->client_updated_at) !== $this->timestamp($snapshot->client_updated_at)) {
            return false;
        }

        $current->delete();

        return true;
    }

    /** @param array<string, mixed> $data */
    private function validSyncResponse(array $data): bool
    {
        if (! is_int($data['cursor'] ?? null)
            || ! is_bool($data['has_more'] ?? null)
            || ! is_array($data['acknowledged'] ?? null)
            || ! is_array($data['changes'] ?? null)) {
            return false;
        }

        foreach ($data['acknowledged'] as $acknowledgement) {
            if (! is_array($acknowledgement)
                || ! is_string($acknowledgement['type'] ?? null)
                || ! is_string($acknowledgement['id'] ?? null)
                || ! is_bool($acknowledgement['accepted'] ?? null)
                || ($acknowledgement['accepted'] === false
                    && (! is_array($acknowledgement['server_record'] ?? null)
                        || ! $this->validRemoteChange($acknowledgement['server_record'])))
            ) {
                return false;
            }
        }

        foreach ($data['changes'] as $change) {
            if (! is_array($change) || ! $this->validRemoteChange($change)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $change */
    private function validRemoteChange(array $change): bool
    {
        if (! is_string($change['type'] ?? null)
            || $this->modelFor($change['type']) === null
            || ! is_string($change['id'] ?? null)
            || ! is_string($change['updated_at'] ?? null)
            || ! is_bool($change['deleted'] ?? null)) {
            return false;
        }

        return $change['deleted'] || is_array($change['data'] ?? null);
    }

    /** @param array<string, mixed> $change */
    private function remoteWinsPendingChange(array $change, string $deviceId): bool
    {
        $type = $change['type'] ?? null;
        $id = $change['id'] ?? null;
        $updatedAt = $change['updated_at'] ?? null;

        if (! is_string($type) || ! is_string($id) || ! is_string($updatedAt)) {
            return false;
        }

        $pending = SyncOutbox::query()
            ->where('record_type', $type)
            ->where('record_id', $id)
            ->first();

        if ($pending === null) {
            return true;
        }

        $pendingAt = $pending->client_updated_at;
        $remoteAt = Date::parse($updatedAt);

        if ($pendingAt->greaterThan($remoteAt)) {
            return false;
        }

        if ($pendingAt->equalTo($remoteAt) && strcmp($deviceId, (string) ($change['source_device_id'] ?? '')) > 0) {
            return false;
        }

        $pending->delete();

        return true;
    }

    /** @param array<string, mixed> $change */
    private function applyRemote(array $change): bool
    {
        $modelClass = $this->modelFor((string) ($change['type'] ?? ''));
        $id = $change['id'] ?? null;

        if ($modelClass === null || ! is_string($id)) {
            return false;
        }

        $model = $modelClass::query()->find($id);

        if (($change['deleted'] ?? false) === true) {
            $model?->delete();

            return $modelClass === AppPreference::class;
        }

        if (! is_array($change['data'] ?? null) || ! is_string($change['updated_at'] ?? null)) {
            return false;
        }

        $model ??= new $modelClass;
        $model->setAttribute($model->getKeyName(), $id);
        $model->forceFill($change['data']);
        $model->timestamps = false;
        $updatedAt = Date::parse($change['updated_at'])->utc();
        $model->setAttribute($model->getUpdatedAtColumn(), $updatedAt);

        if (! $model->exists) {
            $model->setAttribute($model->getCreatedAtColumn(), $updatedAt);
        }

        $model->save();
        $model->timestamps = true;

        return $modelClass === AppPreference::class;
    }

    /** @return class-string<SyncedModel>|null */
    private function modelFor(string $type): ?string
    {
        foreach (config('buff.sync_models') as $modelClass => $definition) {
            if ($definition['type'] === $type) {
                return $modelClass;
            }
        }

        return null;
    }

    private function retryPendingConfirmations(): void
    {
        PendingMealAnalysisConfirmation::query()
            ->where('created_at', '<', Date::now()->subDay())
            ->delete();

        PendingMealAnalysisConfirmation::query()->oldest()->each(function (PendingMealAnalysisConfirmation $pending): void {
            if (SyncState::query()->doesntExist() || SyncOutbox::query()
                ->where('record_type', 'meal_entries')
                ->where('record_id', $pending->meal_record_id)
                ->exists()) {
                return;
            }

            $result = $this->api->post("meal-analyses/{$pending->analysis_id}/confirm", [
                'meal_record_id' => $pending->meal_record_id,
            ]);

            if ($result->successful()) {
                $pending->delete();
            } else {
                $pending->update(['last_error' => $result->message ?? $result->code ?? $result->status->name]);
            }
        });
    }

    private function timestamp(CarbonInterface $date): string
    {
        return $date->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
