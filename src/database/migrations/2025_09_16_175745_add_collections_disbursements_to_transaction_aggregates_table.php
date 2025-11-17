<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_aggregates', function (Blueprint $table) {
            $table->decimal('collections_amount', 19, 4)->default(0)->after('aggregated_daily_amount');
            $table->decimal('disbursements_amount', 19, 4)->default(0)->after('collections_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_aggregates', function (Blueprint $table) {
            $table->dropColumn(['collections_amount', 'disbursements_amount']);
        });
    }
};
