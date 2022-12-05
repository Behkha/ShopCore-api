<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductItem extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'attribute' => new AttributeResource($this->attribute),
            'value' => $this->value,
            'product' => new Product($this->product),
            'discount' => $this->when($this->discount, $this->discount),
            'cart_quantity' => $this->whenPivotLoaded('cart_product', function () {
                return $this->pivot->quantity;
            }),
            'order_quantity' => $this->whenPivotLoaded('order_item', function () {
                return $this->pivot->quantity;
            }),
            'order_price_before_discount' => $this->whenPivotLoaded('order_item', function () {
                return $this->pivot->price_before_discount;
            }),
            'order_price_after_discount' => $this->whenPivotLoaded('order_item', function () {
                return $this->pivot->price_after_discount;
            }),
        ];
    }
}
