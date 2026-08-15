<?php

namespace App\Models;

class DailyLog extends SyncedModel
{
    protected $fillable = [
        'date',
        'burned_calories',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'burned_calories' => 'integer',
        ];
    }
}
