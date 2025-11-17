<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();

            // Basic account information
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->enum('account_type', [
                'asset',
                'liability',
                'equity',
                'income',
                'expense'
            ]);
            $table->string('currency', 3);
            
            // Hierarchical structure
            $table->foreignId('parent_account_id')
                  ->nullable()
                  ->references('id')
                  ->on('gl_accounts')
                  ->onDelete('restrict');
            
            // Additional fields
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('current_balance', 19, 4)->default(0);
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('account_type');
            $table->index('currency');
            $table->index('parent_account_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gl_accounts');
    }
};