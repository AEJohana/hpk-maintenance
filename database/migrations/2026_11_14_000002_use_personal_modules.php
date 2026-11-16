<?php

use App\Services\Modules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
            $t->string('position', 100)->nullable();
        });
        Schema::create('user_modules', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('module', 50);
            $t->primary(['user_id', 'module']);
        });
        Schema::table('inspections', function (Blueprint $t) {
            $t->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        foreach (DB::table('users')->get() as $user) {
            $role = DB::table('roles')->find($user->role_id);
            if ($role?->type === 'admin') {
                DB::table('users')->where('id', $user->id)->update(['is_admin' => true]);

                continue;
            }
            $permissions = DB::table('permissions')->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')->where('role_id', $user->role_id)->pluck('key')->all();
            if ($role?->type === 'branch_admin') {
                $permissions = array_diff($permissions, ['inspections.scan', 'inspections.draft', 'inspections.submit', 'transfers.request']);
            }
            foreach (Modules::legacy($permissions) as $module) {
                DB::table('user_modules')->insert(['user_id' => $user->id, 'module' => $module]);
            }
        }
        // Preserve the last known submitting/verifying actor from the existing audit trail.
        foreach (DB::table('inspections')->get() as $inspection) {
            $actors = [];
            foreach (['submitted' => 'submitted_by', 'verified' => 'verified_by'] as $action => $column) {
                if ($action === 'verified' && $inspection->status !== 'verified') {
                    continue;
                }
                $actor = DB::table('activity_logs')->where('subject_type', 'inspections')->where('subject_id', $inspection->id)->where('action', $action)->orderByDesc('id')->value('user_id');
                if ($actor) {
                    $actors[$column] = $actor;
                }
            }
            if ($actors) {
                DB::table('inspections')->where('id', $inspection->id)->update($actors);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Pulihkan backup pra-migrasi untuk mengembalikan pengaturan role lama.');
    }
};
