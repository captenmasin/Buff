<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOutbox extends Model
{
    protected $fillable = [
        'record_type',
        'record_id',
        'payload',
        'client_updated_at',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'client_updated_at' => 'datetime',
            'is_deleted' => 'boolean',
        ];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }
}
