<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['inspection_date' => 'date', 'snapshot' => 'array', 'answers' => 'array', 'photos' => 'array', 'submitted_at' => 'datetime', 'verified_at' => 'datetime'];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function ownerDepartment()
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }

    public function hasNg(): bool
    {
        return collect($this->answers ?? [])->contains(fn ($a) => ($a['value'] ?? null) === 'NG');
    }
}
