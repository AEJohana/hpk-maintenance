<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'mstrs_departments';

    protected $casts = ['branch_id' => 'integer', 'active' => 'boolean'];

    protected $guarded = ['id'];
}
