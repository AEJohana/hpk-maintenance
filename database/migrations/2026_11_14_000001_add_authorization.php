<?php

use App\Services\Access;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('type');
            $t->boolean('builtin')->default(false);
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
        });
        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        $branch = DB::table('branches')->insertGetId(['name' => 'Cabang Utama', 'created_at' => now(), 'updated_at' => now()]);
        foreach (Access::catalog() as $key) {
            DB::table('permissions')->insert(['key' => $key]);
        }
        $roles = [];
        foreach (['admin' => 'Admin', 'branch_admin' => 'Branch Admin', 'maintenance' => 'Maintenance', 'department' => 'Bagian'] as $type => $name) {
            $roles[$type] = DB::table('roles')->insertGetId(['name' => $name, 'type' => $type, 'builtin' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('permissions')->whereIn('key', Access::ceiling($type))->pluck('id') as $id) {
                DB::table('role_permissions')->insert(['role_id' => $roles[$type], 'permission_id' => $id]);
            }
        }
        foreach (['users', 'departments', 'locations', 'machines', 'checklists', 'activity_logs'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete());
            DB::table($table)->update(['branch_id' => $branch]);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $t->boolean('active')->default(true);
        });
        foreach ($roles as $type => $id) {
            DB::table('users')->where('role', $type)->update(['role_id' => $id]);
        }
        DB::table('users')->where('role', 'admin')->update(['branch_id' => null]);
        foreach (DB::table('activity_logs')->get() as $log) {
            $global = $log->subject_type === 'users' && DB::table('users')->where('id', $log->subject_id)->whereNull('branch_id')->exists();
            if ($global) {
                DB::table('activity_logs')->where('id', $log->id)->update(['branch_id' => null]);
            }
        }
        Schema::table('departments', function (Blueprint $t) {
            $t->dropUnique(['name']);
            $t->unique(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        // Names can repeat across branches; rollback requires a restored pre-upgrade backup.
        throw new RuntimeException('Pulihkan backup pra-migrasi untuk rollback struktur cabang.');
    }
};
