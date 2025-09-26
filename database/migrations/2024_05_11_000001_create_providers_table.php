<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->string('auth_type', 32)->default('api_key');
            $table->string('api_key')->nullable();
            $table->json('rate_limit_json')->nullable();
            $table->timestamps();

            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
