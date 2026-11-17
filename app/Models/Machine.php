<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $table = 'mstrs_machines';

    protected $casts = [
        'branch_id' => 'integer',
        'department_id' => 'integer',
        'location_id' => 'integer',
        'active' => 'boolean',
        'manufacture_year' => 'integer',
        'received_date' => 'date',
        'operational_date' => 'date',
        'power_kw' => 'decimal:2',
        'power_hp' => 'decimal:2',
        'condition_percentage' => 'integer',
        'quantity' => 'integer',
    ];

    protected $guarded = ['id'];

    protected static function booted()
    {
        static::creating(function ($machine) {
            if (empty($machine->qr_value) && ! empty($machine->name) && ! empty($machine->code)) {
                $machine->qr_value = static::formatQrValue($machine->name, $machine->code);
            }
        });
    }

    public static function formatQrValue(string $namaMesin, string $noMesin): string
    {
        return trim($namaMesin) . ' / ' . trim($noMesin) . ' / ...';
    }

    public function getQrSvg(int $size = 200): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);

        return $writer->writeString($this->qr_value);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function components()
    {
        return $this->hasMany(Component::class);
    }

    public function schedules()
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class);
    }
}
