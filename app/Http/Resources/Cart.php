<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Cart extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'products' => ProductItem::collection($this->items),
        ];
    }
}
