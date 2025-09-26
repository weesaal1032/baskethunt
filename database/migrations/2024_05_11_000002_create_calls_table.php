<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnUpdate();
            $table->string('provider_call_id')->unique();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('from_number');
            $table->string('to_number');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->string('disposition')->nullable();
            $table->string('queue')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('started_at');
            $table->index('agent_id');
            $table->index('from_number');
            $table->index('to_number');
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
