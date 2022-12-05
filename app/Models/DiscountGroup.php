<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountGroup extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'is_on',
    ];

    protected $appends = [
        'model_id',
    ];

    public function getModelIdAttribute()
    {
        if ($this->is_on === 'category') {
            $discount = Discount::where('discount_group_id', $this->id)
                ->first();
            return $discount->item->product->category_id;
        } elseif ($this->is_on === 'product') {
            $discount = Discount::where('discount_group_id', $this->id)
                ->first();
            return $discount->item->product_id;
        }
    }
}
