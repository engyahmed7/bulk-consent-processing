<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_group');
            $table->string('setting_key');
            $table->text('setting_value');
            $table->timestamps();

            $table->unique(['setting_group', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
