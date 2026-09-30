<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broker_queues', function (Blueprint $table) {
            $table->id();
            $table->string('purpose')->unique();
            $table->string('queue_name');
            $table->string('exchange')->nullable();
            $table->string('routing_key')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_queues');
    }
};
