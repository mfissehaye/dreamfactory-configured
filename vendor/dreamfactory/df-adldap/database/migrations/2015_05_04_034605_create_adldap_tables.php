<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdldapTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('user', 'adldap')) {
            Schema::table(
                'user',
                function (Blueprint $t){
                    $t->string('adldap', 50)->nullable()->after('remember_token');
                }
            );
        }

        Schema::create(
            'ldap_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->integer('default_role')->unsigned()->index();
                $t->foreign('default_role')->references('id')->on('role')->onDelete('restrict');
                $t->string('host');
                $t->string('base_dn');
                $t->string('account_suffix')->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('user', 'adldap')) {
            Schema::table(
                'user',
                function (Blueprint $t){
                    $t->dropColumn('adldap');
                }
            );
        }

        Schema::dropIfExists('adldap_config');
    }
}
