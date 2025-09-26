<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained()->cascadeOnDelete();
            $table->enum('engine', ['whisper_api', 'whisper_local']);
            $table->string('language', 32)->default('en');
            $table->longText('text')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('segments')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['engine', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
