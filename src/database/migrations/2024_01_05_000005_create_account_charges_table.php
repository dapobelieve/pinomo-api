<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignUuid('charge_id')->constrained('charges')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->json('charge_config')->nullable(); // For any account-specific charge configurations
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->uuid('created_by_user_id');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Ensure unique charge-account combinations within an effective period
            $table->unique(['account_id', 'charge_id', 'effective_from', 'effective_until'], 'unique_account_charge_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_charges');
    }
};