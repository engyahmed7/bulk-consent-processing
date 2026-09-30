<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bulk_job_rows', function (Blueprint $table): void {
            $table->json('additional_data')->nullable();
        });

        Schema::table('bulk_job_chunks', function (Blueprint $table): void {
            $table->timestamp('processing_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_job_chunks', function (Blueprint $table): void {
            $table->dropColumn('processing_started_at');
        });

        Schema::table('bulk_job_rows', function (Blueprint $table): void {
            $table->dropColumn('additional_data');
        });
    }
};
