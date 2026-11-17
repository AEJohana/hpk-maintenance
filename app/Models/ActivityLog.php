<?php

namespace App\Models;

use App\Services\Access;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'trnks_activity_logs';

    protected $guarded = ['id'];

    protected $casts = ['details' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record($subject, string $action, array $details = []): void
    {
        static::create([
            'branch_id' => $subject instanceof AccessRole || $subject instanceof Branch ? null : Access::branch($subject),
            'user_id' => auth()->id(),
            'subject_type' => $subject->getTable(),
            'subject_id' => $subject->id,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
