<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

abstract class SyncedModel extends Model
{
    use HasUuids;

    public function save(array $options = []): bool
    {
        $this->setMicrosecondTimestamps();
        $connection = $this->getConnection();

        if ($connection->transactionLevel() > 0) {
            return parent::save($options);
        }

        return $connection->transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        $connection = $this->getConnection();

        if ($connection->transactionLevel() > 0) {
            return parent::delete();
        }

        return $connection->transaction(fn (): ?bool => parent::delete());
    }

    private function setMicrosecondTimestamps(): void
    {
        if (! $this->usesTimestamps()) {
            return;
        }

        $updatedAtColumn = $this->getUpdatedAtColumn();
        $timestamp = $this->freshTimestamp();
        $originalUpdatedAt = $updatedAtColumn !== null ? $this->getRawOriginal($updatedAtColumn) : null;

        if ($this->exists && is_string($originalUpdatedAt) && $originalUpdatedAt !== '') {
            $original = Date::parse($originalUpdatedAt);

            if (! $timestamp->greaterThan($original)) {
                $timestamp = $original->addMicrosecond();
            }
        }

        $timestampString = $timestamp->format('Y-m-d H:i:s.u');

        if ($updatedAtColumn !== null && ! $this->isDirty($updatedAtColumn)) {
            $this->attributes[$updatedAtColumn] = $timestampString;
        }

        $createdAtColumn = $this->getCreatedAtColumn();

        if (! $this->exists && $createdAtColumn !== null && ! $this->isDirty($createdAtColumn)) {
            $this->attributes[$createdAtColumn] = $timestampString;
        }
    }
}
