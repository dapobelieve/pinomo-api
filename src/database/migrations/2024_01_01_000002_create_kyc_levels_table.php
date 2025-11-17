<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kyc_levels', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('level_name');
            $table->text('description')->nullable();
            $table->json('requirements');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('kyc_levels');
    }
};