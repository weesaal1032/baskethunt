<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->string('remote_url');
            $table->string('local_path')->nullable();
            $table->enum('storage_backend', ['local', 's3'])->default('local');
            $table->string('format', 32)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->json('waveform_json')->nullable();
            $table->enum('status', ['pending', 'downloading', 'ready', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
