<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_meal_analysis_confirmations', function (Blueprint $table): void {
            $table->uuid('analysis_id')->primary();
            $table->uuid('meal_record_id');
            $table->text('last_error')->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_meal_analysis_confirmations');
    }
};
