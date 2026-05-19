<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('barcode')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('image_url')->nullable();
            $table->string('serving_label')->nullable();
            $table->decimal('serving_quantity', 8, 2)->nullable();
            $table->string('serving_unit', 2)->nullable();
            $table->string('package_label')->nullable();
            $table->decimal('package_quantity', 8, 2)->nullable();
            $table->string('package_unit', 2)->nullable();
            $table->string('nutrition_unit', 2)->default('g');
            $table->decimal('calories_per_100', 8, 2);
            $table->decimal('protein_per_100', 8, 2);
            $table->decimal('carbs_per_100', 8, 2);
            $table->decimal('fat_per_100', 8, 2);
            $table->json('raw_payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_products');
    }
};
