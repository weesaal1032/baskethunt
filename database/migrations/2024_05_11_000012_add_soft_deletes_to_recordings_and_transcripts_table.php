<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table): void {
            if (! Schema::hasColumn('recordings', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });

        Schema::table('transcripts', function (Blueprint $table): void {
            if (! Schema::hasColumn('transcripts', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table): void {
            if (Schema::hasColumn('recordings', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });

        Schema::table('transcripts', function (Blueprint $table): void {
            if (Schema::hasColumn('transcripts', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });
    }
};
