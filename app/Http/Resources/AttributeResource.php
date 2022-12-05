<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'is_filter' => $this->is_filter,
            'value' => $this->whenPivotLoaded('attribute_product', function () {
                return $this->pivot->value;
            }),
            'attribute_set' => $this->attributeSet,
        ];
    }
}
