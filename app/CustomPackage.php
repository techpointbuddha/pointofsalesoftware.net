<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomPackage extends Model
{
    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Contact::class,'customer_id');
    }
}
