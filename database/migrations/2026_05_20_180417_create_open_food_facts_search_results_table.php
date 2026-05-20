<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_food_facts_search_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('query_hash')->unique();
            $table->string('query');
            $table->unsignedSmallInteger('limit');
            $table->json('food_product_ids');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_food_facts_search_results');
    }
};
