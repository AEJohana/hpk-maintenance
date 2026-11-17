<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'mstrs_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'location_id',
        'role_id',
        'branch_id',
        'active',
        'is_admin',
        'position',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
        'is_admin' => 'boolean',
        'department_id' => 'integer',
        'location_id' => 'integer',
        'branch_id' => 'integer',
    ];

    public function modules()
    {
        return $this->hasMany(UserModule::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function myMachinesQuery()
    {
        return Machine::query()->where(function ($q) {
            if ($this->location_id && $this->department_id) {
                $q->where('location_id', $this->location_id)
                    ->orWhere('department_id', $this->department_id);
            } elseif ($this->location_id) {
                $q->where('location_id', $this->location_id);
            } elseif ($this->department_id) {
                $q->where('department_id', $this->department_id);
            } else {
                $q->whereRaw('1 = 1');
            }
        });
    }

    public function hasModule(string $key): bool
    {
        return $this->is_admin || $this->modules()->where('module', $key)->exists();
    }

    public function accessRole()
    {
        return $this->belongsTo(AccessRole::class, 'role_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getRoleAttribute($legacy)
    {
        return $this->is_admin ? 'admin' : 'user';
    }
}
