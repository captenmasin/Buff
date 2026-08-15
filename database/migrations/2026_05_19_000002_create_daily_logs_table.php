<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->unsignedInteger('burned_calories')->default(0);
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_logs');
    }
};
