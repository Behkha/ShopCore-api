<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DiscountCode extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'percent' => $this->percent,
            'max' => $this->max,
            'is_used' => $this->is_used,
            'expiration_date' => $this->expiration_date,
            'redeemed_by' => new User($this->redeemedBy),
            'redeemed_at' => $this->redeemed_at,
            'created_at' => $this->created_at,
        ];
    }
}
