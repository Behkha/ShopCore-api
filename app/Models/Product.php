<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $casts = [
        'gallery' => 'json',
    ];

    protected $fillable = ['title', 'description', 'category_id', 'brand_id'];

    public function getImagesAttribute()
    {
        if ($this->gallery) {
            $images = [];
            foreach ($this->gallery as $image) {
                if (strstr($image, 'http://lorempixel.com')) {
                    array_push($images, $image);
                } else {
                    array_push($images, env('STORAGE_PATH') . $image);
                }
            }
            return $images;
        } else {
            return null;
        }

    }

    public function getFinalPriceAttribute()
    {
        return 1;
    }

    public function getRemainingTimeAttribute()
    {
        $timer = new Carbon($this->counter_created_at);
        return $timer->diffInSeconds(now());
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function bookmarks()
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    public function discounts()
    {
        return $this
            ->hasMany(Discount::class)
            ->orderBy('from', 'asc');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class)->withPivot(
            'quantity', 'price', 'price_before_discount'
        );
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function carts()
    {
        return $this
            ->belongsToMany(Cart::class)
            ->withPivot('quantity');
    }

    public function features()
    {
        return $this->hasMany(Feature::class);
    }

    public function attributes()
    {
        return $this
            ->belongsToMany(Attribute::class)
            ->withPivot('value');
    }

    public function isBookmarked()
    {
        $user = auth('user')->user();
        if (!$user) {
            return false;
        }
        return $this->bookmarks->contains('user_id', $user->id);
    }

    public function getPriceAttribute()
    {
        $lowest = $this
            ->productItems
            ->sortBy('price')
            ->first();
        if ($lowest->discount) {
            $price = ((100 - $lowest->discount->off) * $lowest->price) / 100;
        } else {
            $price = $lowest->price;
        }
        return $price;
    }

    public function getDiscountAttribute()
    {
        $discounts = collect();
        $this
            ->productItems
            ->each(function ($item, $key) use ($discounts) {
                $discounts->push($item->discount);
            });
        return $discounts
            ->sortByDesc('off')
            ->first();
    }

    // return count of this product in orders with status unknown
    public function getUnknownOrderCount()
    {
        $orders = Order::where('status', Order::STATUS['unknown'])
            ->where('created_at', '>=', now()->subMinutes(60)->toDateTimeString())
            ->whereHas('products', function ($query) {
                $query->where('id', $this->id);
            })
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            foreach ($order->products as $product) {
                if ($product->id == $this->id) {
                    $count += $product->pivot->quantity;

                    break;
                }
            }
        }

        return $count;
    }

    public function productItems()
    {
        return $this->hasMany(ProductItem::class);
    }
}
