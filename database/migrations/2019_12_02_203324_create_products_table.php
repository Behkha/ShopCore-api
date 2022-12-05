<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table
                ->json('gallery')
                ->nullable();
            $table
                ->text('description')
                ->nullable();
            $table
                ->unsignedInteger('view_count')
                ->default(0);
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
            $table->unsignedInteger('brand_id');

            $table
                ->foreign('brand_id')
                ->references('id')
                ->on('brands');

            $table
                ->foreign('category_id')
                ->references('id')
                ->on('categories');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
}
