<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $table = 'mstrs_checklists';

    protected $casts = ['branch_id' => 'integer', 'items' => 'array', 'active' => 'boolean'];

    protected $guarded = ['id'];
}
