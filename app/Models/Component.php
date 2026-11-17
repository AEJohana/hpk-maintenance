<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    protected $table = 'mstrs_components';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
