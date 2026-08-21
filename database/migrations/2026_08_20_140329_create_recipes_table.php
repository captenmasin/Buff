<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('servings', 8, 2)->default(1);
            $table->json('items');
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
