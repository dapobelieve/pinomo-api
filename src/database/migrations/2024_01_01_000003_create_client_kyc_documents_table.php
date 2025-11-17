<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('client_kyc_documents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('client_id', 36);
            $table->string('document_type');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->string('file_path');
            $table->enum('status', ['uploaded', 'pending_review', 'approved', 'rejected']);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('review_date')->nullable();
            $table->timestamps();
            
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('uploaded_by_user_id')->references('id')->on('users');
            $table->foreign('reviewed_by_user_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('client_kyc_documents');
    }
};