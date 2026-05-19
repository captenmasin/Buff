<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkoutEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'date',
        'title',
        'calories_burned',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'calories_burned' => 'integer',
            'logged_at' => 'datetime',
        ];
    }
}
