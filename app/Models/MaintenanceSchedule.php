<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['weekdays' => 'array', 'starts_on' => 'date', 'active' => 'boolean'];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function dueOn($date): bool
    {
        return $this->active && $this->period === 'daily' && $this->starts_on->toDateString() <= $date->toDateString()
            && in_array($date->dayOfWeekIso, $this->weekdays);
    }
}
