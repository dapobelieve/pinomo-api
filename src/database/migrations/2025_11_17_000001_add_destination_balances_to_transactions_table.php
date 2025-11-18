<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('destination_ledger_balance_before', 19, 4)->nullable()->after('source_available_balance_before');
            $table->decimal('destination_locked_balance_before', 19, 4)->nullable()->after('destination_ledger_balance_before');
            $table->decimal('destination_available_balance_before', 19, 4)->nullable()->after('destination_locked_balance_before');

            $table->decimal('destination_ledger_balance_after', 19, 4)->nullable()->after('source_available_balance_after');
            $table->decimal('destination_locked_balance_after', 19, 4)->nullable()->after('destination_ledger_balance_after');
            $table->decimal('destination_available_balance_after', 19, 4)->nullable()->after('destination_locked_balance_after');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'destination_ledger_balance_before',
                'destination_locked_balance_before',
                'destination_available_balance_before',
                'destination_ledger_balance_after',
                'destination_locked_balance_after',
                'destination_available_balance_after',
            ]);
        });
    }
};
