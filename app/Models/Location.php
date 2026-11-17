<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'mstrs_locations';

    protected $casts = ['branch_id' => 'integer', 'active' => 'boolean'];

    protected $guarded = ['id'];
}
