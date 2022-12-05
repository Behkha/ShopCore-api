<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeSet extends Model
{
    protected $fillable = ['name'];

    public $timestamps = false;

    public function attributes()
    {
        return $this->hasMany(Attribute::class);
    }
}
