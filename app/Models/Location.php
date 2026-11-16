<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $casts = ['branch_id' => 'integer'];

    protected $guarded = ['id'];
}
