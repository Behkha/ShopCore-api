<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('discount_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('is_on', ['category', 'product']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('discount_groups');
    }
}
