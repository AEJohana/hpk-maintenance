<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RENAME_MAP = [
        // Master tables
        'branches' => 'mstrs_branches',
        'roles' => 'mstrs_roles',
        'permissions' => 'mstrs_permissions',
        'role_permissions' => 'mstrs_role_permissions',
        'departments' => 'mstrs_departments',
        'locations' => 'mstrs_locations',
        'machines' => 'mstrs_machines',
        'checklists' => 'mstrs_checklists',
        'users' => 'mstrs_users',
        'user_modules' => 'mstrs_user_modules',

        // Transaction tables
        'maintenance_schedules' => 'trnks_maintenance_schedules',
        'inspections' => 'trnks_inspections',
        'transfer_requests' => 'trnks_transfer_requests',
        'activity_logs' => 'trnks_activity_logs',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::RENAME_MAP as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse(self::RENAME_MAP) as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }

        Schema::enableForeignKeyConstraints();
    }
};
