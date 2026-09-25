<?php

namespace App\Services;

use App\BuffApiStatus;
use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Models\HealthConnectIgnoredWorkout;
use App\Models\PendingBodyMetricPhotoUpload;
use App\Models\PendingMealAnalysisConfirmation;
use App\Models\SyncedModel;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use App\Observers\SyncableObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BuffSyncService
{
    public function __construct(
        private readonly BuffApiClient $api,
        private readonly BuffCredentialStore $credentials,
        private readonly MealReminderBridge $mealReminders,
        private readonly BodyMetricPhotoUploader $bodyMetricPhotos,
        private readonly AnalyticsEventService $analytics,
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
        $this->analytics->flush();
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
            Schema::disableForeignKeyConstraints();

            try {
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

                    foreach ($this->orderedRemoteChanges($data['changes']) as $change) {
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
            } finally {
                Schema::enableForeignKeyConstraints();
            }
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
            $localIds = $this->localIdsForNaturalKey($change);

            if ($localIds !== []) {
                $pending = SyncOutbox::query()
                    ->where('record_type', $type)
                    ->whereIn('record_id', $localIds)
                    ->latest('client_updated_at')
                    ->first();
            }
        }

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

        $fields = config('buff.sync_models')[$modelClass]['fields'] ?? null;

        if (! is_array($fields)) {
            return false;
        }

        if ($modelClass === DailyGoal::class && BodyProfile::query()->doesntExist()) {
            $legacyProfile = Arr::only($change['data'], ['height_cm', 'age', 'sex', 'activity_level']);

            if (array_filter($legacyProfile, fn (mixed $value): bool => $value !== null) !== []) {
                BodyProfile::current()->forceFill($legacyProfile)->save();
            }
        }

        $model = $this->localModelForRemote($modelClass, $id, $change['data'], $model);
        $model->setAttribute($model->getKeyName(), $id);
        $model->forceFill(Arr::only($change['data'], $fields));
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

    /**
     * @param  array<int, mixed>  $changes
     * @return Collection<int, mixed>
     */
    private function orderedRemoteChanges(array $changes): Collection
    {
        return collect($changes)->sortBy(fn (mixed $change): int => is_array($change)
            ? $this->applyOrder((string) ($change['type'] ?? ''))
            : 5)->values();
    }

    private function applyOrder(string $type): int
    {
        return match ($type) {
            'recipes' => 0,
            'meal_entries' => 10,
            default => 5,
        };
    }

    /**
     * @param  class-string<SyncedModel>  $modelClass
     * @param  array<string, mixed>  $data
     */
    private function localModelForRemote(string $modelClass, string $id, array $data, ?SyncedModel $model): SyncedModel
    {
        $occupant = $this->modelForNaturalKey($modelClass, $data);

        if ($occupant !== null && ($model === null || $occupant->isNot($model))) {
            if ($modelClass === BodyMetric::class) {
                PendingBodyMetricPhotoUpload::query()
                    ->whereIn('body_metric_id', array_values(array_filter([$model?->getKey(), $occupant->getKey()])))
                    ->update(['body_metric_id' => $id]);
            }

            if ($model !== null) {
                $model->delete();
            }

            return $occupant;
        }

        return $model ?? new $modelClass;
    }

    /**
     * @param  class-string<SyncedModel>  $modelClass
     * @param  array<string, mixed>  $data
     */
    private function modelForNaturalKey(string $modelClass, array $data): ?SyncedModel
    {
        if ($modelClass === BodyMetric::class && is_string($data['date'] ?? null)) {
            return BodyMetric::query()
                ->whereDate('date', $data['date'])
                ->latest('updated_at')
                ->first();
        }

        if ($modelClass === HealthConnectIgnoredWorkout::class
            && is_string($data['source_type'] ?? null)
            && is_string($data['external_id'] ?? null)) {
            return HealthConnectIgnoredWorkout::query()
                ->where('source_type', $data['source_type'])
                ->where('external_id', $data['external_id'])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $change
     * @return list<string>
     */
    private function localIdsForNaturalKey(array $change): array
    {
        $type = $change['type'] ?? null;
        $data = $change['data'] ?? null;

        if (! is_string($type) || ($change['deleted'] ?? false) === true || ! is_array($data)) {
            return [];
        }

        $modelClass = $this->modelFor($type);
        $occupant = $modelClass === null ? null : $this->modelForNaturalKey($modelClass, $data);

        return $occupant === null ? [] : [(string) $occupant->getKey()];
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
