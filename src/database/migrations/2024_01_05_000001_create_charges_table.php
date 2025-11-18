<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('charge_type', ['flat', 'percentage', 'tiered']);
            $table->enum('txn_type', ['account_opening', 'deposit', 'vat', 'loan_interest', 'loan_disbursement', 'transfer']);
            $table->decimal('amount', 19, 4)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('currency', 3); // ISO 4217 code
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('gl_income_account_id')->constrained('gl_accounts');
            $table->timestamps();

            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};