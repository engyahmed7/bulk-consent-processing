<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_job_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_job_id')->constrained('bulk_jobs')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('status')->default('pending');
            $table->unsignedInteger('row_from');
            $table->unsignedInteger('row_to');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['bulk_job_id', 'chunk_index']);
            $table->index(['bulk_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_job_chunks');
    }
};
