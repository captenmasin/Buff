<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HealthConnectIgnoredWorkout extends Model
{
    use HasUuids;

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
