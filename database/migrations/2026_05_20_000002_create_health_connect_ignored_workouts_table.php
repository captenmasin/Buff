<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_connect_ignored_workouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_id');
            $table->dateTime('ignored_at');
            $table->timestamps(6);

            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_connect_ignored_workouts');
    }
};
