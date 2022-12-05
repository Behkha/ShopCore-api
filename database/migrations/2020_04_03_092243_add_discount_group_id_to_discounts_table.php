<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountGroupIdToDiscountsTable extends Migration
{
    public function up()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('discount_group_id')
                ->nullable();

            $table
                ->foreign('discount_group_id')
                ->references('id')
                ->on('discount_groups')
                ->onDelete('CASCADE');
        });
    }

    public function down()
    {
        Schema::table('discounts', function (Blueprint $table) {
            //
        });
    }
}
