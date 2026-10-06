<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_job_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_job_id')->constrained('bulk_jobs')->cascadeOnDelete();
            $table->foreignId('chunk_id')->nullable()->constrained('bulk_job_chunks')->nullOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('user_id');
            $table->string('phone_number');
            $table->string('status')->default('pending');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['bulk_job_id', 'row_number']);
            $table->index(['chunk_id', 'status']);
            $table->index(['bulk_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_job_rows');
    }
};
