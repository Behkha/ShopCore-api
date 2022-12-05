<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductItemIdToDiscountsTable extends Migration
{
    public function up()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->unsignedBigInteger('product_item_id');

            $table
                ->foreign('product_item_id')
                ->references('id')
                ->on('product_items');
        });
    }

    public function down()
    {
        Schema::table('discounts', function (Blueprint $table) {

        });
    }
}
