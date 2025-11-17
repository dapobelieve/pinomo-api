<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product_name')->unique();
            $table->enum('product_type', ['deposit', 'loan', 'wallet', 'escrow']);
            $table->string('currency', 3); // ISO 4217 code
            
            // Amount constraints
            $table->decimal('minimum_amount', 19, 4)->nullable();
            $table->decimal('maximum_amount', 19, 4)->nullable();
            
            // Interest configuration
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->enum('interest_rate_type', ['fixed', 'variable'])->nullable();
            $table->enum('interest_calculation_frequency', [
                'daily',
                'monthly',
                'annually'
            ])->nullable();
            $table->enum('interest_posting_frequency', [
                'monthly',
                'quarterly',
                'annually'
            ])->nullable();
            
            // Loan-specific fields
            $table->enum('repayment_frequency', [
                'daily',
                'weekly',
                'monthly',
                'annually'
            ])->nullable();
            $table->enum('amortization_type', [
                'flat',
                'declining_balance'
            ])->nullable();
            $table->integer('grace_period_days')->nullable();
            $table->decimal('late_payment_penalty_rate', 8, 4)->nullable();
            
            // General fields
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create pivot table for product charges
        Schema::create('product_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignUuid('charge_id')->constrained('charges')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['product_id', 'charge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_charges');
        Schema::dropIfExists('products');
    }
};