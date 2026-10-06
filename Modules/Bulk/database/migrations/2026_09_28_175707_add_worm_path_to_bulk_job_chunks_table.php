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
        Schema::table('bulk_job_chunks', function (Blueprint $table) {
            $table->string('worm_path')->nullable()->after('row_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_job_chunks', function (Blueprint $table) {
            $table->dropColumn('worm_path');
        });
    }
};
