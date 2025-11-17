<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transaction_aggregates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->decimal('aggregated_daily_amount', 19, 4)->default(0);
            $table->date('date');
            $table->timestamps();
            
            // Unique constraint to ensure one record per account per day
            $table->unique(['account_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('transaction_aggregates');
    }
};