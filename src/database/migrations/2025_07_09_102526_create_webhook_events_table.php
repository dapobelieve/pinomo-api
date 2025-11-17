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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('webhook_id')->unique();
            $table->string('url');
            $table->json('payload');
            $table->enum('status', ['pending', 'delivered', 'failed', 'permanently_failed'])->default('pending');
            $table->integer('attempt')->default(1);
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            $table->index(['webhook_id', 'status']);
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
