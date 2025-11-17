<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'organization_business_ids')) {
                $table->json('organization_business_ids')->nullable();
            }

            if (!Schema::hasColumn('clients', 'owner_client_id')) {
                $table->char('owner_client_id', 36)->nullable()->after('client_type');
                $table->foreign('owner_client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->index('owner_client_id');
            }
        });

        $existingConstraints = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_NAME = 'clients' 
            AND CONSTRAINT_NAME = 'chk_no_self_reference'
        ");

        if (empty($existingConstraints)) {
            DB::statement("
                ALTER TABLE clients 
                ADD CONSTRAINT chk_no_self_reference 
                CHECK (id != owner_client_id)
            ");
        }
    }

    public function down()
    {
        $constraints = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_NAME = 'clients' 
            AND CONSTRAINT_NAME IN ('chk_no_self_reference', 'chk_owner_is_individual')
        ");

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE clients DROP CHECK {$constraint->CONSTRAINT_NAME}");
        }

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'owner_client_id')) {
                $table->dropForeign(['owner_client_id']);
                $table->dropIndex(['owner_client_id']);
                $table->dropColumn('owner_client_id');
            }

            if (Schema::hasColumn('clients', 'organization_business_ids')) {
                $table->dropColumn('organization_business_ids');
            }
        });
    }
};