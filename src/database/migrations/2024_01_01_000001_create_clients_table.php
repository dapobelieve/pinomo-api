<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->char('id', 36)->primary(); // UUID for MySQL
            $table->uuid('external_id')->unique();
            $table->enum('client_type', ['individual', 'organization']);
            $table->char('organization_business_id', 36)->nullable()->unique();
            // Business-specific fields
            $table->string('business_name')->nullable();
            $table->string('business_registration_number')->nullable()->unique();
            $table->date('business_registration_date')->nullable();
            $table->string('business_type')->nullable(); // e.g., LLC, Corporation, Partnership
            $table->string('tax_identification_number')->nullable()->unique();
            $table->string('industry_sector')->nullable();
            // Representative contact person
            $table->string('representative_first_name')->nullable();
            $table->string('representative_last_name')->nullable();
            $table->string('representative_position')->nullable();
            // Existing individual fields
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('marital_status')->nullable();
            $table->char('nationality', 3)->nullable(); // ISO 3166-1 alpha-3
            $table->string('email')->unique()->nullable();
            $table->string('phone_number')->unique()->nullable();
            $table->json('address')->nullable();
            $table->enum('kyc_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('external_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};