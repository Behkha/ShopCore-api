<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductItemsTable extends Migration
{
    public function up()
    {
        Schema::create('product_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('attribute_id');
            $table->string('value');

            $table
                ->foreign('product_id')
                ->references('id')
                ->on('products');

            $table
                ->foreign('attribute_id')
                ->references('id')
                ->on('attributes');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_items');
    }
}
