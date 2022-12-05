<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_fa' => $this->status_fa,
            'code' => $this->code,
            'user' => new User($this->whenLoaded('user')),
            'delivery_method' => $this->deliveryMethod,
            'payment_method' => $this->paymentMethod,
            'address' => $this->address,
            'items' => ProductItem::collection($this->whenLoaded('items')),
            'order_items' => $this->items,
            'price_before_discount_code' => $this->price_before_discount_code,
            'price_after_discount_code' => $this->price_after_discount_code,
            'created_at' => $this->created_at,
        ];
    }
}
