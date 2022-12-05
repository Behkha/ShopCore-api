<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscountCodesTable extends Migration
{
    public function up()
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table
                ->string('code', 100)
                ->unique();
            $table->unsignedTinyInteger('percent');
            $table->unsignedInteger('max');
            $table
                ->boolean('is_used')
                ->default(false);
            $table->date('expiration_date');
            $table
                ->unsignedBigInteger('redeemed_by')
                ->nullable();
            $table
                ->timestamp('redeemed_at')
                ->nullable()
                ->default(null);
            $table
                ->timestamp('created_at')
                ->useCurrent();
            $table
                ->unsignedInteger('group_id')
                ->index();

            $table
                ->foreign('redeemed_by')
                ->references('id')
                ->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('discount_codes');
    }
}
