<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \Morilog\Jalali\Jalalian;

class Order extends Model
{
    protected $fillable = [
        'address_id',
        'delivery_method_id',
        'status',
        'code',
        'user_id',
        'price_before_discount_code',
        'price_after_discount_code',
        'payment_method_id',
    ];

    public const STATUS = [
        'unknown' => 1,
        'registered' => 2,
        'verified' => 3,
        'package' => 4,
        'sent' => 5,
        'delivered' => 6,
        'canceled' => 7,
        'unsuccessful' => 8,
        'rejected' => 9
    ];

    public function getStatusFaAttribute()
    {
        switch ($this->status) {
            case 1:
                return 'نامعلوم';
            case 2:
                return 'ثبت شده';
            case 3:
                return 'تایید شده';
            case 4:
                return 'در حال بسته بندی';
            case 5:
                return 'ارسال شده';
            case 6:
                return 'تحویل داده شده';
            case 7:
                return 'لغو شده';
            case 8:
                return 'ناموفق';
            case 9:
                return 'رد شده';
        }
    }

    public function getCreatedAtAttribute($value)
    {
        return Jalalian::forge($value)->format('%Y-%m-%d %H:i');
    }

    public function items()
    {
        return $this
            ->belongsToMany(ProductItem::class, 'order_item')
            ->withPivot('quantity', 'price_before_discount', 'price_after_discount');
    }

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class)->with(['state', 'city']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
