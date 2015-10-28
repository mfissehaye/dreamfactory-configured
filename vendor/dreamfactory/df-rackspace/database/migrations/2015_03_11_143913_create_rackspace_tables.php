<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Class CreateRackspaceTables
 */
class CreateRackspaceTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Rackspace service configuration
        Schema::create(
            'rackspace_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('username')->nullable();
                $t->longText('password')->nullable();
                $t->string('tenant_name')->nullable();
                $t->longText('api_key')->nullable();
                $t->string('url')->nullable();
                $t->string('region')->nullable();
                $t->string('storage_type')->nullable();
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
        //Rackspace service configuration
        Schema::dropIfExists('rackspace_config');
    }
}
