<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    protected $fillable = ['product_id', 'price', 'quantity', 'attribute_id', 'value'];

    public $timestamps = false;

    public function discounts()
    {
        return $this
            ->hasMany(Discount::class)
            ->where('starting_date', '<=', now()->toDateString())
            ->where('expiration_date', '>', now()->toDateString());
    }

    public function getDiscountAttribute()
    {
        return $this
            ->discounts
            ->sortByDesc('off')
            ->first();
    }

    public function getAttributeAttribute()
    {
        return Attribute::find($this->attribute_id);
    }

    public function getPriceAfterDiscountAttribute()
    {
        if ($this->discounts->count() === 0) {
            return $this->price;
        }
        $discount = $this
            ->discounts
            ->sortByDesc('off')
            ->first();
        return floor(((100 - $discount->off) * $this->price) / 100);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_item');
    }
}
