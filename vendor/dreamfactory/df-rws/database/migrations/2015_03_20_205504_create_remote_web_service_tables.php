<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRemoteWebServiceTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //RWS config table
        Schema::create(
            'rws_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->mediumText('base_url');
            }
        );

        //RWS param config table
        Schema::create(
            'rws_parameters_config',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('name');
                $t->mediumText('value')->nullable();
                $t->boolean('exclude')->default(0);
                $t->boolean('outbound')->default(0);
                $t->boolean('cache_key')->default(0);
                $t->integer('action')->default(0);
            }
        );

        //RWS header config table
        Schema::create(
            'rws_headers_config',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('name');
                $t->mediumText('value')->nullable();
                $t->boolean('pass_from_client')->default(0);
                $t->integer('action')->default(0);
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
        //RWS config table
        Schema::dropIfExists('rws_config');

        //RWS parameters config table
        Schema::dropIfExists('rws_parameters_config');

        //RWS headers config table
        Schema::dropIfExists('rws_headers_config');
    }
}
