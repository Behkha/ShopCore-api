<?php

namespace App\Http\Resources;

use App\Models\ProductAttribute as ProductAttributeModel;
use Illuminate\Http\Resources\Json\JsonResource;

class Product extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'gallery' => $this->images,
            'description' => $this->description,
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
            'items' => ProductItem::collection($this->whenLoaded('productItems')),
            'features' => $this->whenLoaded('features'),
            'order_price' => $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->price;
            }),
            'order_qty' => $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->quantity;
            }),
            'order_price_before_discount' =>
            $this->whenPivotLoaded('order_product', function () {
                return $this->pivot->price_before_discount;
            }),

            'is_bookmarked' => $this->when(auth('user')->user(), $this->isBookmarked()),
            'category' => new Category($this->category),
            'brand' => new Brand($this->brand),
            'cart_product_attribute' => $this->whenPivotLoaded('cart_product', function () {
                return new ProductAttribute(ProductAttributeModel::find($this->pivot->product_attribute_id));
            }),
            'cart_quantity' => $this->whenPivotLoaded('cart_product', function () {
                return $this->pivot->quantity;
            }),
            'price' => $this->price,
            'discount' => $this->when($this->discount, $this->discount),
        ];
    }
}
