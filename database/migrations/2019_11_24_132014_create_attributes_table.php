<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttributesTable extends Migration
{
    public function up()
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table
                ->string('unit')
                ->nullable();
            $table
                ->boolean('is_filter')
                ->default(true);

            $table
                ->unsignedInteger('attribute_set_id')
                ->nullable();

            $table
                ->foreign('attribute_set_id')
                ->references('id')
                ->on('attribute_sets');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attributes');
    }
}
