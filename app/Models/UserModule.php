<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserModule extends Model
{
    protected $table = 'mstrs_user_modules';

    public $timestamps = false;

    protected $guarded = [];
}
