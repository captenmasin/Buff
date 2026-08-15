<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->string('meal_type', 16)->index();
            $table->string('source_type', 16)->index();
            $table->uuid('food_product_id')->nullable()->index();
            $table->string('name');
            $table->decimal('portion_quantity', 8, 2)->nullable();
            $table->string('portion_unit', 2)->nullable();
            $table->unsignedInteger('calories');
            $table->decimal('protein_g', 8, 2);
            $table->decimal('carbs_g', 8, 2);
            $table->decimal('fat_g', 8, 2);
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};
