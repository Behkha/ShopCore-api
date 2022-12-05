<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'unit', 'attribute_set_id', 'is_filter'];

    protected $hidden = ['pivot', 'attribute_set_id'];

    protected $casts = ['is_filter' => 'boolean'];

    public function attributeSet()
    {
        return $this->belongsTo(AttributeSet::class);
    }
}
