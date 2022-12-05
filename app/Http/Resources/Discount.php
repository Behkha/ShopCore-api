<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Discount extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'off' => $this->off,
            'starting_date' => $this->starting_date,
            'expiration_date' => $this->expiration_date,
            'discount_group' => $this->discountGroup,
            'item' => new ProductItem($this->whenLoaded('item')),
        ];
    }
}
