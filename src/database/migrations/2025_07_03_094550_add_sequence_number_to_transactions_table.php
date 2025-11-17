<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->bigInteger('sequence_number')->unsigned()->nullable()->after('id');
            $table->index('sequence_number');
        });

        $transactions = DB::table('transactions')->orderBy('id')->get();
        $sequenceNumber = 1;

        foreach ($transactions as $transaction) {
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['sequence_number' => $sequenceNumber]);
            $sequenceNumber++;
        }

        DB::statement('ALTER TABLE transactions MODIFY sequence_number BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        if ($sequenceNumber > 1) {
            DB::statement("ALTER TABLE transactions AUTO_INCREMENT = $sequenceNumber");
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('sequence_number');
        });
    }
};