<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_storage_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('driver');
            $table->json('settings');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_storage_configs');
    }
};