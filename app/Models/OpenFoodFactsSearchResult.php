<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OpenFoodFactsSearchResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'query_hash',
        'query',
        'limit',
        'food_product_ids',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'food_product_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
