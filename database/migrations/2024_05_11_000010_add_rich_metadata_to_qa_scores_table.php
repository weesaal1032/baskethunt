<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa_scores', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('scored_by');
            $table->string('status', 24)->default('draft')->after('version');
            $table->unsignedSmallInteger('possible_score')->default(100)->after('total_score');
            $table->boolean('passed')->default(false)->after('possible_score');
            $table->json('responses')->nullable()->after('rubric');
            $table->json('score_breakdown')->nullable()->after('responses');
            $table->json('tags')->nullable()->after('comments');
            $table->unsignedInteger('rubric_version')->default(1)->after('status');
            $table->timestamp('submitted_at')->nullable()->after('updated_at');

            $table->index(['status']);
            $table->index(['passed']);
            $table->index(['rubric_version']);
            $table->index(['submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('qa_scores', function (Blueprint $table): void {
            $table->dropColumn([
                'version',
                'status',
                'passed',
                'possible_score',
                'responses',
                'score_breakdown',
                'tags',
                'rubric_version',
                'submitted_at',
            ]);
        });
    }
};
