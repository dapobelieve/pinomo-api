<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create charge_tiers table for tiered pricing
        Schema::create('charge_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('charge_id')->constrained('charges')->onDelete('cascade');
            $table->decimal('from_amount', 19, 4);
            $table->decimal('to_amount', 19, 4)->nullable();
            $table->decimal('fixed_amount', 19, 4)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->timestamps();
            
            // Ensure tiers don't overlap for the same charge
            $table->unique(['charge_id', 'from_amount', 'to_amount']);
        });

        // Create charge_products table for linking charges to products
        Schema::create('charge_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('charge_id')->constrained('charges')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('products')->onDelete('cascade');
            $table->boolean('is_mandatory')->default(false);
            $table->json('charge_triggers')->nullable(); // Events that trigger the charge
            $table->timestamps();
            
            // Ensure unique charge-product combinations
            $table->unique(['charge_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_products');
        Schema::dropIfExists('charge_tiers');
    }
};