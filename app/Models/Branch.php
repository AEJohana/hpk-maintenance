<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'mstrs_branches';

    protected $guarded = ['id'];
}
