<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex', 16)->nullable();
            $table->string('activity_level', 32)->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_profiles');
    }
};
