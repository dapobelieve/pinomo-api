<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('account_number')->unique();
            $table->uuid('client_id');
            $table->uuid('product_id');
            $table->string('account_name');
            $table->string('currency', 3);
            $table->decimal('available_balance', 19, 4)->default(0);
            $table->decimal('actual_balance', 19, 4)->default(0);
            $table->decimal('locked_amount', 19, 4)->default(0);
            $table->enum('status', ['pending_activation', 'active', 'dormant', 'suspended', 'closed'])->default('pending_activation');
            $table->string('closure_reason')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('dormant_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreign('closed_by_user_id')->references('id')->on('users');
        });


    }

    public function down()
    {

        Schema::dropIfExists('accounts');
    }
};