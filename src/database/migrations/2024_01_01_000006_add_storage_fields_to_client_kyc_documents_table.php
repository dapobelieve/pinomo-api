<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_kyc_documents', function (Blueprint $table) {
            $table->foreignId('storage_config_id')->nullable()->constrained('kyc_storage_configs');
            $table->string('storage_disk')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('error_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_kyc_documents', function (Blueprint $table) {
            $table->dropForeign(['storage_config_id']);
            $table->dropColumn([
                'storage_config_id',
                'storage_disk',
                'uploaded_at',
                'error_message'
            ]);
        });
    }
};