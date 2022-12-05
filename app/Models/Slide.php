<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slide extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'image_url',
    ];

    public function getImageUrlAttribute($value)
    {
        return env('STORAGE_PATH') . $value;
    }
}
