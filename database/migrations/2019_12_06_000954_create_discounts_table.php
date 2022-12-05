<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountsTable extends Migration
{
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('off');
            $table->date('expiration_date');
            $table->date('starting_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discounts');
    }
}
