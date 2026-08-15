<?php

namespace App\Models;

class HealthConnectIgnoredWorkout extends SyncedModel
{
    protected $fillable = [
        'external_id',
        'ignored_at',
    ];

    protected function casts(): array
    {
        return [
            'ignored_at' => 'datetime',
        ];
    }
}
