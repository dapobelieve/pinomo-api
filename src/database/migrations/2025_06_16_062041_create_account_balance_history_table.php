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
      Schema::create('account_balance_history', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('account_id');
        $table->decimal('available_balance', 19, 4);
        $table->decimal('actual_balance', 19, 4);
        $table->decimal('locked_amount', 19, 4);
        $table->uuid('journal_entry_id')->nullable();
        $table->timestamp('balance_date');
        $table->timestamps();

        $table->foreign('account_id')->references('id')->on('accounts');
        $table->foreign('journal_entry_id')->references('id')->on('journal_entries');

        // Index for efficient historical queries
        $table->index(['account_id', 'balance_date']);
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      Schema::dropIfExists('account_balance_history');
    }
};
