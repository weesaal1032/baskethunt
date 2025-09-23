<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('calls')->cascadeOnDelete();
            $table->foreignId('scored_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->json('rubric');
            $table->unsignedTinyInteger('total_score');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['call_id', 'scored_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_scores');
    }
};
