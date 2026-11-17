<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Component;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Database\Seeders\MachineSpreadsheetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MachineMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_table_prefixes_conform_to_mstrs_and_trnks_rules(): void
    {
        $expectedMaster = [
            'mstrs_branches',
            'mstrs_departments',
            'mstrs_locations',
            'mstrs_machines',
            'mstrs_components',
            'mstrs_checklists',
            'mstrs_users',
            'mstrs_user_modules',
            'mstrs_roles',
            'mstrs_permissions',
            'mstrs_role_permissions',
        ];

        $expectedTrnks = [
            'trnks_maintenance_schedules',
            'trnks_inspections',
            'trnks_transfer_requests',
            'trnks_activity_logs',
        ];

        foreach ($expectedMaster as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel master [{$table}] harus ada.");
        }

        foreach ($expectedTrnks as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel transaksi [{$table}] harus ada.");
        }
    }

    public function test_qr_code_format_and_resilient_scanner_lookup(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'Cabang Test']);
        $dept = Department::firstOrCreate(['name' => 'Produksi'], ['branch_id' => $branch->id]);
        $loc = Location::firstOrCreate(['name' => 'Line 1 Body 4', 'area' => 'Pabrik', 'branch_id' => $branch->id]);
        $checklist = Checklist::firstOrCreate(['name' => 'Daily Test', 'branch_id' => $branch->id], ['items' => [['label' => 'Kondisi', 'type' => 'condition', 'unit' => '']]]);

        $qrStandard = Machine::formatQrValue('GMAW', 'GMAW-003');
        $this->assertSame('GMAW / GMAW-003 / ...', $qrStandard);

        $machine = Machine::create([
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'location_id' => $loc->id,
            'code' => 'GMAW-003',
            'name' => 'GMAW',
            'qr_value' => $qrStandard,
            'brand' => 'Panasonic',
            'model' => 'YD - 350 KR2',
            'power_kw' => 18.00,
            'power_hp' => 24.14,
            'capacity' => '500A',
            'active' => true,
        ]);

        MaintenanceSchedule::create([
            'machine_id' => $machine->id,
            'checklist_id' => $checklist->id,
            'period' => 'daily',
            'starts_on' => today()->subDay(),
            'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Operator',
            'email' => 'operator@test.example',
            'password' => Hash::make('password1234'),
            'department_id' => $dept->id,
            'branch_id' => $branch->id,
            'active' => true,
        ]);
        DB::table((new \App\Models\UserModule)->getTable())->insert(['user_id' => $user->id, 'module' => 'daily']);

        // 1. Exact QR Lookup: "GMAW / GMAW-003 / ..."
        $this->actingAs($user)
            ->post('/daily-check', ['lookup' => 'qr', 'code' => 'GMAW / GMAW-003 / ...'])
            ->assertRedirect();

        // 2. Resilient Lookup with future additional tokens: "GMAW / GMAW-003 / 2026 / LINE-1"
        $this->actingAs($user)
            ->post('/daily-check', ['lookup' => 'qr', 'code' => 'GMAW / GMAW-003 / 2026 / LINE-1'])
            ->assertRedirect();

        // 3. Direct Code Lookup: "GMAW-003"
        $this->actingAs($user)
            ->post('/daily-check', ['lookup' => 'code', 'code' => 'GMAW-003'])
            ->assertRedirect();
    }

    public function test_machine_components_relationship_and_cascade(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'Cabang Test']);
        $dept = Department::firstOrCreate(['name' => 'Produksi'], ['branch_id' => $branch->id]);
        $loc = Location::firstOrCreate(['name' => 'Line 1', 'area' => 'Pabrik', 'branch_id' => $branch->id]);

        $machine = Machine::create([
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'location_id' => $loc->id,
            'code' => 'MC-TEST-01',
            'name' => 'Mesin Bubut',
            'qr_value' => 'Mesin Bubut / MC-TEST-01 / ...',
            'active' => true,
        ]);

        $comp = Component::create([
            'machine_id' => $machine->id,
            'code' => 'SP-001',
            'name' => 'Motor Penggerak 3 Phase',
            'specification' => '15 KW 380V',
            'quantity' => 1,
            'condition' => 'Baik',
        ]);

        $this->assertCount(1, $machine->components);
        $this->assertSame('Motor Penggerak 3 Phase', $machine->components->first()->name);

        $machine->delete();
        $this->assertDatabaseMissing(Component::class, ['id' => $comp->id]);
    }

    public function test_spreadsheet_seeder_imports_553_machines(): void
    {
        $this->seed(MachineSpreadsheetSeeder::class);

        $this->assertSame(553, Machine::count());

        $sample = Machine::where('code', 'GMAW-003')->first();
        $this->assertNotNull($sample);
        $this->assertSame('GMAW', $sample->name);
        $this->assertSame('Panasonic', $sample->brand);
        $this->assertSame('GMAW / GMAW-003 / ...', $sample->qr_value);
        $this->assertEquals(18.00, $sample->power_kw);
        $this->assertEquals(24.14, $sample->power_hp);
        $this->assertSame('500A', $sample->capacity);
        $this->assertSame('Line 1 Body 4', $sample->location->name);
    }

    public function test_print_qr_page_and_svg_endpoint(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'Cabang Test']);
        $dept = Department::firstOrCreate(['name' => 'Produksi'], ['branch_id' => $branch->id]);
        $loc = Location::firstOrCreate(['name' => 'Line 1', 'area' => 'Pabrik', 'branch_id' => $branch->id]);

        $machine = Machine::create([
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'location_id' => $loc->id,
            'code' => 'GMAW-003',
            'name' => 'GMAW',
            'qr_value' => 'GMAW / GMAW-003 / ...',
            'brand' => 'Panasonic',
            'active' => true,
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.example',
            'password' => Hash::make('password1234'),
            'is_admin' => true,
            'active' => true,
        ]);

        // 1. Test print-qr view
        $response = $this->actingAs($user)->get("/machines/{$machine->id}/print-qr");
        $response->assertOk();
        $response->assertSee('GMAW-003');
        $response->assertSee('GMAW / GMAW-003 / ...');
        $response->assertSee('KAROSERI HPK');
        $response->assertSee('<svg', false);

        // 2. Test SVG raw endpoint
        $svgResponse = $this->actingAs($user)->get("/machines/{$machine->id}/qr-code.svg");
        $svgResponse->assertOk();
        $svgResponse->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svgResponse->getContent());
    }

    public function test_sidebar_navigation_renders_for_authenticated_users(): void
    {
        $user = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@test.example',
            'password' => Hash::make('password1234'),
            'is_admin' => true,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertOk();
        $response->assertSee('class="app-layout has-sidebar"', false);
        $response->assertSee('class="site-sidebar"', false);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Modul Saya');
        $response->assertSee('Keluar');
    }
}
