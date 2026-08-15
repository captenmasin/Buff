<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingMealAnalysisConfirmation extends Model
{
    protected $primaryKey = 'analysis_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'analysis_id',
        'meal_record_id',
        'last_error',
    ];
}
