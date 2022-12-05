<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartProductTable extends Migration
{
    public function up()
    {
        Schema::create('cart_product', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_id');
            $table->unsignedInteger('quantity');

            $table
                ->foreign('cart_id')
                ->references('id')
                ->on('carts');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cart_product');
    }
}
