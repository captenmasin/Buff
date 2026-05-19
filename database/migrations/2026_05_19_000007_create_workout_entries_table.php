<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->string('title');
            $table->unsignedInteger('calories_burned');
            $table->dateTime('logged_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_entries');
    }
};
