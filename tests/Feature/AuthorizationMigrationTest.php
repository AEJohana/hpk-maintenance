<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationMigrationTest extends TestCase
{
    public function test_upgrade_preserves_legacy_records_and_maps_roles(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('legacy');
        try {
            (require database_path('migrations/2014_10_12_000000_create_users_table.php'))->up();
            (require database_path('migrations/2026_11_13_000001_create_maintenance_tables.php'))->up();
            $d = DB::table('departments')->insertGetId(['name' => 'Lama']);
            $a = DB::table('users')->insertGetId(['name' => 'Admin Lama', 'email' => 'admin@test.example', 'password' => 'existing-hash', 'role' => 'admin']);
            $u = DB::table('users')->insertGetId(['name' => 'Bagian Lama', 'email' => 'bagian@test.example', 'password' => 'other-hash', 'role' => 'department', 'department_id' => $d]);
            $l = DB::table('locations')->insertGetId(['name' => 'Line', 'area' => 'Lama']);
            $m = DB::table('machines')->insertGetId(['name' => 'Mesin Lama', 'code' => 'OLD', 'qr_value' => 'OLDQR', 'department_id' => $d, 'location_id' => $l]);
            $snapshot = json_encode(['name' => 'Original', 'items' => []]);
            $i = DB::table('inspections')->insertGetId(['machine_id' => $m, 'department_id' => $d, 'owner_department_id' => $d, 'user_id' => $u, 'inspection_date' => '2026-11-13', 'snapshot' => $snapshot, 'photos' => json_encode(['private/photo.jpg'])]);
            (require database_path('migrations/2026_11_14_000001_add_authorization.php'))->up();
            $this->assertNull(DB::table('users')->find($a)->branch_id);
            $this->assertNotNull(DB::table('users')->find($a)->role_id);
            $this->assertEquals(1, DB::table('users')->find($u)->branch_id);
            $this->assertEquals($snapshot, DB::table('inspections')->find($i)->snapshot);
            $this->assertEquals('existing-hash', DB::table('users')->find($a)->password);
            $this->assertEquals(1, DB::table('machines')->find($m)->branch_id);
            $this->assertEquals(4, DB::table('roles')->count());
            (require database_path('migrations/2026_11_14_000002_use_personal_modules.php'))->up();
            $this->assertTrue((bool) DB::table('users')->find($a)->is_admin);
            $this->assertFalse((bool) DB::table('users')->find($u)->is_admin);
            $this->assertContains('daily', DB::table('user_modules')->where('user_id', $u)->pluck('module')->all());
            $this->assertEquals('existing-hash', DB::table('users')->find($a)->password);
            $this->assertEquals($snapshot, DB::table('inspections')->find($i)->snapshot);
            $this->assertNull(DB::table('inspections')->find($i)->submitted_by);
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('legacy');
        }
    }
}
