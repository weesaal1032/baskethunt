<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['poll_calls', 'download', 'transcribe', 'backfill']);
            $table->json('payload');
            $table->enum('status', ['queued', 'running', 'failed', 'done'])->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('run_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
