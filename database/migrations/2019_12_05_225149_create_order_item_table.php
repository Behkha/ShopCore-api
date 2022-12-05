<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderItemTable extends Migration
{
    public function up()
    {
        Schema::create('order_item', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price_before_discount');
            $table->unsignedInteger('price_after_discount');

            $table
                ->foreign('order_id')
                ->references('id')
                ->on('orders');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_item');
    }
}
