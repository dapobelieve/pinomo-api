<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('client_kyc_status_history', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('client_id', 36);
            $table->enum('old_status', ['pending', 'verified', 'rejected']);
            $table->enum('new_status', ['pending', 'verified', 'rejected']);
            $table->char('action_by_user_id', 36);
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('action_by_user_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('client_kyc_status_history');
    }
};