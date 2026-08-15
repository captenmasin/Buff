<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_goals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('starts_on')->unique();
            $table->unsignedInteger('calories');
            $table->decimal('protein_g', 8, 2);
            $table->decimal('carbs_g', 8, 2);
            $table->decimal('fat_g', 8, 2);
            $table->unsignedInteger('macro_calories');
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_goals');
    }
};
