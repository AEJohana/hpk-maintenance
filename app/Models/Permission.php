<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'mstrs_permissions';

    public $timestamps = false;

    protected $guarded = ['id'];
}
