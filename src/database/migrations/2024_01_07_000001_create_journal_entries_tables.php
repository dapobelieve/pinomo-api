<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number')->unique();  // Auto-generated sequential number
            $table->date('entry_date');
            $table->string('reference_type')->nullable();  // e.g., deposit, withdrawal, transfer
            $table->string('reference_id')->nullable();    // ID of the related transaction
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');
            $table->uuid('created_by_user_id');
            $table->uuid('posted_by_user_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreign('posted_by_user_id')->references('id')->on('users');
        });

        Schema::create('journal_entry_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->decimal('debit_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')
                  ->references('id')
                  ->on('journal_entries')
                  ->onDelete('cascade');
                  
            $table->foreignId('gl_account_id')
                  ->references('id')
                  ->on('gl_accounts');
        });
    }

    public function down()
    {
        Schema::dropIfExists('journal_entry_items');
        Schema::dropIfExists('journal_entries');
    }
};