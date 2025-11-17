<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('allow_overdraft')->default(false);
            $table->decimal('overdraft_limit', 19, 4)->default(0);
            $table->decimal('overdraft_interest_rate', 8, 4)->default(0);
            $table->uuid('overdraft_approved_by_user_id')->nullable();
            $table->timestamp('overdraft_approved_at')->nullable();

            $table->foreign('overdraft_approved_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['overdraft_approved_by_user_id']);
            $table->dropColumn([
                'allow_overdraft',
                'overdraft_limit',
                'overdraft_interest_rate',
                'overdraft_approved_by_user_id',
                'overdraft_approved_at'
            ]);
        });
    }
};