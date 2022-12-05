<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id'];

    public $timestamps = false;

    public function items()
    {
        return $this
            ->belongsToMany(ProductItem::class, 'cart_product')
            ->withPivot('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
