<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSalesforceTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // SalesforceDB Service Configuration
        Schema::create(
            'salesforce_db_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('username')->nullable();
                $t->longText('password')->nullable();
                $t->longText('security_token')->nullable();
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
        // SalesforceDB Service Configuration
        Schema::dropIfExists('salesforce_db_config');
    }
}
