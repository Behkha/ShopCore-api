<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Discount extends Model
{
    public $timestamps = false;

    public function item()
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }

    public function getStartingDateAttribute($value)
    {
        return Jalalian::forge(new Carbon($value))->format('%Y-%m-%d');
    }

    public function getExpirationDateAttribute($value)
    {
        return Jalalian::forge(new Carbon($value))->format('%Y-%m-%d');
    }

    public function discountGroup()
    {
        return $this->belongsTo(DiscountGroup::class);
    }
}
