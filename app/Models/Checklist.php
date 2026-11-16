<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $casts = ['branch_id' => 'integer', 'items' => 'array'];

    protected $guarded = ['id'];
}
